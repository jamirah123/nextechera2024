<?php

namespace App\Enums;

enum AssetCategory: string
{
    case Uniform = 'uniform';
    case Radio = 'radio';
    case Boots = 'boots';
    case Weapon = 'weapon';
    case Laptop = 'laptop';
    case Phone = 'phone';
    case Vehicle = 'vehicle';
    case Furniture = 'furniture';
    case Tools = 'tools';
    case Security = 'security';
    case Consumable = 'consumable';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Uniform => 'Uniform',
            self::Radio => 'Radio',
            self::Boots => 'Boots',
            self::Weapon => 'Weapon',
            self::Laptop => 'Laptop / computer',
            self::Phone => 'Phone / tablet',
            self::Vehicle => 'Vehicle',
            self::Furniture => 'Furniture',
            self::Tools => 'Tools',
            self::Security => 'Security equipment',
            self::Consumable => 'Consumable',
            self::Other => 'Other',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Uniform => 'brand',
            self::Radio, self::Phone => 'indigo',
            self::Boots, self::Furniture => 'amber',
            self::Weapon, self::Security => 'rose',
            self::Laptop, self::Vehicle => 'sky',
            self::Tools, self::Consumable, self::Other => 'slate',
        };
    }

    public function requiresSerial(): bool
    {
        return in_array($this, [self::Radio, self::Weapon, self::Laptop, self::Phone, self::Vehicle], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Kit issued to guards: uniforms, boots, radios and weapons. */
    public static function forGuards(): array
    {
        return [self::Uniform, self::Boots, self::Radio, self::Weapon, self::Security, self::Consumable];
    }

    /** Equipment issued to staff: computers, phones, furniture, vehicles and tools. */
    public static function forStaff(): array
    {
        return [self::Laptop, self::Phone, self::Furniture, self::Vehicle, self::Tools, self::Other];
    }
}
