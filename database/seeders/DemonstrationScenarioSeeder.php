<?php

namespace Database\Seeders;

use App\Enums\AssetCategory;
use App\Enums\AssetIssuanceType;
use App\Enums\AttendanceEventType;
use App\Enums\ContractStatus;
use App\Enums\DeploymentShiftType;
use App\Enums\EmploymentStatus;
use App\Enums\GuardClassification;
use App\Enums\OperationalStatus;
use App\Enums\ShiftPeriod;
use App\Enums\SiteStatus;
use App\Enums\WorkOrderCategory;
use App\Enums\WorkOrderPriority;
use App\Models\Client;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\GuardAssetIssuance;
use App\Models\ManpowerGap;
use App\Models\PurchaseInvoice;
use App\Models\Region;
use App\Models\Site;
use App\Models\Supervisor;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\DesertionService;
use App\Services\Finance\Ledger\GlPeriodService;
use App\Services\Finance\Ledger\PurchaseInvoiceService;
use App\Services\Finance\PayrollCalculationService;
use App\Services\Finance\PayrollRunService;
use App\Services\GuardAssetService;
use App\Services\GuardService;
use App\Services\ManpowerGapService;
use App\Services\OrganizationService;
use App\Services\WorkOrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Named Platinum Security demo scenarios layered on DemoData + WorkflowVolume.
 *
 * Walkthrough-ready data for: manpower gaps / OT, desertion, work orders,
 * purchases, attendance, asset returns, GL periods, and payroll lifecycle.
 */
class DemonstrationScenarioSeeder extends Seeder
{
    private const DEMO_SITE_CODE = 'DEMO-PLAZA';

    private const DEMO_PURCHASE_SUPPLIER = 'Kampala Uniform Supplies (Demo)';

    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first()
            ?? User::query()->first();

        if ($admin) {
            Auth::login($admin);
        }

        $this->command?->info('Seeding demonstration scenarios…');

        try {
            app(GlPeriodService::class)->ensureRollingWindow();
        } catch (Throwable $e) {
            $this->command?->warn('GL periods: '.$e->getMessage());
        }

        $site = $this->ensureDemoPlazaSite();
        $this->seedManpowerGapAndOvertime($site);
        $this->seedAttendanceAtPlaza($site);
        $this->seedDesertionScenario($site);
        $this->seedWorkOrders($site);
        $this->seedProcurementPurchases();
        $this->seedAssetReturnScenario();
        $this->seedPayrollLifecycle();

        Auth::logout();

        $this->printSummary();
    }

    private function ensureDemoPlazaSite(): Site
    {
        $region = Region::query()->where('code', 'KLA')->first()
            ?? Region::query()->where('code', 'CEN')->first()
            ?? Region::query()->orderBy('id')->firstOrFail();

        $supervisor = Supervisor::query()
            ->where('region_id', $region->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->first()
            ?? Supervisor::query()->where('region_id', $region->id)->orderBy('id')->first();

        $client = Client::query()->updateOrCreate(
            ['name' => 'Pearl Plaza Holdings (Demo)'],
            [
                'contact_person' => 'Rebecca Nambi',
                'phone' => '+256700111222',
                'email' => 'security@pearlplaza-demo.local',
                'address' => 'Plot 12 Kampala Road, Kampala',
                'contract_start_date' => now()->subYear()->toDateString(),
                'contract_end_date' => now()->addYear()->toDateString(),
                'contract_status' => ContractStatus::Active,
                'notes' => 'Flagship demo client for manpower-gap and OT walkthroughs.',
            ],
        );

        $site = Site::query()->updateOrCreate(
            ['code' => self::DEMO_SITE_CODE],
            [
                'name' => 'Pearl Plaza Kampala (Demo)',
                'client_id' => $client->id,
                'region_id' => $region->id,
                'supervisor_id' => $supervisor?->id,
                'physical_location' => 'Kampala Road, Central Business District',
                'site_contact_person' => 'Rebecca Nambi',
                'site_contact_phone' => '+256700111222',
                'contract_start_date' => now()->subYear()->toDateString(),
                'contract_end_date' => now()->addYear()->toDateString(),
                'required_guards' => 5,
                'required_day_guards' => 2,
                'required_day_armed_guards' => 0,
                'required_day_unarmed_guards' => 2,
                'required_night_guards' => 3,
                'required_night_armed_guards' => 0,
                'required_night_unarmed_guards' => 3,
                'number_of_posts' => 3,
                'status' => SiteStatus::Active,
                'notes' => 'Demo site: night posts deliberately under-filled for OT coverage demos.',
            ],
        );

        app(OrganizationService::class)->syncSiteManpower($site, 'Demo plaza manpower baseline');

        return $site->fresh();
    }

    private function seedManpowerGapAndOvertime(Site $site): void
    {
        $guards = app(GuardService::class);
        $deployments = app(\App\Services\DeploymentService::class);
        $gaps = app(ManpowerGapService::class);
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        // End prior demo OT on yesterday so same-day ended rows do not still count as coverage.
        Deployment::query()
            ->where('site_id', $site->id)
            ->temporary()
            ->get()
            ->each(function (Deployment $deployment) use ($deployments, $yesterday): void {
                try {
                    if ($deployment->isActive()) {
                        $deployments->end($deployment, $yesterday, 'Demo scenario refresh.');
                    } elseif ($deployment->end_date && $deployment->end_date->toDateString() >= now()->toDateString()) {
                        $deployment->forceFill(['end_date' => $yesterday])->save();
                    }
                } catch (Throwable) {
                    try {
                        $deployment->forceFill([
                            'end_date' => $yesterday,
                            'is_current' => false,
                            'status' => \App\Enums\DeploymentStatus::Ended->value,
                        ])->save();
                    } catch (Throwable) {
                        // continue
                    }
                }
            });

        // OT duty shifts recorded for today also count toward overtime_covered — clear demo ones.
        $demoGuardIds = Guard::query()
            ->where('email', 'like', 'demo.plaza%@platinumsecurity.local')
            ->pluck('id');

        \App\Models\Shift::query()
            ->where('site_id', $site->id)
            ->whereDate('shift_date', $today)
            ->where('shift_type', \App\Enums\ShiftType::Overtime)
            ->where(function ($q) use ($demoGuardIds): void {
                $q->whereIn('guard_id', $demoGuardIds)
                    ->orWhere('notes', 'like', '%Demo OT%')
                    ->orWhere('notes', 'like', '%Pearl Plaza%');
            })
            ->delete();

        // Collapse duplicate current permanent postings (same guard twice).
        $permanentIds = Deployment::query()
            ->where('site_id', $site->id)
            ->current()
            ->permanent()
            ->orderBy('id')
            ->get(['id', 'guard_id']);

        $seenGuards = [];
        foreach ($permanentIds as $row) {
            $gid = (int) $row->guard_id;
            if (isset($seenGuards[$gid])) {
                Deployment::query()->whereKey($row->id)->update([
                    'is_current' => false,
                    'status' => \App\Enums\DeploymentStatus::Ended->value,
                    'end_date' => $yesterday,
                    'notes' => 'Demo duplicate permanent posting collapsed.',
                ]);
            } else {
                $seenGuards[$gid] = true;
            }
        }

        $dayGuards = [];
        $nightGuard = null;
        $otCandidates = [];

        for ($i = 1; $i <= 5; $i++) {
            $email = 'demo.plaza'.$i.'@platinumsecurity.local';
            $guard = Guard::query()->where('email', $email)->orderBy('id')->first();

            if (! $guard) {
                try {
                    $guard = $guards->createGuard([
                        'first_name' => 'Demo',
                        'last_name' => 'Plaza'.$i,
                        'phone' => '+256701'.str_pad((string) (1000 + $i), 6, '0', STR_PAD_LEFT),
                        'email' => $email,
                        'gender' => 'male',
                        'region_id' => $site->region_id,
                        'employment_status' => EmploymentStatus::Active->value,
                        'operational_status' => OperationalStatus::AwaitingDeployment->value,
                        'guard_classification' => GuardClassification::Unarmed->value,
                        'date_employed' => now()->subMonths(6)->toDateString(),
                        'base_shift_rate' => 450_000,
                        'overtime_shift_rate' => 35_000,
                        'notes' => 'Demonstration scenario guard for Pearl Plaza.',
                    ]);
                } catch (Throwable $e) {
                    $this->command?->warn('Demo guard '.$i.' skipped: '.$e->getMessage());

                    continue;
                }
            }

            // Collapse duplicate email rows created by earlier re-seeds (email is not unique).
            Guard::query()
                ->where('email', $email)
                ->where('id', '!=', $guard->id)
                ->orderBy('id')
                ->get()
                ->each(function (Guard $dup) use ($deployments, $yesterday): void {
                    Deployment::query()
                        ->where('guard_id', $dup->id)
                        ->current()
                        ->get()
                        ->each(function (Deployment $deployment) use ($deployments, $yesterday): void {
                            try {
                                $deployments->end($deployment, $yesterday, 'Demo duplicate guard cleanup.');
                            } catch (Throwable) {
                                $deployment->forceFill([
                                    'is_current' => false,
                                    'status' => \App\Enums\DeploymentStatus::Ended->value,
                                    'end_date' => $yesterday,
                                ])->save();
                            }
                        });
                    $dup->forceFill([
                        'employment_status' => EmploymentStatus::Terminated->value,
                        'operational_status' => OperationalStatus::AwaitingDeployment->value,
                        'email' => 'dup.'.$dup->id.'.'.$dup->email,
                        'notes' => 'Superseded duplicate demo guard.',
                    ])->save();
                });

            if ($i <= 2) {
                $dayGuards[] = $guard;
            } elseif ($i === 3) {
                $nightGuard = $guard;
            } else {
                $otCandidates[] = $guard;
            }
        }

        foreach ($dayGuards as $guard) {
            try {
                $existing = Deployment::query()->current()->permanent()->where('guard_id', $guard->id)->first();
                if ($existing) {
                    continue;
                }
                $deployments->deploy([
                    'guard_id' => $guard->id,
                    'site_id' => $site->id,
                    'shift_type' => DeploymentShiftType::Day->value,
                    'start_date' => $today,
                    'notes' => 'Demo permanent day posting — Pearl Plaza.',
                ]);
            } catch (Throwable $e) {
                $this->command?->warn('Day deploy skipped: '.$e->getMessage());
            }
        }

        if ($nightGuard) {
            try {
                $existing = Deployment::query()->current()->permanent()->where('guard_id', $nightGuard->id)->first();
                if (! $existing) {
                    $deployments->deploy([
                        'guard_id' => $nightGuard->id,
                        'site_id' => $site->id,
                        'shift_type' => DeploymentShiftType::Night->value,
                        'start_date' => $today,
                        'notes' => 'Demo permanent night posting — only 1 of 3 required.',
                    ]);
                }
            } catch (Throwable $e) {
                $this->command?->warn('Night deploy skipped: '.$e->getMessage());
            }
        }

        // Keep a single permanent night slot open for the OT demo (end any extras).
        $keptNight = false;
        Deployment::query()
            ->where('site_id', $site->id)
            ->current()
            ->permanent()
            ->where('shift_type', DeploymentShiftType::Night)
            ->orderBy('id')
            ->get()
            ->each(function (Deployment $deployment) use ($nightGuard, $deployments, $yesterday, &$keptNight): void {
                $isCanonical = $nightGuard && (int) $deployment->guard_id === (int) $nightGuard->id;
                if ($isCanonical && ! $keptNight) {
                    $keptNight = true;

                    return;
                }
                try {
                    $deployments->end($deployment, $yesterday, 'Demo night baseline — single permanent only.');
                } catch (Throwable) {
                    $deployment->forceFill([
                        'is_current' => false,
                        'status' => \App\Enums\DeploymentStatus::Ended->value,
                        'end_date' => $yesterday,
                    ])->save();
                }
            });

        // Each deploy() syncs gaps immediately and freezes original_shortage mid-baseline.
        // Reset today's gaps so originals match the finished permanent posting picture.
        ManpowerGap::query()
            ->where('site_id', $site->id)
            ->whereDate('gap_date', $today)
            ->delete();

        // Sync gaps: night should show original shortage = 2 (3 required − 1 permanent).
        $gapRows = $gaps->syncSiteDate($site->fresh(), $today);
        $nightGap = $gapRows->first(fn (ManpowerGap $g) => $g->period === ShiftPeriod::Night);

        if ($nightGap && $nightGap->remaining_shortage > 0 && $dayGuards !== []) {
            try {
                // Day-posted guard takes night OT — temporary coverage linked to gap.
                $gaps->resolveWithOvertime($nightGap, [
                    'guard_id' => $dayGuards[0]->id,
                    'notes' => 'Demo OT: day guard covering night shortage at Pearl Plaza.',
                ]);
                $this->command?->info('Night gap partially resolved with overtime (day guard).');
            } catch (Throwable $e) {
                $this->command?->warn('OT resolve skipped: '.$e->getMessage());
            }
        }

        // Leave one night slot open so Coverage shows remaining shortage after OT.
        $final = $gaps->syncGap($site->fresh(), $today, ShiftPeriod::Night);
        $this->command?->info(sprintf(
            'Pearl Plaza night gap — original %d, OT covered %d, remaining %d (%s)',
            $final->original_shortage,
            $final->overtime_covered,
            $final->remaining_shortage,
            $final->status->value,
        ));
    }

    private function seedAttendanceAtPlaza(Site $site): void
    {
        $attendance = app(AttendanceService::class);
        $posted = Deployment::query()
            ->current()
            ->permanent()
            ->where('site_id', $site->id)
            ->with('assignedGuard')
            ->limit(3)
            ->get();

        foreach ($posted as $deployment) {
            $guard = $deployment->assignedGuard;
            if (! $guard) {
                continue;
            }

            try {
                $attendance->record([
                    'guard_id' => $guard->id,
                    'site_id' => $site->id,
                    'event_type' => AttendanceEventType::CheckIn->value,
                    'occurred_at' => now()->setTime(5, 55)->toDateTimeString(),
                    'source' => 'manual',
                    'notes' => 'Demo parade check-in — Pearl Plaza.',
                ]);
            } catch (Throwable $e) {
                $this->command?->warn('Attendance skipped: '.$e->getMessage());
            }
        }
    }

    private function seedDesertionScenario(Site $site): void
    {
        $guards = app(GuardService::class);
        $deployments = app(\App\Services\DeploymentService::class);
        $desertions = app(DesertionService::class);

        try {
            $guard = Guard::query()->where('email', 'demo.desertion@platinumsecurity.local')->first();
            if (! $guard) {
                $guard = $guards->createGuard([
                    'first_name' => 'Demo',
                    'last_name' => 'Deserted',
                    'phone' => '+256701999001',
                    'email' => 'demo.desertion@platinumsecurity.local',
                    'gender' => 'male',
                    'region_id' => $site->region_id,
                    'employment_status' => EmploymentStatus::Active->value,
                    'operational_status' => OperationalStatus::AwaitingDeployment->value,
                    'guard_classification' => GuardClassification::Unarmed->value,
                    'date_employed' => now()->subMonths(8)->toDateString(),
                    'base_shift_rate' => 450_000,
                    'notes' => 'Demonstration desertion case guard.',
                ]);
            }

            if (! Deployment::query()->current()->where('guard_id', $guard->id)->exists()
                && $guard->operational_status !== OperationalStatus::Deserted) {
                // Use a different site if plaza night is full — pick any site in region with capacity.
                $target = Site::query()
                    ->where('region_id', $site->region_id)
                    ->where('id', '!=', $site->id)
                    ->where('status', SiteStatus::Active)
                    ->orderBy('id')
                    ->first() ?? $site;

                try {
                    $deployments->deploy([
                        'guard_id' => $guard->id,
                        'site_id' => $target->id,
                        'shift_type' => DeploymentShiftType::Day->value,
                        'start_date' => now()->subDays(14)->toDateString(),
                        'notes' => 'Pre-desertion posting for demo.',
                    ]);
                } catch (Throwable) {
                    // Site may be at capacity — report desertion without posting.
                }
            }

            if ($guard->fresh()->operational_status !== OperationalStatus::Deserted) {
                $desertions->report([
                    'guard_id' => $guard->id,
                    'date_reported' => now()->subDays(2)->toDateString(),
                    'last_known_duty_date' => now()->subDays(3)->toDateString(),
                    'last_known_site_id' => $guard->fresh()->current_site_id ?? $site->id,
                    'circumstances' => 'Failed to report for duty for 48 hours. Phone unreachable.',
                    'action_taken' => 'Site supervisor notified HR. Replacement requested.',
                    'notes' => 'Demo desertion for HR follow-up walkthrough.',
                ]);
                $this->command?->info('Desertion reported for demo.desertion@…');
            }
        } catch (Throwable $e) {
            $this->command?->warn('Desertion scenario skipped: '.$e->getMessage());
        }
    }

    private function seedWorkOrders(Site $site): void
    {
        $orders = app(WorkOrderService::class);
        $hr = User::query()->where('email', 'hr@platinumsecurity.local')->first();
        $ops = User::query()->where('email', 'operations@platinumsecurity.local')->first();

        $defs = [
            [
                'title' => 'Follow up desertion — Demo Deserted',
                'description' => 'Collect statements, recover kit, and update employment status.',
                'category' => WorkOrderCategory::Hr->value,
                'priority' => WorkOrderPriority::High->value,
                'assigned_to' => $hr?->id,
                'due_at' => now()->addDays(2)->toDateTimeString(),
                'region_id' => $site->region_id,
            ],
            [
                'title' => 'Pearl Plaza night coverage review',
                'description' => 'Confirm OT coverage and permanent night recruitment need.',
                'category' => WorkOrderCategory::Staffing->value,
                'priority' => WorkOrderPriority::Normal->value,
                'assigned_to' => $ops?->id,
                'due_at' => now()->addDays(5)->toDateTimeString(),
                'region_id' => $site->region_id,
            ],
            [
                'title' => 'Uniform top-up for Central region',
                'description' => 'Procurement to raise purchase order for 40 uniform sets.',
                'category' => WorkOrderCategory::General->value,
                'priority' => WorkOrderPriority::Normal->value,
                'assigned_to' => User::query()->where('email', 'procurement@platinumsecurity.local')->value('id'),
                'due_at' => now()->addWeek()->toDateTimeString(),
                'region_id' => $site->region_id,
            ],
        ];

        foreach ($defs as $def) {
            try {
                $existing = \App\Models\WorkOrder::query()->where('title', $def['title'])->orderBy('id')->first();
                if ($existing) {
                    \App\Models\WorkOrder::query()
                        ->where('title', $def['title'])
                        ->where('id', '!=', $existing->id)
                        ->delete();

                    continue;
                }
                $orders->createManual($def, Auth::user());
            } catch (Throwable $e) {
                $this->command?->warn('Work order skipped: '.$e->getMessage());
            }
        }
    }

    private function seedProcurementPurchases(): void
    {
        $purchases = app(PurchaseInvoiceService::class);
        $actor = User::query()->where('email', 'procurement@platinumsecurity.local')->first()
            ?? Auth::user();

        $bills = [
            [
                'supplier_name' => self::DEMO_PURCHASE_SUPPLIER,
                'supplier_tin' => '1000123456',
                'supplier_invoice_no' => 'KUS-DEMO-001',
                'bill_date' => now()->subDays(5)->toDateString(),
                'due_date' => now()->addDays(25)->toDateString(),
                'subtotal' => 4_800_000,
                'tax_amount' => 864_000,
                'description' => '40 × security uniform sets (trousers, shirt, beret).',
                'notes' => 'Demo posted purchase for procurement / VAT pack.',
                'post' => true,
            ],
            [
                'supplier_name' => self::DEMO_PURCHASE_SUPPLIER,
                'supplier_tin' => '1000123456',
                'supplier_invoice_no' => 'KUS-DEMO-002',
                'bill_date' => now()->toDateString(),
                'due_date' => now()->addMonth()->toDateString(),
                'subtotal' => 1_250_000,
                'tax_amount' => 225_000,
                'description' => 'Radios and torch kit top-up — Central region.',
                'notes' => 'Demo draft purchase awaiting post.',
                'post' => false,
            ],
            [
                'supplier_name' => 'Nile Security Equipment Ltd (Demo)',
                'supplier_tin' => '1000987654',
                'supplier_invoice_no' => 'NSE-440',
                'bill_date' => now()->subDays(12)->toDateString(),
                'due_date' => now()->subDays(2)->toDateString(),
                'subtotal' => 2_100_000,
                'tax_amount' => 378_000,
                'description' => 'Boots and raincoats — rainy season issue.',
                'notes' => 'Demo posted purchase (due soon).',
                'post' => true,
            ],
        ];

        foreach ($bills as $bill) {
            $post = $bill['post'];
            unset($bill['post']);

            try {
                $existing = PurchaseInvoice::query()
                    ->where('supplier_invoice_no', $bill['supplier_invoice_no'])
                    ->orderBy('id')
                    ->first();
                if ($existing) {
                    // Drop accidental duplicates from earlier non-idempotent seeds.
                    PurchaseInvoice::query()
                        ->where('supplier_invoice_no', $bill['supplier_invoice_no'])
                        ->where('id', '!=', $existing->id)
                        ->where('status', 'draft')
                        ->delete();

                    continue;
                }
                $created = $purchases->createDraft($bill, $actor);
                if ($post) {
                    $purchases->post($created, $actor);
                }
            } catch (Throwable $e) {
                $this->command?->warn('Purchase skipped: '.$e->getMessage());
            }
        }

        $this->command?->info('Procurement purchase invoices seeded (draft + posted).');
    }

    private function seedAssetReturnScenario(): void
    {
        $assets = app(GuardAssetService::class);
        $issuance = GuardAssetIssuance::query()
            ->with('lines')
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (! $issuance || $issuance->lines->isEmpty()) {
            // Issue a dedicated demo kit then return part of it.
            $guard = Guard::query()
                ->whereDoesntHave('supervisorProfile')
                ->where('employment_status', EmploymentStatus::Active)
                ->where('email', 'like', 'demo.plaza%@platinumsecurity.local')
                ->first()
                ?? Guard::query()->whereDoesntHave('supervisorProfile')->orderBy('id')->first();

            if (! $guard) {
                return;
            }

            try {
                $issuance = $assets->issue([
                    'guard_id' => $guard->id,
                    'issuance_type' => AssetIssuanceType::InitialKit->value,
                    'issued_at' => now()->subMonths(2)->toDateString(),
                    'notes' => 'Demo kit issuance for return walkthrough.',
                    'lines' => [
                        [
                            'asset_category' => AssetCategory::Uniform->value,
                            'description' => 'Demo uniform set',
                            'quantity' => 1,
                            'unit_value' => 120_000,
                        ],
                        [
                            'asset_category' => AssetCategory::Other->value,
                            'description' => 'Demo torch',
                            'quantity' => 1,
                            'unit_value' => 25_000,
                        ],
                    ],
                ], Auth::user());
            } catch (Throwable $e) {
                $this->command?->warn('Demo asset issue skipped: '.$e->getMessage());

                return;
            }
        }

        $line = $issuance->fresh('lines')->lines->first(fn ($l) => (int) $l->quantity_returned < (int) $l->quantity)
            ?? $issuance->fresh('lines')->lines->first();
        if (! $line) {
            return;
        }

        if ((int) $line->quantity_returned >= (int) $line->quantity) {
            $this->command?->info('Asset return already on file for '.$issuance->reference);

            return;
        }

        try {
            $assets->recordReturns($issuance, [
                $line->id => [
                    'return_qty' => 1,
                    'disposition' => 'returned',
                    'notes' => 'Demo partial kit return at store.',
                ],
            ], Auth::user());
            $this->command?->info('Asset return recorded on issuance '.$issuance->reference);
        } catch (Throwable $e) {
            $this->command?->warn('Asset return skipped: '.$e->getMessage());
        }
    }

    private function seedPayrollLifecycle(): void
    {
        try {
            $runs = app(PayrollRunService::class);
            $calc = app(PayrollCalculationService::class);
            $period = now()->subMonth()->startOfMonth();

            // Prefer advancing the volume seeder's company-wide run; otherwise open a DEMO-PLAZA scoped draft.
            $run = \App\Models\PayrollRun::query()
                ->where('period_year', $period->year)
                ->where('period_month', $period->month)
                ->whereNull('region_id')
                ->whereNull('site_id')
                ->whereNot('status', \App\Enums\PayrollRunStatus::Cancelled->value)
                ->orderBy('id')
                ->first();

            if (! $run) {
                $run = $runs->createDraft([
                    'period_year' => $period->year,
                    'period_month' => $period->month,
                    'site_id' => Site::query()->where('code', self::DEMO_SITE_CODE)->value('id'),
                    'notes' => 'Demo lifecycle payroll — calculate → submit → approve.',
                ], Auth::user());
            }

            if (in_array($run->status, [
                \App\Enums\PayrollRunStatus::Draft,
                \App\Enums\PayrollRunStatus::Calculated,
            ], true) && $run->status === \App\Enums\PayrollRunStatus::Draft) {
                $calc->calculate($run);
                $run = $run->fresh();
            }

            if ($run->status === \App\Enums\PayrollRunStatus::Calculated) {
                try {
                    $runs->submit($run, Auth::user());
                    $run = $run->fresh();
                } catch (Throwable $e) {
                    $this->command?->warn('Payroll submit skipped: '.$e->getMessage());

                    return;
                }
            }

            if ($run->status === \App\Enums\PayrollRunStatus::Submitted) {
                $md = User::query()->where('email', 'md@platinumsecurity.local')->first();
                if ($md) {
                    Auth::login($md);
                    try {
                        $runs->approve($run->fresh(), $md);
                        $this->command?->info('Demo payroll approved: '.$run->fresh()->reference);
                    } catch (Throwable $e) {
                        $this->command?->warn('Payroll approve skipped: '.$e->getMessage());
                    }
                    $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first();
                    if ($admin) {
                        Auth::login($admin);
                    }
                }
            } elseif ($run->status === \App\Enums\PayrollRunStatus::Approved
                || $run->status === \App\Enums\PayrollRunStatus::Paid) {
                $this->command?->info('Payroll already '.$run->status->value.': '.$run->reference);
            }
        } catch (Throwable $e) {
            $this->command?->warn('Payroll lifecycle skipped: '.$e->getMessage());
        }
    }

    private function printSummary(): void
    {
        $this->command?->newLine();
        $this->command?->info('=== Demonstration scenarios ready ===');
        $this->command?->info('Site: Pearl Plaza Kampala (DEMO-PLAZA) — night gap + OT');
        $this->command?->info('Purchases: '.PurchaseInvoice::query()
            ->where('supplier_name', 'like', '%(Demo)%')
            ->pluck('supplier_invoice_no')
            ->unique()
            ->count().' demo supplier invoices');
        $this->command?->info('Manpower gaps today: '.ManpowerGap::query()->whereDate('gap_date', now())->where('original_shortage', '>', 0)->count());
        $this->command?->info('Work orders (demo titles): '.\App\Models\WorkOrder::query()->whereIn('title', [
            'Follow up desertion — Demo Deserted',
            'Pearl Plaza night coverage review',
            'Uniform top-up for Central region',
        ])->count());
        $this->command?->info('Desertions: '.\App\Models\Desertion::query()->count());
        $payroll = \App\Models\PayrollRun::query()->latest('id')->first();
        $this->command?->info('Payroll: '.($payroll ? $payroll->reference.' ('.$payroll->status->value.')' : 'none'));
        $this->command?->info('Walkthrough logins (Password@123):');
        $this->command?->info('  shifts@ / operations@ — Posting Board, Duty Roster, Manpower Coverage');
        $this->command?->info('  procurement@ — Purchases, Assets');
        $this->command?->info('  hr@ — Desertions, Work orders');
        $this->command?->info('  finance@ / md@ — Payroll & ledger');
    }
}
