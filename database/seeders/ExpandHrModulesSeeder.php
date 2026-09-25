<?php

namespace Database\Seeders;

use App\Enums\AbsenceReason;
use App\Enums\AssetCategory;
use App\Enums\AssetIssuanceType;
use App\Enums\DesertionHrStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\OperationalStatus;
use App\Models\Absence;
use App\Models\Desertion;
use App\Models\Guard;
use App\Models\GuardAssetIssuance;
use App\Models\Incident;
use App\Models\Leave;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use App\Services\AbsenceService;
use App\Services\DesertionService;
use App\Services\GuardAssetService;
use App\Services\LeaveService;
use App\Services\Operations\IncidentService;
use App\Services\Operations\OperationalPeriodService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Seeds Leave, Absence, Assets & uniforms, Desertion, and Occurrence Book
 * through the same domain services used by live controllers.
 */
class ExpandHrModulesSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first()
            ?? User::query()->orderBy('id')->first();

        if ($admin === null) {
            throw new \RuntimeException('No user available for HR module seeding.');
        }

        Auth::login($admin);

        $from = Carbon::parse('2026-01-01')->startOfDay();
        $to = now()->startOfDay();

        $periods = app(OperationalPeriodService::class);
        foreach (CarbonPeriod::create($from->copy()->startOfMonth(), '1 month', $to->copy()->startOfMonth()) as $month) {
            $periods->ensureForDate($month->toDateString());
        }

        $before = $this->counts();

        $this->seedLeaves();
        $this->seedAbsences();
        $this->seedAssets($admin);
        $this->seedDesertions();
        $this->seedOccurrences();

        Auth::logout();

        $after = $this->counts();

        $this->command?->newLine();
        $this->command?->info('=== Expand HR modules seed ===');
        foreach ($after as $label => $count) {
            $added = $count - ($before[$label] ?? 0);
            $suffix = $added > 0 ? " (+{$added})" : '';
            $this->command?->info("{$label}: {$count}{$suffix}");
        }
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        return [
            'Leaves' => Leave::query()->count(),
            'Absences' => Absence::query()->count(),
            'Asset issuances' => GuardAssetIssuance::query()->count(),
            'Desertions' => Desertion::query()->count(),
            'Occurrences' => Incident::query()->count(),
        ];
    }

    private function seedLeaves(): void
    {
        $service = app(LeaveService::class);
        $guards = Guard::query()
            ->where('operational_status', '!=', OperationalStatus::Deserted->value)
            ->orderBy('employment_id')
            ->limit(20)
            ->get();

        if ($guards->isEmpty()) {
            return;
        }

        $defs = [
            [
                'type' => LeaveType::Annual,
                'start' => now()->subMonths(4)->startOfMonth()->addDays(2),
                'days' => 5,
                'flow' => 'complete',
                'reason' => 'Family visit — annual leave.',
            ],
            [
                'type' => LeaveType::Sick,
                'start' => now()->subMonths(3)->startOfMonth()->addDays(10),
                'days' => 2,
                'flow' => 'complete',
                'reason' => 'Medical rest — doctor note on file.',
            ],
            [
                'type' => LeaveType::Compassionate,
                'start' => now()->subMonths(2)->startOfMonth()->addDays(5),
                'days' => 3,
                'flow' => 'complete',
                'reason' => 'Bereavement leave.',
            ],
            [
                'type' => LeaveType::Unpaid,
                'start' => now()->subMonth()->startOfMonth()->addDays(8),
                'days' => 2,
                'flow' => 'complete',
                'reason' => 'Unpaid personal leave.',
            ],
            [
                'type' => LeaveType::Annual,
                'start' => now()->addDays(7),
                'days' => 4,
                'flow' => 'approved',
                'reason' => 'Upcoming approved annual leave.',
            ],
            [
                'type' => LeaveType::Sick,
                'start' => now()->addDays(2),
                'days' => 1,
                'flow' => 'pending',
                'reason' => 'Pending sick leave request.',
            ],
            [
                'type' => LeaveType::Other,
                'start' => now()->subWeeks(2),
                'days' => 1,
                'flow' => 'rejected',
                'reason' => 'Insufficient coverage — rejected.',
            ],
            [
                'type' => LeaveType::Annual,
                'start' => now()->addDays(21),
                'days' => 7,
                'flow' => 'pending',
                'reason' => 'Holiday leave pending approval.',
            ],
            [
                'type' => LeaveType::Paternity,
                'start' => now()->subMonths(5)->startOfMonth()->addDays(1),
                'days' => 7,
                'flow' => 'complete',
                'reason' => 'Paternity leave.',
            ],
            [
                'type' => LeaveType::Annual,
                'start' => now()->subDays(10),
                'days' => 3,
                'flow' => 'cancelled',
                'reason' => 'Leave cancelled after coverage issue.',
            ],
        ];

        foreach ($defs as $index => $def) {
            $guard = $guards[$index % $guards->count()];
            $start = $def['start']->copy()->startOfDay();
            $end = $start->copy()->addDays($def['days'] - 1);

            $exists = Leave::query()
                ->where('guard_id', $guard->id)
                ->whereDate('start_date', $start->toDateString())
                ->whereDate('end_date', $end->toDateString())
                ->exists();

            if ($exists) {
                continue;
            }

            try {
                $leave = $service->create([
                    'guard_id' => $guard->id,
                    'leave_type' => $def['type']->value,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'expected_return_date' => $end->copy()->addDay()->toDateString(),
                    'reason' => $def['reason'],
                    'notes' => 'HR modules seed',
                    'status' => LeaveStatus::Pending->value,
                ]);

                match ($def['flow']) {
                    'complete' => (function () use ($service, $leave): void {
                        $approved = $service->approve($leave, 'Approved (HR seed).');
                        $service->complete($approved);
                    })(),
                    'approved' => $service->approve($leave, 'Approved (HR seed).'),
                    'rejected' => $service->reject($leave, 'Rejected (HR seed).'),
                    'cancelled' => (function () use ($service, $leave): void {
                        $approved = $service->approve($leave, 'Approved then cancelled (HR seed).');
                        $service->cancel($approved, 'Cancelled (HR seed).');
                    })(),
                    default => null,
                };
            } catch (Throwable $e) {
                $this->command?->warn('Leave seed skipped: '.$e->getMessage());
            }
        }
    }

    private function seedAbsences(): void
    {
        $service = app(AbsenceService::class);
        $yesterday = now()->subDay()->toDateString();

        $candidates = Shift::query()
            ->whereDate('shift_date', '>=', '2026-01-01')
            ->whereDate('shift_date', '<', $yesterday)
            ->whereDate('shift_date', '<', now()->toDateString())
            ->orderByDesc('shift_date')
            ->limit(120)
            ->get();

        if ($candidates->isEmpty()) {
            return;
        }

        $picked = $candidates->unique('guard_id')->take(18)->values();
        $reasons = AbsenceReason::cases();

        foreach ($picked as $index => $shift) {
            $date = $shift->shift_date->toDateString();

            if (Absence::query()->where('guard_id', $shift->guard_id)->whereDate('absence_date', $date)->exists()) {
                continue;
            }

            try {
                $service->record([
                    'guard_id' => $shift->guard_id,
                    'shift_id' => $shift->id,
                    'site_id' => $shift->site_id,
                    'absence_date' => $date,
                    'reason' => $reasons[$index % count($reasons)]->value,
                    'action_taken' => 'Supervisor contacted guard; follow-up logged.',
                    'notes' => 'HR modules seed — historical absence',
                    'replacement_required' => $index % 3 === 0,
                ]);
            } catch (Throwable $e) {
                // Skip duplicates / period edge cases.
            }
        }
    }

    private function seedAssets(User $admin): void
    {
        $service = app(GuardAssetService::class);
        $guards = Guard::query()->orderBy('employment_id')->limit(30)->get();

        foreach ($guards as $index => $guard) {
            if (GuardAssetIssuance::query()->where('guard_id', $guard->id)->exists()) {
                continue;
            }

            $issuedAt = now()->subMonths(2 + ($index % 4))->startOfMonth()->addDays(($index % 10) + 1)->toDateString();

            try {
                $issuance = $service->issue([
                    'guard_id' => $guard->id,
                    'issuance_type' => $index % 5 === 0
                        ? AssetIssuanceType::Replacement->value
                        : AssetIssuanceType::InitialKit->value,
                    'issued_at' => $issuedAt,
                    'notes' => 'HR modules seed — uniform & kit issue',
                    'lines' => [
                        [
                            'asset_category' => AssetCategory::Uniform->value,
                            'description' => 'Security shirt & trousers',
                            'size' => ['S', 'M', 'L', 'XL'][$index % 4],
                            'quantity' => 2,
                            'unit_value' => 45000,
                        ],
                        [
                            'asset_category' => AssetCategory::Boots->value,
                            'description' => 'Duty boots',
                            'size' => (string) (40 + ($index % 6)),
                            'quantity' => 1,
                            'unit_value' => 85000,
                        ],
                        [
                            'asset_category' => AssetCategory::Radio->value,
                            'description' => 'Handheld radio',
                            'serial_number' => 'RAD-'.str_pad((string) ($guard->id * 17 + $index), 5, '0', STR_PAD_LEFT),
                            'quantity' => 1,
                            'unit_value' => 180000,
                        ],
                    ],
                ], $admin);

                // Partially return kit for a few older issuances.
                if ($index % 7 === 0) {
                    $line = $issuance->lines()->where('asset_category', AssetCategory::Uniform->value)->first();
                    if ($line) {
                        $service->recordReturns($issuance, [
                            $line->id => [
                                'return_qty' => 1,
                                'disposition' => 'returned',
                                'notes' => 'One uniform set returned for replacement sizing.',
                            ],
                        ], $admin);
                    }
                }
            } catch (Throwable $e) {
                $this->command?->warn('Asset seed skipped for '.$guard->employment_id.': '.$e->getMessage());
            }
        }
    }

    private function seedDesertions(): void
    {
        $service = app(DesertionService::class);

        // Prefer guards not currently on duty so we do not tear down live postings.
        $guards = Guard::query()
            ->whereIn('operational_status', [
                OperationalStatus::AwaitingDeployment->value,
                OperationalStatus::OffDuty->value,
            ])
            ->whereDoesntHave('desertions')
            ->orderBy('employment_id')
            ->limit(6)
            ->get();

        $flows = [
            DesertionHrStatus::Reported,
            DesertionHrStatus::Investigating,
            DesertionHrStatus::Confirmed,
            DesertionHrStatus::Returned,
            DesertionHrStatus::Closed,
            DesertionHrStatus::Investigating,
        ];

        foreach ($guards as $index => $guard) {
            $reported = now()->subDays(20 + ($index * 3))->toDateString();
            $lastDuty = Carbon::parse($reported)->subDays(2)->toDateString();

            try {
                $desertion = $service->report([
                    'guard_id' => $guard->id,
                    'date_reported' => $reported,
                    'last_known_duty_date' => $lastDuty,
                    'last_known_site_id' => $guard->current_site_id ?? Site::query()->value('id'),
                    'circumstances' => 'Failed to report for assigned duty and unreachable on phone.',
                    'action_taken' => 'Supervisor notified HR; site coverage adjusted.',
                    'notes' => 'HR modules seed',
                ]);

                $target = $flows[$index % count($flows)];
                if ($target !== DesertionHrStatus::Reported) {
                    $service->updateStatus($desertion, $target, 'Status updated during HR modules seed.');
                }
            } catch (Throwable $e) {
                $this->command?->warn('Desertion seed skipped: '.$e->getMessage());
            }
        }
    }

    private function seedOccurrences(): void
    {
        $service = app(IncidentService::class);
        $sites = Site::query()->orderBy('code')->get();
        $guards = Guard::query()->orderBy('employment_id')->limit(24)->get();
        $assignees = User::query()->where('is_active', true)->orderBy('id')->limit(5)->get();

        if ($sites->isEmpty()) {
            return;
        }

        $defs = [
            [IncidentType::Theft, IncidentSeverity::High, IncidentStatus::Investigating, 'Missing copper cable from store', 'Approximately 15 metres of cable removed overnight from the store room.'],
            [IncidentType::Trespass, IncidentSeverity::Medium, IncidentStatus::Resolved, 'Unauthorised person at perimeter', 'Individual climbed perimeter fence; escorted out and ID recorded.'],
            [IncidentType::Disturbance, IncidentSeverity::Low, IncidentStatus::Closed, 'Noise complaint at gate', 'Neighbours reported loud argument near main gate; situation calmed.'],
            [IncidentType::Medical, IncidentSeverity::High, IncidentStatus::FollowUp, 'Guard injured on duty', 'Guard slipped on wet floor; first aid given, referred to clinic.'],
            [IncidentType::Breach, IncidentSeverity::Critical, IncidentStatus::Investigating, 'Forced padlock on side gate', 'Side gate padlock found cut; area searched, no further loss found.'],
            [IncidentType::Vandalism, IncidentSeverity::Medium, IncidentStatus::Reported, 'Graffiti on compound wall', 'Fresh graffiti painted on east wall overnight.'],
            [IncidentType::Fire, IncidentSeverity::High, IncidentStatus::Resolved, 'Small bin fire near kitchen', 'Bin fire extinguished with extinguisher; client notified.'],
            [IncidentType::Assault, IncidentSeverity::Critical, IncidentStatus::FollowUp, 'Altercation with visitor', 'Visitor assaulted gate guard; police called, statement taken.'],
            [IncidentType::Other, IncidentSeverity::Low, IncidentStatus::Closed, 'Lost & found handbag', 'Handbag found at reception and logged for client collection.'],
            [IncidentType::Theft, IncidentSeverity::Medium, IncidentStatus::Reported, 'Phone reported stolen from office', 'Staff member reported phone missing from unlocked office.'],
            [IncidentType::Trespass, IncidentSeverity::Low, IncidentStatus::Closed, 'Street vendor inside compound', 'Vendor entered without escort; escorted out peacefully.'],
            [IncidentType::Disturbance, IncidentSeverity::Medium, IncidentStatus::Investigating, 'Drunk person refused entry', 'Intoxicated person refused at gate and became abusive.'],
            [IncidentType::Breach, IncidentSeverity::High, IncidentStatus::Reported, 'CCTV blind spot noted', 'Blind spot identified near loading bay during night patrol.'],
            [IncidentType::Medical, IncidentSeverity::Medium, IncidentStatus::Resolved, 'Visitor fainted at reception', 'Visitor fainted; first aid + ambulance called; stable.'],
            [IncidentType::Vandalism, IncidentSeverity::Low, IncidentStatus::Closed, 'Broken glass at gatehouse', 'Window pane cracked; temporary covering applied.'],
            [IncidentType::Fire, IncidentSeverity::Critical, IncidentStatus::Investigating, 'Electrical smell in generator room', 'Burning smell investigated; electrician called; area isolated.'],
        ];

        foreach ($defs as $index => $def) {
            [$type, $severity, $status, $title, $description] = $def;
            $site = $sites[$index % $sites->count()];
            $guard = $guards->isNotEmpty() ? $guards[$index % $guards->count()] : null;
            $occurredAt = now()->subDays(2 + ($index * 3))->setTime(8 + ($index % 10), 15 + ($index % 3) * 10);

            $exists = Incident::query()
                ->where('site_id', $site->id)
                ->where('title', $title)
                ->exists();

            if ($exists) {
                continue;
            }

            try {
                $incident = $service->record([
                    'site_id' => $site->id,
                    'guard_id' => $guard?->id,
                    'incident_type' => $type->value,
                    'severity' => $severity->value,
                    'occurred_at' => $occurredAt->toDateTimeString(),
                    'title' => $title,
                    'description' => $description,
                    'action_taken' => 'Logged in occurrence book; supervisor briefed.',
                    'client_notified' => in_array($severity, [IncidentSeverity::High, IncidentSeverity::Critical], true),
                    'police_reference' => $severity === IncidentSeverity::Critical ? 'POL-'.now()->format('Ymd').'-'.($index + 1) : null,
                    'assigned_to' => $assignees->isNotEmpty() ? $assignees[$index % $assignees->count()]->id : null,
                    'follow_up_due_at' => $status === IncidentStatus::FollowUp
                        ? now()->addDays(3)->toDateTimeString()
                        : null,
                ]);

                if ($status !== IncidentStatus::Reported) {
                    $service->updateFollowUp($incident, [
                        'status' => $status->value,
                        'follow_up_notes' => 'Updated during HR modules seed.',
                        'action_taken' => $incident->action_taken,
                        'client_notified' => $incident->client_notified,
                        'police_reference' => $incident->police_reference,
                        'assigned_to' => $incident->assigned_to,
                        'follow_up_due_at' => $incident->follow_up_due_at?->toDateTimeString(),
                    ]);
                }
            } catch (Throwable $e) {
                $this->command?->warn('Occurrence seed skipped: '.$e->getMessage());
            }
        }
    }
}
