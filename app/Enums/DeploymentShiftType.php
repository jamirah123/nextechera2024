<?php

namespace App\Enums;

enum DeploymentShiftType: string
{
    case Day = 'day';
    case Night = 'night';
    case Rotating = 'rotating';

    public function label(): string
    {
        return match ($this) {
            self::Day => 'Day',
            self::Night => 'Night',
            self::Rotating => 'Rotating',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Day => 'amber',
            self::Night => 'indigo',
            self::Rotating => 'brand',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
