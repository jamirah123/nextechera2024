<?php

namespace App\Enums;

enum AssetIssuanceType: string
{
    case InitialKit = 'initial_kit';
    case Replacement = 'replacement';
    case TopUp = 'top_up';

    public function label(): string
    {
        return match ($this) {
            self::InitialKit => 'Initial kit',
            self::Replacement => 'Replacement',
            self::TopUp => 'Top-up',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::InitialKit => 'emerald',
            self::Replacement => 'amber',
            self::TopUp => 'sky',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
