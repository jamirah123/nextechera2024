<?php

namespace App\Enums;

enum GuardDocumentType: string
{
    case NationalId = 'national_id';
    case License = 'license';
    case Medical = 'medical';
    case Contract = 'contract';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NationalId => 'National ID',
            self::License => 'License / permit',
            self::Medical => 'Medical certificate',
            self::Contract => 'Contract',
            self::Other => 'Other document',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
