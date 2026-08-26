<?php

namespace App\Enums;

enum SupervisorStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Suspended => 'Suspended',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'emerald',
            self::Inactive => 'slate',
            self::Suspended => 'amber',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
