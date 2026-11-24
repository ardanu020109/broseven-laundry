<?php

namespace Tests\Unit;

use App\Support\ThemeColors;
use PHPUnit\Framework\TestCase;

class ThemeColorsTest extends TestCase
{
    public function test_normalize(): void
    {
        $this->assertSame('#ADD8E6', ThemeColors::normalize('#add8e6'));
        $this->assertSame('#AABBCC', ThemeColors::normalize(' #abc '));

        foreach (['red', '#12345', '#GGGGGG', '123456', '', null, '#1234567'] as $invalid) {
            $this->assertNull(ThemeColors::normalize($invalid), 'Seharusnya tidak valid: '.var_export($invalid, true));
        }
    }

    public function test_contrast_ratio(): void
    {
        $this->assertEqualsWithDelta(21.0, ThemeColors::contrast('#000000', '#FFFFFF'), 0.01);
        $this->assertEqualsWithDelta(21.0, ThemeColors::contrast('#FFFFFF', '#000000'), 0.01);
        $this->assertEqualsWithDelta(1.0, ThemeColors::contrast('#777777', '#777777'), 0.0001);
    }

    public function test_default_palette_satisfies_the_readability_rules(): void
    {
        $defaults = ThemeColors::DEFAULTS;

        $this->assertGreaterThanOrEqual(
            ThemeColors::MIN_TEXT_CONTRAST,
            ThemeColors::contrast($defaults['text'], $defaults['background'])
        );
        $this->assertGreaterThanOrEqual(
            ThemeColors::MIN_TEXT_CONTRAST,
            ThemeColors::contrast($defaults['text'], ThemeColors::SURFACE)
        );

        foreach (['primary', 'secondary', 'accent'] as $key) {
            $this->assertGreaterThanOrEqual(
                ThemeColors::MIN_BRAND_CONTRAST,
                ThemeColors::contrast($defaults['text'], $defaults[$key]),
                "Warna default {$key} harus terbaca dengan teks default."
            );
        }
    }

    public function test_css_uses_valid_values_and_falls_back_to_defaults(): void
    {
        $css = ThemeColors::css([
            'primary' => '#f4a6c0',
            'secondary' => 'red;}</style><script>',
            'accent' => null,
        ]);

        $this->assertStringStartsWith('html:root{', $css);
        $this->assertStringEndsWith('}', $css);
        $this->assertStringContainsString('--color-primary:#F4A6C0', $css);
        $this->assertStringContainsString('--color-secondary:#ADBCE6', $css);
        $this->assertStringContainsString('--color-accent:#ADE6D8', $css);
        $this->assertStringNotContainsString('<', $css);
        $this->assertSame(5, substr_count($css, '--color-'));
    }

    public function test_keys_and_variable_names(): void
    {
        $this->assertSame(['primary', 'secondary', 'accent', 'background', 'text'], ThemeColors::keys());
        $this->assertSame('--color-primary', ThemeColors::variable('primary'));
    }
}