<?php

namespace App\Support\Hr;

use App\Models\Leave;
use App\Models\LeaveTypeConfig;
use Carbon\Carbon;

class LeavePayrollAdjustment
{
    /**
     * Unpaid portion of an approved leave that falls inside a payroll period.
     * Paid leave (100%) contributes nothing. Partial pay deducts only the unpaid share.
     */
    public static function unpaidAmount(Leave $leave, string $periodStart, string $periodEnd, float $monthlyGross): float
    {
        $type = $leave->leaveTypeConfig;
        if (! $type instanceof LeaveTypeConfig || $monthlyGross <= 0) {
            return 0.0;
        }

        $unpaidShare = max(0, 100 - (float) $type->pay_percent) / 100;
        if ($unpaidShare <= 0) {
            return 0.0;
        }

        $start = Carbon::parse(max($leave->start_date->toDateString(), $periodStart));
        $end = Carbon::parse(min($leave->end_date->toDateString(), $periodEnd));
        if ($end->lt($start)) {
            return 0.0;
        }

        $days = $type->chargeableDays($start, $end);
        if ($days <= 0) {
            return 0.0;
        }

        $calendarDays = max(1, Carbon::parse($periodStart)->daysInMonth);
        $daily = $monthlyGross / $calendarDays;

        return round($days * $daily * $unpaidShare, 2);
    }
}
