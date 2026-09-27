<?php

namespace App\Enums;

enum StaffSalaryChangeType: string
{
    case Initial = 'initial';
    case Promotion = 'promotion';
    case Demotion = 'demotion';
    case Increment = 'increment';
    case Reduction = 'reduction';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Initial salary',
            self::Promotion => 'Promotion',
            self::Demotion => 'Demotion',
            self::Increment => 'Increment',
            self::Reduction => 'Reduction',
            self::Other => 'Other',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
