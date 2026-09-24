<?php

namespace App\Support\Finance;

/**
 * Uganda Revenue Authority — resident individual monthly PAYE.
 *
 * Official schedule (from 1 July 2026):
 * | Monthly chargeable income | Rate of tax |
 * | 0 – 335,000 | Nil |
 * | 335,001 – 410,000 | 20% × (income − 335,000) |
 * | 410,001 – 485,000 | 15,000 + 25% × (income − 410,000) |
 * | 485,001 – 10,000,000 | 33,750 + 30% × (income − 485,000) |
 * | Above 10,000,000 | 33,750 + 30% × (income − 485,000) + 10% × (income − 10,000,000) |
 *
 * Brackets are overridable via Platform Settings → Payroll (`psg.payroll.paye_brackets`).
 */
class PayrollPayeCalculator
{
    public const THRESHOLD_TAX_FREE = 335_000;

    public const BAND_20_MAX = 410_000;

    public const BAND_25_MAX = 485_000;

    public const SURTAX_THRESHOLD = 10_000_000;

    public const BAND_25_BASE = 15_000;

    public const BAND_30_BASE = 33_750;

    /**
     * @return array{
     *     threshold_tax_free: float|int,
     *     band_20_max: float|int,
     *     band_25_max: float|int,
     *     surtax_threshold: float|int,
     *     band_25_base: float|int,
     *     band_30_base: float|int,
     *     rate_20: float|int,
     *     rate_25: float|int,
     *     rate_30: float|int,
     *     rate_surtax: float|int,
     *     label: string
     * }
     */
    public static function defaults(): array
    {
        return [
            'threshold_tax_free' => self::THRESHOLD_TAX_FREE,
            'band_20_max' => self::BAND_20_MAX,
            'band_25_max' => self::BAND_25_MAX,
            'surtax_threshold' => self::SURTAX_THRESHOLD,
            'band_25_base' => self::BAND_25_BASE,
            'band_30_base' => self::BAND_30_BASE,
            'rate_20' => 20,
            'rate_25' => 25,
            'rate_30' => 30,
            'rate_surtax' => 10,
            'label' => 'PAYE (URA resident monthly)',
        ];
    }

    /**
     * @param  array<string, mixed>  $configured
     * @return array{
     *     threshold_tax_free: float,
     *     band_20_max: float,
     *     band_25_max: float,
     *     surtax_threshold: float,
     *     band_25_base: float,
     *     band_30_base: float,
     *     rate_20: float,
     *     rate_25: float,
     *     rate_30: float,
     *     rate_surtax: float,
     *     label: string
     * }
     */
    public static function bracketsFrom(array $configured = []): array
    {
        $merged = array_merge(self::defaults(), $configured);

        return [
            'threshold_tax_free' => (float) $merged['threshold_tax_free'],
            'band_20_max' => (float) $merged['band_20_max'],
            'band_25_max' => (float) $merged['band_25_max'],
            'surtax_threshold' => (float) $merged['surtax_threshold'],
            'band_25_base' => (float) $merged['band_25_base'],
            'band_30_base' => (float) $merged['band_30_base'],
            'rate_20' => (float) $merged['rate_20'],
            'rate_25' => (float) $merged['rate_25'],
            'rate_30' => (float) $merged['rate_30'],
            'rate_surtax' => (float) $merged['rate_surtax'],
            'label' => (string) ($merged['label'] ?: self::defaults()['label']),
        ];
    }

    /**
     * @return array{
     *     threshold_tax_free: float,
     *     band_20_max: float,
     *     band_25_max: float,
     *     surtax_threshold: float,
     *     band_25_base: float,
     *     band_30_base: float,
     *     rate_20: float,
     *     rate_25: float,
     *     rate_30: float,
     *     rate_surtax: float,
     *     label: string
     * }
     */
    public static function brackets(): array
    {
        $configured = [];

        if (function_exists('config')) {
            try {
                $value = config('psg.payroll.paye_brackets');
                if (is_array($value)) {
                    $configured = $value;
                }
            } catch (\Throwable) {
                // Pure PHPUnit cases without a bootstrapped container.
            }
        }

        return self::bracketsFrom($configured);
    }

    public static function monthlyTax(float $chargeableIncome): float
    {
        $b = self::brackets();
        $income = max(0, round($chargeableIncome, 2));

        if ($income <= $b['threshold_tax_free']) {
            return 0.0;
        }

        // 335,001 – 410,000: 20% × (income − 335,000)
        if ($income <= $b['band_20_max']) {
            return round(($income - $b['threshold_tax_free']) * ($b['rate_20'] / 100), 2);
        }

        // 410,001 – 485,000: 15,000 + 25% × (income − 410,000)
        if ($income <= $b['band_25_max']) {
            return round($b['band_25_base'] + (($income - $b['band_20_max']) * ($b['rate_25'] / 100)), 2);
        }

        // 485,001 – 10,000,000: 33,750 + 30% × (income − 485,000)
        // Above 10,000,000: same + 10% × (income − 10,000,000)
        $tax = $b['band_30_base'] + (($income - $b['band_25_max']) * ($b['rate_30'] / 100));

        if ($income > $b['surtax_threshold']) {
            $tax += ($income - $b['surtax_threshold']) * ($b['rate_surtax'] / 100);
        }

        return round($tax, 2);
    }

    public static function label(): string
    {
        return self::brackets()['label'];
    }

    /**
     * Official URA schedule rows for admin display.
     *
     * @return list<array{income: string, rate: string}>
     */
    public static function officialSchedule(): array
    {
        $b = self::defaults();

        return [
            [
                'income' => '0 – '.number_format($b['threshold_tax_free']),
                'rate' => 'Nil',
            ],
            [
                'income' => number_format($b['threshold_tax_free'] + 1).' – '.number_format($b['band_20_max']),
                'rate' => number_format($b['rate_20'], 0).'% × (Chargeable income – '.number_format($b['threshold_tax_free']).')',
            ],
            [
                'income' => number_format($b['band_20_max'] + 1).' – '.number_format($b['band_25_max']),
                'rate' => number_format($b['band_25_base']).' + '.number_format($b['rate_25'], 0).'% × (Chargeable income – '.number_format($b['band_20_max']).')',
            ],
            [
                'income' => number_format($b['band_25_max'] + 1).' – '.number_format($b['surtax_threshold']),
                'rate' => number_format($b['band_30_base']).' + '.number_format($b['rate_30'], 0).'% × (Chargeable income – '.number_format($b['band_25_max']).')',
            ],
            [
                'income' => 'Above '.number_format($b['surtax_threshold']),
                'rate' => number_format($b['band_30_base']).' + '.number_format($b['rate_30'], 0).'% × (Chargeable income – '.number_format($b['band_25_max']).') + '.number_format($b['rate_surtax'], 0).'% × (Chargeable income – '.number_format($b['surtax_threshold']).')',
            ],
        ];
    }

    /** @return list<array{upto: float|null, rate: float, base: float, on_excess_above: float}> */
    public static function bracketSummary(): array
    {
        $b = self::brackets();

        return [
            ['upto' => $b['threshold_tax_free'], 'rate' => 0, 'base' => 0, 'on_excess_above' => 0],
            ['upto' => $b['band_20_max'], 'rate' => $b['rate_20'], 'base' => 0, 'on_excess_above' => $b['threshold_tax_free']],
            ['upto' => $b['band_25_max'], 'rate' => $b['rate_25'], 'base' => $b['band_25_base'], 'on_excess_above' => $b['band_20_max']],
            ['upto' => $b['surtax_threshold'], 'rate' => $b['rate_30'], 'base' => $b['band_30_base'], 'on_excess_above' => $b['band_25_max']],
            ['upto' => null, 'rate' => $b['rate_30'] + $b['rate_surtax'], 'base' => self::monthlyTax($b['surtax_threshold']), 'on_excess_above' => $b['surtax_threshold']],
        ];
    }
}
