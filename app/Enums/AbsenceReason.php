<?php

namespace App\Enums;

enum AbsenceReason: string
{
    case NoShow = 'no_show';
    case LateUnreported = 'late_unreported';
    case SickUnreported = 'sick_unreported';
    case FamilyEmergency = 'family_emergency';
    case Transport = 'transport';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NoShow => 'No show',
            self::LateUnreported => 'Late / unreported',
            self::SickUnreported => 'Sick (unreported)',
            self::FamilyEmergency => 'Family emergency',
            self::Transport => 'Transport issue',
            self::Other => 'Other',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::NoShow => 'rose',
            self::LateUnreported => 'amber',
            self::SickUnreported => 'sky',
            self::FamilyEmergency => 'violet',
            self::Transport => 'indigo',
            self::Other => 'slate',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
