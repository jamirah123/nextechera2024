<?php

namespace App\Enums;

enum WorkOrderCategory: string
{
    case Staffing = 'staffing';
    case Contract = 'contract';
    case Hr = 'hr';
    case Finance = 'finance';
    case Compliance = 'compliance';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Staffing => 'Staffing',
            self::Contract => 'Contract renewal',
            self::Hr => 'HR follow-up',
            self::Finance => 'Finance',
            self::Compliance => 'Compliance',
            self::General => 'General',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Staffing => 'amber',
            self::Contract => 'indigo',
            self::Hr => 'rose',
            self::Finance => 'emerald',
            self::Compliance => 'violet',
            self::General => 'slate',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
