<?php

namespace App\Enums;

enum DeploymentStatus: string
{
    case Active = 'active';
    case Transferred = 'transferred';
    case Ended = 'ended';
    case Replaced = 'replaced';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Transferred => 'Transferred',
            self::Ended => 'Ended',
            self::Replaced => 'Replaced',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'emerald',
            self::Transferred => 'sky',
            self::Ended => 'slate',
            self::Replaced => 'amber',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
