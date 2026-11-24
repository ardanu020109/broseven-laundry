<?php

namespace App\Support;

/**
 * Palette website: nilai default, validasi HEX, dan perhitungan kontras (WCAG).
 */
final class ThemeColors
{
    public const DEFAULTS = [
        'primary' => '#ADD8E6',    // Light Blue
        'secondary' => '#ADBCE6',  // Perano
        'accent' => '#ADE6D8',     // Ice Cold
        'background' => '#F8FCFD',
        'text' => '#243746',
    ];

    public const LABELS = [
        'primary' => 'Warna Utama (Primary)',
        'secondary' => 'Warna Sekunder (Secondary)',
        'accent' => 'Warna Aksen (Accent)',
        'background' => 'Warna Latar (Background)',
        'text' => 'Warna Teks (Text)',
    ];

    /** Warna kartu (tidak dapat diubah admin). Teks harus terbaca di atasnya. */
    public const SURFACE = '#FFFFFF';

    public const MIN_TEXT_CONTRAST = 4.5;

    public const MIN_BRAND_CONTRAST = 3.0;

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::DEFAULTS);
    }

    public static function variable(string $key): string
    {
        return '--color-'.$key;
    }

    /** Mengembalikan "#RRGGBB" huruf besar, atau null jika tidak valid. */
    public static function normalize(?string $hex): ?string
    {
        $hex = trim((string) $hex);

        if (preg_match('/^#([0-9A-Fa-f]{3})$/', $hex, $matches)) {
            $short = $matches[1];
            $hex = '#'.$short[0].$short[0].$short[1].$short[1].$short[2].$short[2];
        }

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $hex) ? strtoupper($hex) : null;
    }

    public static function luminance(string $hex): float
    {
        $hex = self::normalize($hex) ?? '#000000';

        $channels = array_map(
            function (int $value): float {
                $channel = $value / 255;

                return $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
            },
            [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))]
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /** Rasio kontras 1.0 sampai 21.0. */
    public static function contrast(string $a, string $b): float
    {
        $first = self::luminance($a);
        $second = self::luminance($b);

        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    }

    /**
     * CSS variabel warna. Hanya nilai HEX valid yang dipakai (selain itu kembali ke default),
     * sehingga aman ditulis langsung ke halaman. Selektor "html:root" lebih spesifik
     * daripada ":root" di variables.css sehingga selalu menang, apa pun urutan pemuatannya.
     *
     * @param  array<string, string|null>  $colors
     */
    public static function css(array $colors): string
    {
        $declarations = [];

        foreach (self::DEFAULTS as $key => $default) {
            $value = self::normalize($colors[$key] ?? null) ?? $default;
            $declarations[] = self::variable($key).':'.$value;
        }

        return 'html:root{'.implode(';', $declarations).'}';
    }
}