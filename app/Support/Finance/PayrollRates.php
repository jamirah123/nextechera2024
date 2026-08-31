<?php

namespace App\Support\Finance;

use App\Models\Guard;
use App\Models\PayrollRun;

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
        return max(1, $run->period_start->diffInDays($run->period_end) + 1);
    }

    /** Earnings per completed normal (or equivalent) shift. */
    public static function perShiftRate(Guard $guard, ?PayrollRun $run = null): float
    {
        $divisor = $run !== null
            ? self::periodDays($run)
            : max(1, (int) now()->daysInMonth);

        return round(self::monthlyGross($guard) / $divisor, 2);
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
}
