<?php

namespace App\Enums;

enum ShiftPeriod: string
{
    case Day = 'day';
    case Night = 'night';

    public function label(): string
    {
        return match ($this) {
            self::Day => 'Day',
            self::Night => 'Night',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Day => 'amber',
            self::Night => 'indigo',
        };
    }

    public function defaultStartTime(): string
    {
        return match ($this) {
            self::Day => '06:00',
            self::Night => '18:00',
        };
    }

    public function defaultEndTime(): string
    {
        return match ($this) {
            self::Day => '18:00',
            self::Night => '06:00',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
