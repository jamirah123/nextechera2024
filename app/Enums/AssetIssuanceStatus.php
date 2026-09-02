<?php

namespace App\Enums;

enum AssetIssuanceStatus: string
{
    case Active = 'active';
    case PartiallyReturned = 'partially_returned';
    case Returned = 'returned';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::PartiallyReturned => 'Partially returned',
            self::Returned => 'Returned',
            self::Closed => 'Closed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'brand',
            self::PartiallyReturned => 'amber',
            self::Returned => 'emerald',
            self::Closed => 'slate',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
