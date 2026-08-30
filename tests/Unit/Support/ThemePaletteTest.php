<?php

namespace Tests\Unit\Support;

use App\Support\Branding\ThemePalette;
use Tests\TestCase;

class ThemePaletteTest extends TestCase
{
    public function test_it_builds_css_variables_from_primary_and_sidebar_colors(): void
    {
        $css = ThemePalette::css('#1845de', '#070d18');

        $this->assertStringContainsString('--color-brand-700: #1845de;', $css);
        $this->assertStringContainsString('--color-steel-950: #070d18;', $css);
    }

    public function test_it_normalizes_invalid_hex_values_to_fallback(): void
    {
        $this->assertSame('#1845de', ThemePalette::normalize('invalid', '#1845de'));
        $this->assertSame('#0f766e', ThemePalette::normalize('#0F766E', '#1845de'));
    }
}
