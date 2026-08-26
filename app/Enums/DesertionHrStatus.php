<?php

namespace App\Enums;

enum DesertionHrStatus: string
{
    case Reported = 'reported';
    case Investigating = 'investigating';
    case Confirmed = 'confirmed';
    case Returned = 'returned';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Reported => 'Reported',
            self::Investigating => 'Investigating',
            self::Confirmed => 'Confirmed',
            self::Returned => 'Returned',
            self::Closed => 'Closed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Reported => 'amber',
            self::Investigating => 'sky',
            self::Confirmed => 'rose',
            self::Returned => 'emerald',
            self::Closed => 'slate',
        };
    }

    public function keepsDesertedStatus(): bool
    {
        return in_array($this, [self::Reported, self::Investigating, self::Confirmed], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
