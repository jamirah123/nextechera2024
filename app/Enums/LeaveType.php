<?php

namespace App\Enums;

enum LeaveType: string
{
    case Annual = 'annual';
    case Sick = 'sick';
    case Compassionate = 'compassionate';
    case Unpaid = 'unpaid';
    case Maternity = 'maternity';
    case Paternity = 'paternity';
    case Study = 'study';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Annual => 'Annual',
            self::Sick => 'Sick',
            self::Compassionate => 'Compassionate',
            self::Unpaid => 'Unpaid',
            self::Maternity => 'Maternity',
            self::Paternity => 'Paternity',
            self::Study => 'Study',
            self::Other => 'Other',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Annual => 'sky',
            self::Sick => 'amber',
            self::Compassionate => 'violet',
            self::Unpaid => 'slate',
            self::Maternity, self::Paternity => 'indigo',
            self::Study => 'sky',
            self::Other => 'brand',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
