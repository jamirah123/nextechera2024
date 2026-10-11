<?php

namespace App\Enums;

enum SupplierCategory: string
{
    case Uniforms = 'uniforms';
    case Boots = 'boots';
    case Radios = 'radios';
    case Protective = 'protective_equipment';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Uniforms => 'Uniforms',
            self::Boots => 'Boots',
            self::Radios => 'Radios',
            self::Protective => 'Protective equipment',
            self::Other => 'Other',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
