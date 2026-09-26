<?php

namespace Tests\Unit\Support;

use App\Enums\DeploymentShiftType;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftType;
use App\Support\Shifts\ShiftDutyTypeResolver;
use PHPUnit\Framework\TestCase;

class ShiftDutyTypeResolverTest extends TestCase
{
    public function test_night_posting_with_day_work_is_overtime(): void
    {
        $this->assertSame(
            ShiftType::Overtime,
            ShiftDutyTypeResolver::resolve(DeploymentShiftType::Night, ShiftPeriod::Day),
        );
    }

    public function test_day_posting_with_night_work_is_overtime(): void
    {
        $this->assertSame(
            ShiftType::Overtime,
            ShiftDutyTypeResolver::resolve(DeploymentShiftType::Day, ShiftPeriod::Night),
        );
    }

    public function test_explicit_normal_cannot_downgrade_cross_period_work(): void
    {
        $this->assertSame(
            ShiftType::Overtime,
            ShiftDutyTypeResolver::resolve(DeploymentShiftType::Night, ShiftPeriod::Day, ShiftType::Normal),
        );
    }

    public function test_matching_posting_and_period_is_normal(): void
    {
        $this->assertSame(
            ShiftType::Normal,
            ShiftDutyTypeResolver::resolve(DeploymentShiftType::Night, ShiftPeriod::Night),
        );
        $this->assertSame(
            ShiftType::Normal,
            ShiftDutyTypeResolver::resolve(DeploymentShiftType::Day, ShiftPeriod::Day),
        );
    }
}
