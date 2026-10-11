<?php

namespace App\Enums;

enum AssetAcquisitionSource: string
{
    case Purchase = 'purchase';
    case OpeningBalance = 'opening_balance';
    case Donation = 'donation';
    case Transfer = 'transfer';
    case Discovery = 'discovery';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase',
            self::OpeningBalance => 'Opening balance',
            self::Donation => 'Donation',
            self::Transfer => 'Transfer in',
            self::Discovery => 'Discovered on site',
        };
    }
}
