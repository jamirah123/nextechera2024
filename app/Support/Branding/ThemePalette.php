<?php

namespace App\Support\Branding;

class ThemePalette
{
    public const DEFAULT_PRIMARY = '#1845de';

    public const DEFAULT_SIDEBAR = '#070d18';

    /** @return array{primary: string, sidebar: string} */
    public static function defaults(): array
    {
        return [
            'primary' => config('psg.theme.primary', self::DEFAULT_PRIMARY),
            'sidebar' => config('psg.theme.sidebar', self::DEFAULT_SIDEBAR),
        ];
    }

    public static function normalize(?string $hex, string $fallback): string
    {
        if (! is_string($hex)) {
            return $fallback;
        }

        $hex = trim($hex);

        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $hex) === 1) {
            return strtolower($hex);
        }

        return $fallback;
    }

    /** @return array<string, string> */
    public static function brandScale(string $primary): array
    {
        return [
            '50' => self::mix($primary, 0.92),
            '100' => self::mix($primary, 0.84),
            '200' => self::mix($primary, 0.72),
            '300' => self::mix($primary, 0.56),
            '400' => self::mix($primary, 0.36),
            '500' => self::mix($primary, 0.18),
            '600' => self::mix($primary, 0.08),
            '700' => $primary,
            '800' => self::mix($primary, -0.12),
            '900' => self::mix($primary, -0.22),
            '950' => self::mix($primary, -0.32),
        ];
    }

    public static function css(?string $primary, ?string $sidebar): string
    {
        $defaults = self::defaults();
        $primary = self::normalize($primary, $defaults['primary']);
        $sidebar = self::normalize($sidebar, $defaults['sidebar']);

        $lines = collect(self::brandScale($primary))
            ->map(fn (string $color, string $shade) => "    --color-brand-{$shade}: {$color};")
            ->all();

        $lines[] = '    --color-steel-950: '.$sidebar.';';
        $lines[] = '    --color-steel-900: '.self::mix($sidebar, 0.08).';';
        $lines[] = '    --color-steel-850: '.self::mix($sidebar, 0.14).';';

        return ":root {\n".implode("\n", $lines)."\n}";
    }

    private static function mix(string $hex, float $ratio): string
    {
        [$red, $green, $blue] = self::hexToRgb($hex);

        if ($ratio >= 0) {
            return self::rgbToHex(
                (int) round($red + (255 - $red) * $ratio),
                (int) round($green + (255 - $green) * $ratio),
                (int) round($blue + (255 - $blue) * $ratio),
            );
        }

        $factor = 1 + $ratio;

        return self::rgbToHex(
            (int) round($red * $factor),
            (int) round($green * $factor),
            (int) round($blue * $factor),
        );
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function rgbToHex(int $red, int $green, int $blue): string
    {
        return sprintf(
            '#%02x%02x%02x',
            max(0, min(255, $red)),
            max(0, min(255, $green)),
            max(0, min(255, $blue)),
        );
    }
}
