<?php

namespace App\Support;

class Money
{
    public static function currency(): string
    {
        return (string) config('psg.currency', 'UGX');
    }

    public static function decimals(): int
    {
        return (int) config('psg.currency_decimals', 0);
    }

    public static function format(float|int|string|null $amount, ?string $currency = null): string
    {
        $value = (float) ($amount ?? 0);
        $code = $currency ?: self::currency();
        $formatted = number_format($value, self::decimals(), '.', ',');

        return $code.' '.$formatted;
    }
}
