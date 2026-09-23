<?php

namespace App\Support\Supervisors;

use App\Enums\DeploymentShiftType;
use App\Enums\ShiftType;

/**
 * Classifies supervisor manpower-shortage cover as Normal (fixed salary) or Overtime.
 *
 * Day cover within the supervisor's configured normal working hours → Normal Supervisor Shift.
 * Night cover, rotating cover, or any cover outside normal hours → Supervisor Overtime.
 */
class SupervisorCoverageClassifier
{
    public static function classify(DeploymentShiftType $shiftType): ShiftType
    {
        return match ($shiftType) {
            DeploymentShiftType::Day => self::dayIsWithinNormalHours()
                ? ShiftType::Normal
                : ShiftType::Overtime,
            DeploymentShiftType::Night, DeploymentShiftType::Rotating => ShiftType::Overtime,
        };
    }

    public static function label(ShiftType $dutyType): string
    {
        return $dutyType === ShiftType::Overtime
            ? 'Supervisor Overtime'
            : 'Normal Supervisor Shift';
    }

    public static function payrollHint(ShiftType $dutyType): string
    {
        return $dutyType === ShiftType::Overtime
            ? 'OT payable (subject to overtime rules)'
            : 'Fixed salary — no OT';
    }

    /**
     * Day posting maps to Normal when the configured day window sits inside
     * (or matches) the supervisor normal working-hours window.
     */
    public static function dayIsWithinNormalHours(): bool
    {
        $dayStart = self::minutes((string) config('psg.shift_defaults.day.start', '06:00'));
        $dayEnd = self::minutes((string) config('psg.shift_defaults.day.end', '18:00'));
        $normalStart = self::minutes((string) config('psg.supervisor_coverage.normal_start', '06:00'));
        $normalEnd = self::minutes((string) config('psg.supervisor_coverage.normal_end', '19:00'));

        // Overnight normal windows are not supported for "day" classification.
        if ($normalEnd <= $normalStart || $dayEnd <= $dayStart) {
            return false;
        }

        return $dayStart >= $normalStart && $dayEnd <= $normalEnd;
    }

    private static function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return ($hour * 60) + $minute;
    }
}
