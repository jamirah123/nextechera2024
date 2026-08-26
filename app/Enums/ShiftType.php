<?php

namespace App\Enums;

enum ShiftType: string
{
    case Normal = 'normal';
    case Overtime = 'overtime';
    case Relief = 'relief';
    case Replacement = 'replacement';
    case SpecialDuty = 'special_duty';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Overtime => 'Overtime',
            self::Relief => 'Relief',
            self::Replacement => 'Replacement',
            self::SpecialDuty => 'Special Duty',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Normal => 'brand',
            self::Overtime => 'amber',
            self::Relief => 'sky',
            self::Replacement => 'indigo',
            self::SpecialDuty => 'violet',
        };
    }

    public function countsAsWorked(): bool
    {
        return in_array($this, [
            self::Normal,
            self::Overtime,
            self::Relief,
            self::Replacement,
            self::SpecialDuty,
        ], true);
    }

    public function isOvertime(): bool
    {
        return $this === self::Overtime;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
