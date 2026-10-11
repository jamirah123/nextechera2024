<?php

namespace App\Enums;

enum VatTreatment: string
{
    case Standard = 'standard';
    case ZeroRated = 'zero_rated';
    case Exempt = 'exempt';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard-rated',
            self::ZeroRated => 'Zero-rated',
            self::Exempt => 'Exempt',
        };
    }

    public function chargesVat(): bool
    {
        return $this === self::Standard;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
