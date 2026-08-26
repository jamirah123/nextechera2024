<?php

namespace App\Enums;

enum ReplacementReason: string
{
    case Sick = 'sick';
    case Unavailable = 'unavailable';
    case Leave = 'leave';
    case Emergency = 'emergency';
    case Transport = 'transport';
    case NoShow = 'no_show';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Sick => 'Sick',
            self::Unavailable => 'Unavailable',
            self::Leave => 'Leave',
            self::Emergency => 'Emergency',
            self::Transport => 'Transport',
            self::NoShow => 'No show',
            self::Other => 'Other',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Sick => 'sky',
            self::Unavailable => 'amber',
            self::Leave => 'indigo',
            self::Emergency => 'rose',
            self::Transport => 'violet',
            self::NoShow => 'rose',
            self::Other => 'slate',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
