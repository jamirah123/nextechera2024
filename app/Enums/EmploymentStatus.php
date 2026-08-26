<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
    case Terminated = 'terminated';
    case Resigned = 'resigned';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Suspended => 'Suspended',
            self::Terminated => 'Terminated',
            self::Resigned => 'Resigned',
            self::Retired => 'Retired',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'emerald',
            self::Inactive => 'slate',
            self::Suspended => 'amber',
            self::Terminated => 'rose',
            self::Resigned => 'violet',
            self::Retired => 'indigo',
        };
    }

    public function isEmployable(): bool
    {
        return $this === self::Active;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
