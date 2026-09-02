<?php

namespace App\Enums;

enum AssetCategory: string
{
    case Uniform = 'uniform';
    case Radio = 'radio';
    case Boots = 'boots';
    case Weapon = 'weapon';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Uniform => 'Uniform',
            self::Radio => 'Radio',
            self::Boots => 'Boots',
            self::Weapon => 'Weapon',
            self::Other => 'Other',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Uniform => 'brand',
            self::Radio => 'indigo',
            self::Boots => 'amber',
            self::Weapon => 'rose',
            self::Other => 'slate',
        };
    }

    public function requiresSerial(): bool
    {
        return in_array($this, [self::Radio, self::Weapon], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
