<?php

namespace App\Support\Finance;

/**
 * Uganda resident individual PAYE — monthly chargeable income (2026/27 brackets).
 *
 * Based on the Income Tax (Amendment) Act 2026 monthly equivalents:
 * - Up to UGX 335,000: nil
 * - UGX 335,001 – 410,000: 20% on amount exceeding UGX 335,000
 * - UGX 410,001 – 485,000: UGX 15,000 + 25% on amount exceeding UGX 410,000
 * - UGX 485,001 – 10,000,000: UGX 33,750 + 30% on amount exceeding UGX 485,000
 * - Above UGX 10,000,000: band tax at 10M + 10% surtax on amount exceeding UGX 10,000,000
 */
class PayrollPayeCalculator
{
    public const THRESHOLD_TAX_FREE = 335_000;

    public const BAND_20_MAX = 410_000;

    public const BAND_25_MAX = 485_000;

    public const SURTAX_THRESHOLD = 10_000_000;

    public const BAND_25_BASE = 15_000;

    public const BAND_30_BASE = 33_750;

    public static function monthlyTax(float $chargeableIncome): float
    {
        $income = max(0, round($chargeableIncome, 2));

        if ($income <= self::THRESHOLD_TAX_FREE) {
            return 0.0;
        }

        if ($income <= self::BAND_20_MAX) {
            return round(($income - self::THRESHOLD_TAX_FREE) * 0.20, 2);
        }

        if ($income <= self::BAND_25_MAX) {
            return round(self::BAND_25_BASE + (($income - self::BAND_20_MAX) * 0.25), 2);
        }

        if ($income <= self::SURTAX_THRESHOLD) {
            return round(self::BAND_30_BASE + (($income - self::BAND_25_MAX) * 0.30), 2);
        }

        $baseTax = self::monthlyTax(self::SURTAX_THRESHOLD);
        $surtax = ($income - self::SURTAX_THRESHOLD) * 0.10;

        return round($baseTax + $surtax, 2);
    }

    public static function label(): string
    {
        return 'PAYE (Uganda resident brackets)';
    }

    /** @return list<array{upto: float|null, rate: float, base: float, on_excess_above: float}> */
    public static function bracketSummary(): array
    {
        return [
            ['upto' => self::THRESHOLD_TAX_FREE, 'rate' => 0, 'base' => 0, 'on_excess_above' => 0],
            ['upto' => self::BAND_20_MAX, 'rate' => 20, 'base' => 0, 'on_excess_above' => self::THRESHOLD_TAX_FREE],
            ['upto' => self::BAND_25_MAX, 'rate' => 25, 'base' => self::BAND_25_BASE, 'on_excess_above' => self::BAND_20_MAX],
            ['upto' => self::SURTAX_THRESHOLD, 'rate' => 30, 'base' => self::BAND_30_BASE, 'on_excess_above' => self::BAND_25_MAX],
            ['upto' => null, 'rate' => 40, 'base' => self::monthlyTax(self::SURTAX_THRESHOLD), 'on_excess_above' => self::SURTAX_THRESHOLD],
        ];
    }
}
