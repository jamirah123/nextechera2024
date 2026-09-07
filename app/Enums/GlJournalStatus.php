<?php

namespace App\Enums;

enum GlJournalStatus: string
{
    case Posted = 'posted';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Posted => 'Posted',
            self::Void => 'Void',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Posted => 'emerald',
            self::Void => 'rose',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
