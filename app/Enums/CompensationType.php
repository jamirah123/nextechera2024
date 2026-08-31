<?php

namespace App\Enums;

enum CompensationType: string
{
    case Shift = 'shift';
    case Salary = 'salary';

    public function label(): string
    {
        return match ($this) {
            self::Shift => 'Shift-based (field staff)',
            self::Salary => 'Fixed monthly salary',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Shift => 'Shift pay',
            self::Salary => 'Fixed salary',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
