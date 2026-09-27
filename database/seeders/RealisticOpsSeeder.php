<?php

namespace Database\Seeders;

use App\Enums\AbsenceReason;
use App\Enums\CompensationType;
use App\Enums\DeploymentShiftType;
use App\Enums\LeaveType;
use App\Enums\PayrollRunStatus;
use App\Enums\SalaryChangeReason;
use App\Enums\StaffSalaryChangeType;
use App\Enums\UserRole;
use App\Models\BillingProfile;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Invoice;
use App\Models\Leave;
use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\Site;
use App\Models\Staff;
use App\Models\User;
use App\Services\AbsenceService;
use App\Services\DeploymentService;
use App\Services\Finance\PayrollRunService;
use App\Services\GuardSalaryService;
use App\Services\GuardService;
use App\Services\LeaveService;
use App\Services\Operations\OperationalPeriodService;
use App\Services\StaffSalaryService;
use App\Services\StaffService;
use App\Services\SystemSettingService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Database\Seeders\Concerns\SeedsBillingAndInvoices;
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
    use SeedsBillingAndInvoices;

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
        $this->ensurePayrollDefaults();
        $this->ensureGuardCompensation();
        $this->backdateEmploymentDates();
        $this->seedSalaryHistory();
        $this->seedStaffSalaryHistory();
        $this->rebuildPostingHistory();
        $this->seedAbsences();
        $this->seedCompletedLeave();
        $this->seedRealisticBillingProfiles($this->from->copy()->startOfMonth());
        $this->seedInvoicesFromBillingProfiles($this->from, $this->to);
        $this->seedPayrollRuns();

        Auth::logout();

        $this->printSummary();
    }

    /**
     * Platform fallback used when a guard has no monthly gross on file.
     * Admin setting payroll_default_base_shift_rate is the monthly gross (not per-shift).
     */
    private function ensurePayrollDefaults(): void
    {
        $settings = app(SystemSettingService::class);
        $current = $settings->current();
        $patch = [];

        if ((float) $current->payroll_default_base_shift_rate <= 0) {
            $patch['payroll_default_base_shift_rate'] = 170000;
        }

        if ((int) ($current->payroll_standard_shifts_per_month ?? 0) <= 0) {
            $patch['payroll_standard_shifts_per_month'] = 30;
        }

        if ((float) $current->payroll_overtime_multiplier < 1.25) {
            $patch['payroll_overtime_multiplier'] = 1.5;
        }

        if ($patch === []) {
            $settings->applyRuntimeConfig($current);

            return;
        }

        $updated = $settings->update($patch);
        $this->command?->info('Payroll defaults updated: '.collect($patch)->map(
            fn ($value, $key) => "{$key}={$value}"
        )->implode(', '));
        $settings->applyRuntimeConfig($updated);
    }

    /**
     * Shift-pay gross = Σ(shift counts × (monthlyGross / standard_shifts)).
     * Seed field guards with realistic monthly gross on guards.base_shift_rate.
     */
    private function ensureGuardCompensation(): void
    {
        $guards = app(GuardService::class);
        $staff = app(StaffService::class);
        $monthlyGross = (float) config('psg.payroll.default_monthly_gross', 170000);
        if ($monthlyGross <= 0) {
            $monthlyGross = 170000;
        }
        $updated = 0;

        Guard::query()
            ->whereDoesntHave('supervisorProfile')
            ->orderBy('employment_id')
            ->each(function (Guard $guard) use ($guards, $monthlyGross, &$updated): void {
                if ((float) $guard->base_shift_rate > 0) {
                    return;
                }

                $guards->updateGuard($guard, [
                    'compensation_type' => CompensationType::Shift->value,
                    'base_shift_rate' => $monthlyGross,
                    'bank_name' => $guard->bank_name ?: 'Centenary Bank',
                    'bank_account' => $guard->bank_account ?: '30'.str_pad((string) $guard->id, 8, '0', STR_PAD_LEFT),
                    'nssf_number' => $guard->nssf_number ?: 'NSSF'.str_pad((string) $guard->id, 6, '0', STR_PAD_LEFT),
                ], 'realistic_ops_seed_salary');
                $updated++;
            });

        // Supervisors are paid via staff.monthly_salary (synced onto the linked guard profile).
        Staff::query()
            ->whereHas('supervisorProfile')
            ->orderBy('id')
            ->each(function (Staff $member) use ($staff): void {
                if ((float) $member->monthly_salary >= 1000000) {
                    return;
                }

                $staff->updateStaff($member, [
                    'monthly_salary' => 1200000,
                ], 'realistic_ops_seed_supervisor_salary');
            });

        $this->command?->info("Guard compensation ensured ({$updated} shift guards updated).");
    }

    /**
     * Effective-dated salaries for shift guards. Payroll later in this seeder
     * reads these rows through PayrollRates and PayrollCalculationService.
     */
    private function seedSalaryHistory(): void
    {
        $salaries = app(GuardSalaryService::class);
        $actor = User::query()->where('email', 'hr@platinumsecurity.local')->first()
            ?? User::query()->where('role', UserRole::HrManager)->first()
            ?? Auth::user();

        $levels = [150000, 170000, 180000, 200000, 220000, 250000];
        $written = 0;
        $index = 0;

        Guard::query()
            ->whereDoesntHave('supervisorProfile')
            ->orderBy('employment_id')
            ->each(function (Guard $guard) use ($salaries, $actor, $levels, &$written, &$index): void {
                $revisionCount = $guard->salaryRevisions()->count();
                $hasPayslips = PayrollPayslip::query()->where('guard_id', $guard->id)->exists();

                if ($revisionCount > 1 || ($revisionCount === 1 && $hasPayslips)) {
                    return;
                }

                if ($revisionCount === 1) {
                    $guard->salaryRevisions()->delete();
                }

                if ($guard->employment_id === 'PSG0001') {
                    $salaries->recordOpening($guard, 1700000, Carbon::parse('2025-01-01'), $actor, 'Opening monthly gross.');
                    $salaries->increment($guard, 180000, Carbon::parse('2025-07-01'), SalaryChangeReason::LengthOfService, $actor, 'Length of service review.');
                    $salaries->increment($guard, 200000, Carbon::parse('2026-07-01'), SalaryChangeReason::LengthOfService, $actor, 'Length of service increment.');
                    $written++;

                    return;
                }

                $opening = $levels[$index % count($levels)];
                $employed = $guard->date_employed?->copy()->startOfDay() ?? Carbon::parse('2025-01-01');

                if ($employed->greaterThan(Carbon::parse('2025-07-01'))) {
                    $employed = Carbon::parse('2025-07-01');
                }

                $salaries->recordOpening($guard, $opening, $employed, $actor, 'Opening monthly gross.');

                if ($index % 5 === 1) {
                    $salaries->increment(
                        $guard->fresh(),
                        $opening + 10000,
                        Carbon::parse('2026-03-15'),
                        SalaryChangeReason::LengthOfService,
                        $actor,
                        'Length of service increment.',
                    );
                } elseif ($index % 5 === 2) {
                    $salaries->increment(
                        $guard->fresh(),
                        $opening + 20000,
                        Carbon::parse('2026-07-01'),
                        SalaryChangeReason::Promotion,
                        $actor,
                        'Promotion increment.',
                    );
                } elseif ($index % 5 === 3) {
                    $salaries->increment(
                        $guard->fresh(),
                        $opening + 10000,
                        Carbon::parse('2026-02-01'),
                        SalaryChangeReason::Performance,
                        $actor,
                        'Performance review.',
                    );
                    $salaries->increment(
                        $guard->fresh(),
                        $opening + 25000,
                        Carbon::parse('2026-08-01'),
                        SalaryChangeReason::ContractChange,
                        $actor,
                        'Contract change.',
                    );
                }

                $index++;
                $written++;
            });

        $this->command?->info("Salary history recorded for {$written} guards.");
    }

    /**
     * Effective-dated staff and supervisor salaries. Payroll generation later
     * in this seeder reads these rows through PayrollCalculationService.
     */
    private function seedStaffSalaryHistory(): void
    {
        $salaries = app(StaffSalaryService::class);
        $actor = User::query()->where('email', 'hr@platinumsecurity.local')->first()
            ?? User::query()->where('role', UserRole::HrManager)->first()
            ?? Auth::user();

        $story = $this->staffSalaryStorySubject();
        $demotion = Staff::query()
            ->when($story, fn ($query) => $query->whereKeyNot($story->id))
            ->whereHas('supervisorProfile')
            ->orderBy('id')
            ->first()
            ?? Staff::query()
                ->when($story, fn ($query) => $query->whereKeyNot($story->id))
                ->orderBy('id')
                ->skip(1)
                ->first();

        $written = 0;
        $index = 0;

        Staff::query()->orderBy('employment_id')->each(function (Staff $member) use ($salaries, $actor, $story, $demotion, &$written, &$index): void {
            $revisionCount = $member->salaryRevisions()->count();
            $hasPayslips = PayrollPayslip::query()->where('staff_id', $member->id)->exists();

            if ($revisionCount > 1 || ($revisionCount === 1 && $hasPayslips)) {
                return;
            }

            if ($revisionCount === 1) {
                $member->salaryRevisions()->delete();
                $member->unsetRelation('salaryRevisions');
            }

            $member = $member->fresh();
            $openingOn = $member->date_employed?->copy()->startOfDay() ?? Carbon::parse('2025-01-01');

            if ($openingOn->greaterThan(Carbon::parse('2025-07-01'))) {
                $openingOn = Carbon::parse('2025-07-01');
                $member->update(['date_employed' => $openingOn->toDateString()]);
            }

            if ($story !== null && $member->id === $story->id) {
                $member->update(['date_employed' => '2025-01-01']);
                $salaries->recordOpening($member, 800000, Carbon::parse('2025-01-01'), $actor, 'Security Officer', 'G2', 'Opening salary.');
                $salaries->change(
                    $member->fresh(),
                    1200000,
                    Carbon::parse('2026-07-01'),
                    StaffSalaryChangeType::Promotion,
                    'Promotion approved by HR Manager',
                    $actor,
                    'Operations Supervisor',
                    'G4',
                    'Promoted from Security Officer.',
                );
                $written++;

                return;
            }

            if ($demotion !== null && $member->id === $demotion->id) {
                $salaries->recordOpening($member, 1200000, $openingOn, $actor, $member->job_title ?: 'Operations Supervisor', 'G4');
                $salaries->change(
                    $member->fresh(),
                    1000000,
                    Carbon::parse('2026-09-01'),
                    StaffSalaryChangeType::Demotion,
                    'Approved management decision',
                    $actor,
                    $member->job_title ?: 'Operations Supervisor',
                    'G3',
                    'Salary reduced after an approved management decision.',
                );
                $written++;

                return;
            }

            $opening = (float) $member->monthly_salary;
            if ($opening <= 0) {
                $opening = 750000;
            }

            if ($member->supervisorProfile()->exists() && $opening < 1000000) {
                $opening = 1200000;
            }

            $title = $member->job_title ?: 'Staff';
            $bucket = $index % 6;
            $index++;

            $salaries->recordOpening($member, $opening, $openingOn, $actor, $title, $member->job_grade);

            if ($bucket === 0) {
                $written++;

                return;
            }

            if ($bucket === 1) {
                $salaries->change(
                    $member->fresh(),
                    $opening + 150000,
                    Carbon::parse('2026-06-01'),
                    StaffSalaryChangeType::Promotion,
                    'Promotion with salary increase',
                    $actor,
                    'Senior '.$title,
                    'G3',
                );
            } elseif ($bucket === 2) {
                $salaries->change(
                    $member->fresh(),
                    $opening,
                    Carbon::parse('2026-05-01'),
                    StaffSalaryChangeType::Promotion,
                    'Promotion with no salary change',
                    $actor,
                    'Senior '.$title,
                    $member->job_grade,
                );
            } elseif ($bucket === 3) {
                $salaries->change(
                    $member->fresh(),
                    max(0, $opening - 50000),
                    Carbon::parse('2026-04-01'),
                    StaffSalaryChangeType::Reduction,
                    'Annual salary review',
                    $actor,
                    $title,
                    $member->job_grade,
                );
            } elseif ($bucket === 4) {
                $salaries->change(
                    $member->fresh(),
                    $opening + 40000,
                    Carbon::parse('2026-03-15'),
                    StaffSalaryChangeType::Increment,
                    'Salary increment',
                    $actor,
                    $title,
                    $member->job_grade,
                );
            } else {
                $salaries->change(
                    $member->fresh(),
                    $opening + 20000,
                    Carbon::parse('2026-02-01'),
                    StaffSalaryChangeType::Increment,
                    'Salary increment',
                    $actor,
                    $title,
                    $member->job_grade,
                );
                $salaries->change(
                    $member->fresh(),
                    $opening + 60000,
                    Carbon::parse('2026-08-01'),
                    StaffSalaryChangeType::Other,
                    'Management-approved salary adjustment',
                    $actor,
                    $title,
                    $member->job_grade,
                    'Second change in the same year.',
                );
            }

            $written++;
        });

        $this->command?->info("Salary history recorded for {$written} staff.");
    }

    private function staffSalaryStorySubject(): ?Staff
    {
        $existing = Staff::query()->where('employment_id', 'PSG015')->first();

        if ($existing !== null) {
            return $existing;
        }

        $candidate = Staff::query()
            ->whereDoesntHave('supervisorProfile')
            ->orderBy('id')
            ->first();

        if ($candidate === null) {
            return null;
        }

        $taken = Staff::query()->where('employment_id', 'PSG015')->exists()
            || Guard::query()->where('employment_id', 'PSG015')->exists();

        if (! $taken) {
            $candidate->update(['employment_id' => 'PSG015']);
        }

        return $candidate->fresh();
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

            // Cancel prior company-wide runs for this month so salaries recalculate through services.
            PayrollRun::query()
                ->where('period_year', $year)
                ->where('period_month', $monthNo)
                ->whereNull('region_id')
                ->whereNull('site_id')
                ->where('status', '!=', PayrollRunStatus::Cancelled->value)
                ->orderBy('id')
                ->each(function (PayrollRun $existing) use ($payroll): void {
                    try {
                        $payroll->cancel($existing);
                    } catch (Throwable $e) {
                        $this->command?->warn(
                            "Could not cancel payroll {$existing->reference}: ".$e->getMessage()
                        );
                    }
                });

            try {
                $run = $payroll->createDraft([
                    'period_year' => $year,
                    'period_month' => $monthNo,
                    'notes' => 'Company payroll for '.$month->format('F Y').'.',
                ], $finance);

                $run = $payroll->calculate($run);
                $run = $payroll->submit($run, $finance);
                $run = $payroll->approve($run, $finance);

                // Mark most months paid; leave the latest closed month approved for workflow demos.
                if (! $month->isSameMonth($lastPayrollMonth)) {
                    $payroll->markPaid($run, $finance);
                }

                $this->command?->info(sprintf(
                    'Payroll %s: gross=%s net=%s (%d payslips)',
                    $month->format('Y-m'),
                    number_format((float) $run->gross_total, 0),
                    number_format((float) $run->net_total, 0),
                    $run->payslips()->count(),
                ));
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
