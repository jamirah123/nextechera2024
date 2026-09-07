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
        if ($explicit !== null) {
            return $explicit;
        }

        if ($normalPosting === DeploymentShiftType::Rotating) {
            return ShiftType::Normal;
        }

        $normalPeriod = $normalPosting === DeploymentShiftType::Night
            ? ShiftPeriod::Night
            : ShiftPeriod::Day;

        return $workPeriod === $normalPeriod
            ? ShiftType::Normal
            : ShiftType::Overtime;
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
