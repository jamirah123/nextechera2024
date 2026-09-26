<?php

namespace App\Support\Shifts;

use App\Enums\DeploymentShiftType;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftType;

class ShiftDutyTypeResolver
{
    public static function resolve(
        DeploymentShiftType $normalPosting,
        ShiftPeriod $workPeriod,
        ?ShiftType $explicit = null,
    ): ShiftType {
        if ($normalPosting === DeploymentShiftType::Rotating) {
            return $explicit ?? ShiftType::Normal;
        }

        $normalPeriod = $normalPosting === DeploymentShiftType::Night
            ? ShiftPeriod::Night
            : ShiftPeriod::Day;

        $derived = $workPeriod === $normalPeriod
            ? ShiftType::Normal
            : ShiftType::Overtime;

        // A night posting worked by day (or the reverse) is overtime.
        // An explicit Normal duty must not downgrade that.
        if ($explicit === null || $explicit === ShiftType::Normal) {
            return $derived;
        }

        return $explicit;
    }

    public static function workPeriodFor(DeploymentShiftType $posting): ShiftPeriod
    {
        return $posting === DeploymentShiftType::Night
            ? ShiftPeriod::Night
            : ShiftPeriod::Day;
    }

    public static function isOvertimeWork(DeploymentShiftType $normalPosting, ShiftPeriod $workPeriod): bool
    {
        return self::resolve($normalPosting, $workPeriod) === ShiftType::Overtime;
    }
}
