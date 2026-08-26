<?php

namespace App\Enums;

enum ContractStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Expired = 'expired';
    case Suspended = 'suspended';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Pending => 'Pending',
            self::Expired => 'Expired',
            self::Suspended => 'Suspended',
            self::Terminated => 'Terminated',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'emerald',
            self::Pending => 'amber',
            self::Expired => 'slate',
            self::Suspended => 'rose',
            self::Terminated => 'rose',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
