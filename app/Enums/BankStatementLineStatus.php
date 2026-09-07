<?php

namespace App\Enums;

enum BankStatementLineStatus: string
{
    case Unmatched = 'unmatched';
    case Matched = 'matched';
    case Excluded = 'excluded';

    public function label(): string
    {
        return match ($this) {
            self::Unmatched => 'Unmatched',
            self::Matched => 'Matched',
            self::Excluded => 'Excluded',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Unmatched => 'amber',
            self::Matched => 'emerald',
            self::Excluded => 'slate',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
