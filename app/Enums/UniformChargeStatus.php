<?php

namespace App\Enums;

enum UniformChargeStatus: string
{
    case Subject = 'subject';
    case Exempt = 'exempt';

    public function label(): string
    {
        return match ($this) {
            self::Subject => 'Subject to uniform charge',
            self::Exempt => 'Exempt from uniform charge',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
