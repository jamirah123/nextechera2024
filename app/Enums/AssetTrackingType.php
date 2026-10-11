<?php

namespace App\Enums;

enum AssetTrackingType: string
{
    case Individual = 'individual';
    case Quantity = 'quantity';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Individually tracked',
            self::Quantity => 'Quantity stock',
        };
    }
}
