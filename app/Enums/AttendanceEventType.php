<?php

namespace App\Enums;

enum AttendanceEventType: string
{
    case CheckIn = 'check_in';
    case CheckOut = 'check_out';
    case OnDuty = 'on_duty';
    case OffDuty = 'off_duty';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::CheckIn => 'Check in',
            self::CheckOut => 'Check out',
            self::OnDuty => 'On duty',
            self::OffDuty => 'Off duty',
            self::Manual => 'Manual update',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::CheckIn, self::OnDuty => 'emerald',
            self::CheckOut, self::OffDuty => 'slate',
            self::Manual => 'brand',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
