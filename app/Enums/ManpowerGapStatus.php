<?php

namespace App\Enums;

enum ManpowerGapStatus: string
{
    case None = 'none';
    case Open = 'open';
    case PartiallyResolved = 'partially_resolved';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No shortage',
            self::Open => 'Open shortage',
            self::PartiallyResolved => 'Partially resolved',
            self::Resolved => 'Resolved by overtime',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::None => 'slate',
            self::Open => 'rose',
            self::PartiallyResolved => 'amber',
            self::Resolved => 'emerald',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
