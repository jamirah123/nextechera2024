<?php

namespace App\Support\Finance;

use App\Models\Guard;
use App\Models\PayrollRun;
use App\Models\Staff;
use Carbon\CarbonInterface;

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

    /** Fixed monthly gross pro-rated for mid-period joiners and leavers (calendar employment window). */
    public static function fixedPeriodGross(Guard $guard, PayrollRun $run): float
    {
        $monthly = self::monthlyGross($guard);

        if ($monthly <= 0) {
            return 0;
        }

        $eligibleDays = self::guardEligibleDays($guard, $run);

        if ($eligibleDays <= 0) {
            return 0;
        }

        return round($monthly * ($eligibleDays / self::calendarDays($run)), 2);
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
            return $guard->employment_end_date->copy()->startOfDay();
        }

        return $periodEnd;
    }

    public static function staffMonthlyGross(Staff $staff): float
    {
        return max(0, (float) $staff->monthly_salary);
    }

    public static function staffPeriodGross(Staff $staff, PayrollRun $run): float
    {
        $monthly = self::staffMonthlyGross($staff);

        if ($monthly <= 0) {
            return 0;
        }

        $eligibleDays = self::staffEligibleDays($staff, $run);

        if ($eligibleDays <= 0) {
            return 0;
        }

        return round($monthly * ($eligibleDays / self::calendarDays($run)), 2);
    }

    public static function staffEligibleDays(Staff $staff, PayrollRun $run): int
    {
        return self::eligibleDaysInPeriod(
            $run->period_start,
            $run->period_end,
            $staff->date_employed,
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
