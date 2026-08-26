<?php

namespace App\Enums;

enum GuardClassification: string
{
    case Armed = 'armed';
    case Unarmed = 'unarmed';

    public function label(): string
    {
        return match ($this) {
            self::Armed => 'Armed',
            self::Unarmed => 'Unarmed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Armed => 'rose',
            self::Unarmed => 'sky',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
