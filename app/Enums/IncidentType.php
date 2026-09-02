<?php

namespace App\Enums;

enum IncidentType: string
{
    case Theft = 'theft';
    case Trespass = 'trespass';
    case Fire = 'fire';
    case Medical = 'medical';
    case Assault = 'assault';
    case Breach = 'breach';
    case Vandalism = 'vandalism';
    case Disturbance = 'disturbance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Theft => 'Theft / loss',
            self::Trespass => 'Trespass',
            self::Fire => 'Fire / alarm',
            self::Medical => 'Medical emergency',
            self::Assault => 'Assault / violence',
            self::Breach => 'Security breach',
            self::Vandalism => 'Vandalism / damage',
            self::Disturbance => 'Disturbance',
            self::Other => 'Other',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Theft, self::Assault, self::Breach => 'rose',
            self::Fire, self::Medical => 'amber',
            self::Trespass, self::Vandalism, self::Disturbance => 'violet',
            self::Other => 'slate',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
