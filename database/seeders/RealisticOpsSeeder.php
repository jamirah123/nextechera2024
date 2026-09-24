<?php

namespace Database\Seeders;

use App\Enums\AbsenceReason;
use App\Enums\BillingMode;
use App\Enums\DeploymentShiftType;
use App\Enums\LeaveType;
use App\Enums\PaymentMethod;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Invoice;
use App\Models\Leave;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\Site;
use App\Models\Staff;
use App\Models\User;
use App\Services\AbsenceService;
use App\Services\DeploymentService;
use App\Services\Finance\BillingService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\PayrollRunService;
use App\Services\GuardService;
use App\Services\LeaveService;
use App\Services\Operations\OperationalPeriodService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Seeds realistic operational history from a start date through "today"
 * by calling the same domain services used by live controllers.
 *
 * Does not invent orphan rows — deployments, duties, HR, invoices, and payroll
 * all flow through validations, audits, and relationship rules.
 */
class RealisticOpsSeeder extends Seeder
{
    private Carbon $from;

    private Carbon $to;

    private DeploymentService $deployments;

    private OperationalPeriodService $periods;

    public function run(?string $from = null, ?string $to = null): void
    {
        $this->from = Carbon::parse($from ?: '2026-01-01')->startOfDay();
        $this->to = Carbon::parse($to ?: now()->toDateString())->startOfDay();

        if ($this->to->lt($this->from)) {
            throw new \InvalidArgumentException('Seed end date must be on or after the start date.');
        }

        if (Site::query()->count() === 0 || Guard::query()->count() === 0) {
            $this->command?->info('Base organisation missing — running SmallCompanySeeder first…');
            $this->call(SmallCompanySeeder::class);
        }

        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first()
            ?? User::query()->orderBy('id')->first();

        if ($admin === null) {
            throw new \RuntimeException('No admin user available for realistic ops seeding.');
        }

        Auth::login($admin);

        $this->deployments = app(DeploymentService::class);
        $this->periods = app(OperationalPeriodService::class);

        $this->command?->info(sprintf(
            'Seeding realistic ops %s → %s via domain services…',
            $this->from->toDateString(),
            $this->to->toDateString(),
        ));

        $this->ensureOperationalPeriodsOpen();
        $this->backdateEmploymentDates();
        $this->rebuildPostingHistory();
        $this->seedAbsences();
        $this->seedCompletedLeave();
        $this->seedBillingProfiles();
        $this->seedInvoicesAndPayments();
        $this->seedPayrollRuns();

        Auth::logout();

        $this->printSummary();
    }

    private function ensureOperationalPeriodsOpen(): void
    {
        foreach (CarbonPeriod::create($this->from->copy()->startOfMonth(), '1 month', $this->to->copy()->startOfMonth()) as $month) {
            $this->periods->ensureForDate($month->toDateString());
        }
    }

    private function backdateEmploymentDates(): void
    {
        $guards = app(GuardService::class);
        $employedFrom = $this->from->copy()->subMonths(6)->toDateString();

        Guard::query()->orderBy('id')->each(function (Guard $guard) use ($guards, $employedFrom): void {
            if ($guard->date_employed !== null && $guard->date_employed->gte($this->from)) {
                $guards->updateGuard($guard, [
                    'date_employed' => $employedFrom,
                ], 'realistic_ops_seed_backdate');
            }
        });

        Staff::query()->whereDate('date_employed', '>=', $this->from->toDateString())
            ->update(['date_employed' => $employedFrom]);
    }

    private function rebuildPostingHistory(): void
    {
        $plan = $this->postingPlan();

        if ($plan === []) {
            $this->command?->warn('No posting plan could be built — skipping duty history.');

            return;
        }

        $guards = app(GuardService::class);

        // End lean "today-only" seed postings so history can be rebuilt through services.
        Deployment::query()->current()->orderBy('id')->each(function (Deployment $deployment): void {
            try {
                $this->deployments->end(
                    $deployment,
                    $this->to->toDateString(),
                    'Ended so realistic ops history can be rebuilt through normal posting workflows.',
                );
            } catch (Throwable $e) {
                $this->command?->warn('Could not end deployment #'.$deployment->id.': '.$e->getMessage());
            }
        });

        foreach ($plan as [$guard, $site, $shiftType]) {
            $guard = $guard->fresh();

            // Ensure the guard is deployable after ending prior postings / prior seed runs.
            try {
                $guards->updateGuard($guard, [
                    'operational_status' => \App\Enums\OperationalStatus::AwaitingDeployment->value,
                    'current_site_id' => null,
                    'current_supervisor_id' => null,
                ], 'realistic_ops_seed_reset');
            } catch (Throwable) {
                // Continue — deploy() will surface a clear error if still blocked.
            }

            $this->seedGuardPostingHistory($guard->fresh(), $site->fresh(), $shiftType);
        }
    }

    /**
     * @return list<array{0: Guard, 1: Site, 2: DeploymentShiftType}>
     */
    private function postingPlan(): array
    {
        $sites = Site::query()->with('region')->orderBy('id')->get();
        $guards = Guard::query()->orderBy('employment_id')->get()->groupBy('region_id');

        if ($sites->count() < 2) {
            return [];
        }

        $plan = [];

        foreach ($sites->groupBy('region_id') as $regionId => $regionSites) {
            $regionGuards = ($guards->get($regionId) ?? collect())->values();
            if ($regionGuards->count() < 6 || $regionSites->count() < 2) {
                continue;
            }

            [$siteA, $siteB] = [$regionSites[0], $regionSites[1]];

            $plan[] = [$regionGuards[0], $siteA, DeploymentShiftType::Day];
            $plan[] = [$regionGuards[1], $siteA, DeploymentShiftType::Day];
            $plan[] = [$regionGuards[2], $siteA, DeploymentShiftType::Night];
            $plan[] = [$regionGuards[3], $siteB, DeploymentShiftType::Day];
            $plan[] = [$regionGuards[4], $siteB, DeploymentShiftType::Night];
            $plan[] = [$regionGuards[5], $siteB, DeploymentShiftType::Night];
        }

        return $plan;
    }

    private function seedGuardPostingHistory(Guard $guard, Site $site, DeploymentShiftType $shiftType): void
    {
        $currentStart = $this->to->copy()->startOfMonth()->max($this->from);

        // One active posting that covers today (same path as the posting UI).
        try {
            $deployment = $this->deployments->deploy([
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'shift_type' => $shiftType->value,
                'start_date' => $currentStart->toDateString(),
                'duty_date_to' => $this->to->toDateString(),
                'notes' => 'Realistic ops seed — current posting through '.$this->to->toDateString().'.',
                'correction_reason' => 'Realistic operational history seed',
            ]);
        } catch (Throwable $e) {
            $this->command?->warn(
                "Current posting failed for {$guard->employment_id} @ {$site->code}: ".$e->getMessage()
            );

            $deployment = Deployment::query()
                ->current()
                ->where('guard_id', $guard->id)
                ->where('site_id', $site->id)
                ->first();

            if ($deployment === null) {
                return;
            }
        }

        // Backfill earlier months as duty records on that posting (≤31 days per service call).
        $cursor = $this->from->copy();
        $historyEnd = $currentStart->copy()->subDay();

        while ($cursor->lte($historyEnd)) {
            $windowEnd = $cursor->copy()->addDays(30)->min($historyEnd);

            try {
                $this->deployments->recordDutiesForPosting(
                    $guard->fresh(),
                    $site->fresh(),
                    [
                        'start_date' => $cursor->toDateString(),
                        'duty_date_to' => $windowEnd->toDateString(),
                        'deployment_id' => $deployment->id,
                        'notes' => 'Realistic ops seed — historical duties '.$cursor->format('M Y').'.',
                        'correction_reason' => 'Realistic operational history seed',
                    ],
                    $shiftType,
                    $shiftType,
                );
            } catch (Throwable $e) {
                $this->command?->warn(
                    "Duty backfill failed for {$guard->employment_id} {$cursor->toDateString()}–{$windowEnd->toDateString()}: ".$e->getMessage()
                );
            }

            $cursor = $windowEnd->copy()->addDay();
        }
    }

    private function seedAbsences(): void
    {
        $absences = app(AbsenceService::class);
        $candidates = Shift::query()
            ->whereDate('shift_date', '>=', $this->from->toDateString())
            ->whereDate('shift_date', '<=', $this->to->copy()->subDays(3)->toDateString())
            ->whereDate('shift_date', '<', now()->toDateString())
            ->orderBy('shift_date')
            ->limit(40)
            ->get();

        if ($candidates->isEmpty()) {
            return;
        }

        $picked = $candidates->random(min(8, $candidates->count()));
        $reasons = AbsenceReason::cases();

        foreach ($picked as $index => $shift) {
            try {
                $absences->record([
                    'guard_id' => $shift->guard_id,
                    'shift_id' => $shift->id,
                    'site_id' => $shift->site_id,
                    'absence_date' => $shift->shift_date->toDateString(),
                    'reason' => $reasons[$index % count($reasons)]->value,
                    'action_taken' => 'Recorded during realistic ops seed — follow-up with supervisor.',
                    'notes' => 'Realistic operational history seed',
                    'replacement_required' => false,
                ]);
            } catch (Throwable $e) {
                // Skip duplicates / board edge cases.
            }
        }
    }

    private function seedCompletedLeave(): void
    {
        $leaves = app(LeaveService::class);
        $guard = Guard::query()->orderBy('employment_id')->skip(2)->first();

        if ($guard === null) {
            return;
        }

        $start = $this->from->copy()->addMonths(2)->startOfMonth()->addDays(3);
        $end = $start->copy()->addDays(4);

        if ($end->gte($this->to) || $end->gte(now()->startOfDay())) {
            return;
        }

        try {
            $leave = $leaves->create([
                'guard_id' => $guard->id,
                'leave_type' => LeaveType::Annual->value,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'expected_return_date' => $end->copy()->addDay()->toDateString(),
                'reason' => 'Annual leave — realistic ops seed',
                'notes' => 'Requested via normal leave workflow during history seed.',
            ]);

            $leave = $leaves->approve($leave, 'Approved for realistic ops history.');
            $leaves->complete($leave);
        } catch (Throwable $e) {
            $this->command?->warn('Leave seed skipped: '.$e->getMessage());
        }
    }

    private function seedBillingProfiles(): void
    {
        $billing = app(BillingService::class);

        Site::query()->with('client')->orderBy('id')->each(function (Site $site) use ($billing): void {
            if ($site->client_id === null) {
                return;
            }

            $exists = BillingProfile::query()
                ->where('client_id', $site->client_id)
                ->where('site_id', $site->id)
                ->where('is_active', true)
                ->exists();

            if ($exists) {
                return;
            }

            try {
                $billing->create([
                    'client_id' => $site->client_id,
                    'site_id' => $site->id,
                    'billing_mode' => BillingMode::Monthly->value,
                    'monthly_rate_per_unarmed_guard' => 450_000,
                    'monthly_rate_per_armed_guard' => 650_000,
                    'monthly_rate_per_unarmed_day_guard' => 450_000,
                    'monthly_rate_per_unarmed_night_guard' => 480_000,
                    'monthly_rate_per_armed_day_guard' => 650_000,
                    'monthly_rate_per_armed_night_guard' => 700_000,
                    'effective_from' => $this->from->toDateString(),
                    'is_active' => true,
                ]);
            } catch (Throwable $e) {
                $this->command?->warn('Billing profile skipped for site '.$site->code.': '.$e->getMessage());
            }
        });
    }

    private function seedInvoicesAndPayments(): void
    {
        $invoices = app(InvoiceService::class);
        $payments = app(PaymentService::class);
        $finance = User::query()->where('email', 'finance@platinumsecurity.local')->first() ?? Auth::user();

        $month = $this->from->copy()->startOfMonth();
        $lastInvoiceMonth = $this->to->copy()->startOfMonth()->subMonth();

        while ($month->lte($lastInvoiceMonth)) {
            $periodStart = $month->copy()->startOfMonth()->toDateString();
            $periodEnd = $month->copy()->endOfMonth()->toDateString();

            Client::query()->orderBy('id')->each(function (Client $client) use (
                $invoices,
                $payments,
                $finance,
                $periodStart,
                $periodEnd,
                $month,
            ): void {
                $already = Invoice::query()
                    ->where('client_id', $client->id)
                    ->whereDate('period_start', $periodStart)
                    ->whereDate('period_end', $periodEnd)
                    ->exists();

                if ($already) {
                    return;
                }

                try {
                    $invoice = $invoices->createDraft([
                        'client_id' => $client->id,
                        'period_start' => $periodStart,
                        'period_end' => $periodEnd,
                        'auto_generate' => true,
                        'notes' => 'Realistic ops seed invoice for '.$month->format('F Y').'.',
                    ]);

                    if ((float) $invoice->total <= 0) {
                        return;
                    }

                    $invoice = $invoices->issue($invoice);

                    // Pay most months in full; leave one older month open for AR dashboards.
                    if ((int) $month->month !== 2) {
                        $invoice = $invoice->fresh();
                        $payments->record([
                            'invoice_id' => $invoice->id,
                            'amount' => (float) ($invoice->balance > 0 ? $invoice->balance : $invoice->total),
                            'payment_date' => Carbon::parse($periodEnd)->addDays(7)->min($this->to)->toDateString(),
                            'method' => PaymentMethod::BankTransfer->value,
                            'external_reference' => 'SEED-'.$invoice->reference,
                            'notes' => 'Realistic ops seed payment',
                        ]);
                    }
                } catch (Throwable $e) {
                    $this->command?->warn(
                        "Invoice seed skipped for client #{$client->id} {$month->format('Y-m')}: ".$e->getMessage()
                    );
                }
            });

            $month->addMonth();
        }
    }

    private function seedPayrollRuns(): void
    {
        $payroll = app(PayrollRunService::class);
        $finance = User::query()->where('email', 'finance@platinumsecurity.local')->first() ?? Auth::user();

        $month = $this->from->copy()->startOfMonth();
        $lastPayrollMonth = $this->to->copy()->startOfMonth()->subMonth();

        while ($month->lte($lastPayrollMonth)) {
            $year = (int) $month->year;
            $monthNo = (int) $month->month;

            if (! PayrollRunService::isPeriodClosed($year, $monthNo)) {
                $month->addMonth();

                continue;
            }

            $exists = PayrollRun::query()
                ->where('period_year', $year)
                ->where('period_month', $monthNo)
                ->whereNull('region_id')
                ->whereNull('site_id')
                ->exists();

            if ($exists) {
                $month->addMonth();

                continue;
            }

            try {
                $run = $payroll->createDraft([
                    'period_year' => $year,
                    'period_month' => $monthNo,
                    'notes' => 'Realistic ops seed payroll '.$month->format('F Y').'.',
                ], $finance);

                $run = $payroll->calculate($run);
                $run = $payroll->submit($run, $finance);
                $run = $payroll->approve($run, $finance);

                // Mark most months paid; leave the latest closed month approved for workflow demos.
                if (! $month->isSameMonth($lastPayrollMonth)) {
                    $payroll->markPaid($run, $finance);
                }
            } catch (Throwable $e) {
                $this->command?->warn('Payroll seed skipped for '.$month->format('Y-m').': '.$e->getMessage());
            }

            $month->addMonth();
        }
    }

    private function printSummary(): void
    {
        $this->command?->newLine();
        $this->command?->info('=== Realistic ops seed complete ===');
        foreach ([
            'Shifts' => Shift::query()->count(),
            'Deployments (all)' => Deployment::query()->count(),
            'Current deployments' => Deployment::query()->current()->count(),
            'Absences' => \App\Models\Absence::query()->count(),
            'Leaves' => Leave::query()->count(),
            'Billing profiles' => BillingProfile::query()->count(),
            'Invoices' => Invoice::query()->count(),
            'Payroll runs' => PayrollRun::query()->count(),
            'Audit logs' => \App\Models\AuditLog::query()->count(),
        ] as $label => $count) {
            $this->command?->info("{$label}: {$count}");
        }
        $this->command?->info('Range: '.$this->from->toDateString().' → '.$this->to->toDateString());
    }
}
