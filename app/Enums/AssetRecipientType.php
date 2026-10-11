<?php

namespace App\Enums;

enum AssetRecipientType: string
{
    case Guard = 'guard';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Guard => 'Guard',
            self::Staff => 'Staff',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
