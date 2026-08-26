<?php

namespace App\Enums;

enum SiteStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Suspended = 'suspended';
    case Closed = 'closed';
    case ContractExpired = 'contract_expired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Pending => 'Pending',
            self::Suspended => 'Suspended',
            self::Closed => 'Closed',
            self::ContractExpired => 'Contract Expired',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'emerald',
            self::Pending => 'amber',
            self::Suspended => 'rose',
            self::Closed => 'slate',
            self::ContractExpired => 'rose',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
