<?php

namespace App\Enums;

enum BillingMode: string
{
    case Monthly = 'monthly';
    case PerShift = 'per_shift';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly day / night posts',
            self::PerShift => 'Per completed shift',
            self::Hybrid => 'Monthly posts + extra shifts',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Monthly => 'Bill negotiated monthly rates for armed/unarmed day and night posts from site manpower.',
            self::PerShift => 'Bill each completed day or night armed/unarmed shift at negotiated shift rates.',
            self::Hybrid => 'Bill monthly armed/unarmed day and night posts, then add overtime and special-duty shifts.',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Monthly => 'brand',
            self::PerShift => 'indigo',
            self::Hybrid => 'violet',
        };
    }

    public function usesMonthlyRates(): bool
    {
        return in_array($this, [self::Monthly, self::Hybrid], true);
    }

    public function usesShiftRates(): bool
    {
        return in_array($this, [self::PerShift, self::Hybrid], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
