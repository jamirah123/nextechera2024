<?php

namespace App\Support\Finance;

use App\Models\Guard;
use App\Models\GuardSalaryRevision;
use App\Models\PayrollRun;
use App\Models\Staff;
use App\Models\StaffSalaryRevision;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PayrollRates
{
    public static function monthlyGross(Guard $guard): float
    {
        $salary = (float) $guard->base_shift_rate;

        if ($salary > 0) {
            return $salary;
        }

        return (float) config('psg.payroll.default_monthly_gross', 0);
    }

    public static function periodDays(PayrollRun $run): int
    {
        return self::calendarDays($run);
    }

    public static function calendarDays(PayrollRun $run): int
    {
        return max(1, $run->period_start->diffInDays($run->period_end) + 1);
    }

    /**
     * Divisor for converting monthly gross into a per-shift rate (the configured salary basis).
     *
     * Shift-pay never uses calendar days in the month (28/29/30/31). Gross pay is always:
     * payable recorded shifts × (monthly gross ÷ this basis).
     */
    public static function rateDivisor(?PayrollRun $run = null): int
    {
        $standard = (int) config('psg.payroll.standard_shifts_per_month', 0);

        if ($standard > 0) {
            return $standard;
        }

        // Admin unset / zero → company default basis (not days-in-month).
        return 30;
    }

    public static function dailyRateFromMonthly(float $monthlyGross, ?PayrollRun $run = null): float
    {
        if ($monthlyGross <= 0) {
            return 0.0;
        }

        return round($monthlyGross / self::rateDivisor($run), 2);
    }

    /** Earnings per completed normal (or equivalent) payable shift. */
    public static function perShiftRate(Guard $guard, ?PayrollRun $run = null): float
    {
        return self::dailyRateFromMonthly(self::monthlyGross($guard), $run);
    }

    public static function baseShiftRate(Guard $guard, ?PayrollRun $run = null): float
    {
        return self::perShiftRate($guard, $run);
    }

    public static function overtimeShiftRate(Guard $guard, ?PayrollRun $run = null): float
    {
        $overtime = (float) $guard->overtime_shift_rate;

        if ($overtime > 0) {
            return $overtime;
        }

        return round(self::perShiftRate($guard, $run) * (float) config('psg.payroll.overtime_multiplier', 1.5), 2);
    }

    /**
     * Overtime rate for fixed-salary staff/supervisors from monthly salary + configured rules.
     * Does not permanently change salary — used only when overtime shifts are recorded.
     */
    public static function salaryOvertimeShiftRate(float $monthlyGross, ?Guard $guard = null, ?PayrollRun $run = null): float
    {
        if ($guard !== null) {
            $explicit = (float) $guard->overtime_shift_rate;

            if ($explicit > 0) {
                return $explicit;
            }
        }

        $daily = self::dailyRateFromMonthly($monthlyGross, $run);

        return round($daily * (float) config('psg.payroll.overtime_multiplier', 1.5), 2);
    }

    /**
     * Monthly gross in force on a date.
     * Guards without salary history keep using guards.base_shift_rate (or the company default).
     */
    public static function salaryOn(Guard $guard, CarbonInterface $date): float
    {
        return self::salaryFromRevisions(self::loadedRevisions($guard), $date, $guard);
    }

    /**
     * Non-overlapping salary slices covering the inclusive date window.
     *
     * @return list<array{from: string, to: string, monthly: float}>
     */
    public static function segments(Guard $guard, CarbonInterface $start, CarbonInterface $end): array
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();

        if ($start->greaterThan($end)) {
            return [];
        }

        $revisions = self::loadedRevisions($guard);

        if ($revisions->isEmpty()) {
            return [[
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
                'monthly' => self::monthlyGross($guard),
            ]];
        }

        $points = collect([$start]);

        foreach ($revisions as $revision) {
            $from = $revision->effective_from->copy()->startOfDay();

            if ($from->greaterThan($start) && $from->lessThanOrEqualTo($end)) {
                $points->push($from);
            }
        }

        $points = $points
            ->unique(fn (CarbonInterface $day) => $day->toDateString())
            ->sortBy(fn (CarbonInterface $day) => $day->toDateString())
            ->values();

        $segments = [];

        foreach ($points as $index => $point) {
            $segStart = $point->copy()->startOfDay();
            $segEnd = isset($points[$index + 1])
                ? $points[$index + 1]->copy()->startOfDay()->subDay()
                : $end->copy();

            if ($segEnd->greaterThan($end)) {
                $segEnd = $end->copy();
            }

            if ($segStart->greaterThan($segEnd)) {
                continue;
            }

            $segments[] = [
                'from' => $segStart->toDateString(),
                'to' => $segEnd->toDateString(),
                'monthly' => self::salaryFromRevisions($revisions, $segStart, $guard),
            ];
        }

        return $segments;
    }

    /**
     * Employment-clipped salary slices for a payroll run, with calendar-day proration.
     *
     * @return list<array{from: string, to: string, monthly: float, days: int, amount: float}>
     */
    public static function employmentSegments(Guard $guard, PayrollRun $run): array
    {
        $start = self::effectiveShiftStart($guard, $run);
        $end = self::effectiveShiftEnd($guard, $run);

        if ($start->greaterThan($end)) {
            return [];
        }

        $calendar = self::calendarDays($run);
        $segments = self::segments($guard, $start, $end);

        foreach ($segments as &$segment) {
            $from = Carbon::parse($segment['from'])->startOfDay();
            $to = Carbon::parse($segment['to'])->startOfDay();
            $days = (int) $from->diffInDays($to) + 1;
            $segment['days'] = $days;
            $segment['amount'] = round(((float) $segment['monthly']) * ($days / $calendar), 2);
        }
        unset($segment);

        return $segments;
    }

    /** Fixed monthly gross pro-rated for mid-period joiners, leavers, and mid-period salary changes. */
    public static function fixedPeriodGross(Guard $guard, PayrollRun $run): float
    {
        $segments = self::employmentSegments($guard, $run);

        if ($segments === []) {
            return 0.0;
        }

        return round(array_sum(array_column($segments, 'amount')), 2);
    }

    /**
     * @param  Collection<int, GuardSalaryRevision>  $revisions
     */
    private static function salaryFromRevisions(Collection $revisions, CarbonInterface $date, Guard $guard): float
    {
        if ($revisions->isEmpty()) {
            return self::monthlyGross($guard);
        }

        $day = $date->copy()->startOfDay();
        $covering = $revisions
            ->sortByDesc(fn (GuardSalaryRevision $revision) => $revision->effective_from->toDateString())
            ->first(function (GuardSalaryRevision $revision) use ($day) {
                $from = $revision->effective_from->copy()->startOfDay();
                $to = $revision->effective_to?->copy()->startOfDay();

                return $from->lessThanOrEqualTo($day) && ($to === null || $to->greaterThanOrEqualTo($day));
            });

        if ($covering !== null) {
            return (float) $covering->salary;
        }

        $earliest = $revisions->sortBy(fn (GuardSalaryRevision $revision) => $revision->effective_from->toDateString())->first();

        if ($earliest !== null && $day->lessThan($earliest->effective_from->copy()->startOfDay())) {
            return $earliest->previous_salary !== null
                ? (float) $earliest->previous_salary
                : (float) $earliest->salary;
        }

        $latest = $revisions->sortByDesc(fn (GuardSalaryRevision $revision) => $revision->effective_from->toDateString())->first();

        return $latest !== null ? (float) $latest->salary : self::monthlyGross($guard);
    }

    /**
     * @return Collection<int, GuardSalaryRevision>
     */
    private static function loadedRevisions(Guard $guard): Collection
    {
        $revisions = $guard->relationLoaded('salaryRevisions')
            ? $guard->salaryRevisions
            : $guard->salaryRevisions()->get();

        return $revisions->sortBy(fn (GuardSalaryRevision $revision) => $revision->effective_from->toDateString())->values();
    }

    public static function guardEligibleDays(Guard $guard, PayrollRun $run): int
    {
        return self::eligibleDaysInPeriod(
            $run->period_start,
            $run->period_end,
            $guard->date_employed,
            $guard->employment_end_date,
        );
    }

    /**
     * First duty date in the run that may count for this guard (employment start clipped to period).
     */
    public static function effectiveShiftStart(Guard $guard, PayrollRun $run): CarbonInterface
    {
        $periodStart = $run->period_start->copy()->startOfDay();

        if ($guard->date_employed !== null && $guard->date_employed->greaterThan($periodStart)) {
            return $guard->date_employed->copy()->startOfDay();
        }

        return $periodStart;
    }

    /**
     * Last duty date in the run that may count for this guard (employment end clipped to period).
     */
    public static function effectiveShiftEnd(Guard $guard, PayrollRun $run): CarbonInterface
    {
        $periodEnd = $run->period_end->copy()->startOfDay();

        if ($guard->employment_end_date !== null && $guard->employment_end_date->lessThan($periodEnd)) {
            $periodEnd = $guard->employment_end_date->copy()->startOfDay();
        }

        if ($guard->guard_pay_until !== null && $guard->guard_pay_until->lessThan($periodEnd)) {
            return $guard->guard_pay_until->copy()->startOfDay();
        }

        return $periodEnd;
    }

    public static function staffMonthlyGross(Staff $staff): float
    {
        return max(0, (float) $staff->monthly_salary);
    }

    public static function staffSalaryOn(Staff $staff, CarbonInterface $date): float
    {
        return self::staffSalaryFromRevisions(self::loadedStaffRevisions($staff), $date, $staff);
    }

    /**
     * @return list<array{from: string, to: string, monthly: float}>
     */
    public static function staffSegments(Staff $staff, CarbonInterface $start, CarbonInterface $end): array
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();

        if ($start->greaterThan($end)) {
            return [];
        }

        $revisions = self::loadedStaffRevisions($staff);

        if ($revisions->isEmpty()) {
            return [[
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
                'monthly' => self::staffMonthlyGross($staff),
            ]];
        }

        $points = collect([$start]);

        foreach ($revisions as $revision) {
            $from = $revision->effective_from->copy()->startOfDay();

            if ($from->greaterThan($start) && $from->lessThanOrEqualTo($end)) {
                $points->push($from);
            }
        }

        $points = $points
            ->unique(fn (CarbonInterface $day) => $day->toDateString())
            ->sortBy(fn (CarbonInterface $day) => $day->toDateString())
            ->values();

        $segments = [];

        foreach ($points as $index => $point) {
            $segStart = $point->copy()->startOfDay();
            $segEnd = isset($points[$index + 1])
                ? $points[$index + 1]->copy()->startOfDay()->subDay()
                : $end->copy();

            if ($segEnd->greaterThan($end)) {
                $segEnd = $end->copy();
            }

            if ($segStart->greaterThan($segEnd)) {
                continue;
            }

            $segments[] = [
                'from' => $segStart->toDateString(),
                'to' => $segEnd->toDateString(),
                'monthly' => self::staffSalaryFromRevisions($revisions, $segStart, $staff),
            ];
        }

        return $segments;
    }

    /**
     * @return list<array{from: string, to: string, monthly: float, days: int, amount: float}>
     */
    public static function staffEmploymentSegments(Staff $staff, PayrollRun $run): array
    {
        $start = $run->period_start->copy()->startOfDay();
        $end = $run->period_end->copy()->startOfDay();

        if ($staff->date_employed !== null && $staff->date_employed->greaterThan($start)) {
            $start = $staff->date_employed->copy()->startOfDay();
        }

        if ($staff->compensation_from !== null && $staff->compensation_from->greaterThan($start)) {
            $start = $staff->compensation_from->copy()->startOfDay();
        }

        if ($staff->employment_end_date !== null && $staff->employment_end_date->lessThan($end)) {
            $end = $staff->employment_end_date->copy()->startOfDay();
        }

        if ($start->greaterThan($end)) {
            return [];
        }

        $calendar = self::calendarDays($run);
        $segments = self::staffSegments($staff, $start, $end);

        foreach ($segments as &$segment) {
            $from = Carbon::parse($segment['from'])->startOfDay();
            $to = Carbon::parse($segment['to'])->startOfDay();
            $days = (int) $from->diffInDays($to) + 1;
            $segment['days'] = $days;
            $segment['amount'] = round(((float) $segment['monthly']) * ($days / $calendar), 2);
        }
        unset($segment);

        return $segments;
    }

    public static function staffPeriodGross(Staff $staff, PayrollRun $run): float
    {
        $segments = self::staffEmploymentSegments($staff, $run);

        if ($segments === []) {
            return 0.0;
        }

        return round(array_sum(array_column($segments, 'amount')), 2);
    }

    /**
     * @param  Collection<int, StaffSalaryRevision>  $revisions
     */
    private static function staffSalaryFromRevisions(Collection $revisions, CarbonInterface $date, Staff $staff): float
    {
        if ($revisions->isEmpty()) {
            return self::staffMonthlyGross($staff);
        }

        $day = $date->copy()->startOfDay();
        $covering = $revisions
            ->sortByDesc(fn (StaffSalaryRevision $revision) => $revision->effective_from->toDateString())
            ->first(function (StaffSalaryRevision $revision) use ($day) {
                $from = $revision->effective_from->copy()->startOfDay();
                $to = $revision->effective_to?->copy()->startOfDay();

                return $from->lessThanOrEqualTo($day) && ($to === null || $to->greaterThanOrEqualTo($day));
            });

        if ($covering !== null) {
            return (float) $covering->salary;
        }

        $earliest = $revisions->sortBy(fn (StaffSalaryRevision $revision) => $revision->effective_from->toDateString())->first();

        if ($earliest !== null && $day->lessThan($earliest->effective_from->copy()->startOfDay())) {
            return $earliest->previous_salary !== null
                ? (float) $earliest->previous_salary
                : (float) $earliest->salary;
        }

        $latest = $revisions->sortByDesc(fn (StaffSalaryRevision $revision) => $revision->effective_from->toDateString())->first();

        return $latest !== null ? (float) $latest->salary : self::staffMonthlyGross($staff);
    }

    /**
     * @return Collection<int, StaffSalaryRevision>
     */
    private static function loadedStaffRevisions(Staff $staff): Collection
    {
        $revisions = $staff->relationLoaded('salaryRevisions')
            ? $staff->salaryRevisions
            : $staff->salaryRevisions()->get();

        return $revisions->sortBy(fn (StaffSalaryRevision $revision) => $revision->effective_from->toDateString())->values();
    }

    public static function staffEligibleDays(Staff $staff, PayrollRun $run): int
    {
        $employed = $staff->date_employed;

        if ($staff->compensation_from !== null && ($employed === null || $staff->compensation_from->greaterThan($employed))) {
            $employed = $staff->compensation_from;
        }

        return self::eligibleDaysInPeriod(
            $run->period_start,
            $run->period_end,
            $employed,
            $staff->employment_end_date,
        );
    }

    public static function eligibleDaysInPeriod(
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        ?CarbonInterface $dateEmployed,
        ?CarbonInterface $employmentEndDate,
    ): int {
        $start = $periodStart->copy()->startOfDay();
        $end = $periodEnd->copy()->startOfDay();

        $eligibleStart = $start;

        if ($dateEmployed !== null && $dateEmployed->greaterThan($start)) {
            $eligibleStart = $dateEmployed->copy()->startOfDay();
        }

        $eligibleEnd = $end;

        if ($employmentEndDate !== null && $employmentEndDate->lessThan($end)) {
            $eligibleEnd = $employmentEndDate->copy()->startOfDay();
        }

        if ($eligibleStart->greaterThan($eligibleEnd)) {
            return 0;
        }

        return $eligibleStart->diffInDays($eligibleEnd) + 1;
    }
}
