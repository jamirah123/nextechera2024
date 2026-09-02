<?php

namespace App\Enums;

enum AssetLineStatus: string
{
    case Issued = 'issued';
    case PartiallyReturned = 'partially_returned';
    case Returned = 'returned';
    case WrittenOff = 'written_off';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Issued',
            self::PartiallyReturned => 'Partially returned',
            self::Returned => 'Returned',
            self::WrittenOff => 'Written off',
            self::Lost => 'Lost',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Issued => 'brand',
            self::PartiallyReturned => 'amber',
            self::Returned => 'emerald',
            self::WrittenOff => 'slate',
            self::Lost => 'rose',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
