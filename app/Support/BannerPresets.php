<?php

namespace App\Support;

/**
 * Ukuran banner dan batas rendering. Menjadi satu-satunya sumber daftar ukuran:
 * frontend membacanya lewat API, dan log ekspor divalidasi terhadapnya.
 */
final class BannerPresets
{
    public const MIN_CM = 10;

    public const MAX_CM = 600;

    public const MAX_DPI = 300;

    public const MAX_AREA_PX = 50_000_000;

    public const MAX_AREA_PX_MOBILE = 16_000_000;

    /** @return array<int, array<string, mixed>> */
    public static function groups(): array
    {
        return [
            [
                'key' => 'hanging',
                'label' => 'Spanduk Horizontal (Gantung)',
                'presets' => [
                    self::preset('spanduk-100x50', 'Spanduk 100 × 50 cm', 100, 50, 'Gerobak, lapak kecil, atau etalase'),
                    self::preset('spanduk-200x80', 'Spanduk 2 × 0,8 m', 200, 80, 'Gerobak, lapak kecil, atau etalase'),
                    self::preset('spanduk-200x100', 'Spanduk 2 × 1 m', 200, 100, 'Depan ruko, gerbang toko, atau booth jualan'),
                    self::preset('spanduk-300x100', 'Spanduk 3 × 1 m', 300, 100, 'Paling populer untuk warung, toko, dan promosi pinggir jalan'),
                    self::preset('spanduk-400x100', 'Spanduk 4 × 1 m', 400, 100, 'Jalan raya ramai atau acara/bazar besar'),
                    self::preset('spanduk-300x200', 'Spanduk 3 × 2 m', 300, 200, 'Jalan raya ramai atau acara/bazar besar'),
                ],
            ],
            [
                'key' => 'standing',
                'label' => 'Standing Banner (Berdiri)',
                'presets' => [
                    self::preset('mini-25x40', 'Mini Banner 25 × 40 cm', 25, 40, 'Meja kasir atau etalase untuk info harga'),
                    self::preset('mini-26x38', 'Mini Banner 26 × 38 cm', 26, 38, 'Meja kasir atau etalase untuk info harga'),
                    self::preset('standing-60x160', 'X-Banner / Y-Banner / Roll-Up 60 × 160 cm', 60, 160, 'Booth, bazar, depan pintu toko, atau pameran'),
                    self::preset('rollup-85x200', 'Roll-Up Banner 85 × 200 cm', 85, 200, 'Lebih rapi dan kokoh untuk toko, pameran, atau event'),
                    self::preset('tripod-60x80', 'Tripod Banner 60 × 80 cm', 60, 80, 'Praktis di depan lapak atau trotoar'),
                    self::preset('tripod-60x90', 'Tripod Banner 60 × 90 cm', 60, 90, 'Praktis di depan lapak atau trotoar'),
                ],
            ],
        ];
    }

    /** @return array<int, array{key: string, label: string, max_side_px: int}> */
    public static function qualities(): array
    {
        return [
            ['key' => 'ringan', 'label' => 'Ringan (layar dan WhatsApp, sisi terpanjang 2.000 px)', 'max_side_px' => 2000],
            ['key' => 'standar', 'label' => 'Standar (cetak, dilihat dari jauh, 4.000 px)', 'max_side_px' => 4000],
            ['key' => 'tinggi', 'label' => 'Tinggi (cetak detail, butuh memori besar, 8.000 px)', 'max_side_px' => 8000],
        ];
    }

    /** @return array<string, int> */
    public static function limits(): array
    {
        return [
            'min_cm' => self::MIN_CM,
            'max_cm' => self::MAX_CM,
            'max_dpi' => self::MAX_DPI,
            'max_area_px' => self::MAX_AREA_PX,
            'max_area_px_mobile' => self::MAX_AREA_PX_MOBILE,
        ];
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return collect(self::groups())->flatMap(fn (array $g) => collect($g['presets'])->pluck('key'))->all();
    }

    /** @return array<int, string> */
    public static function qualityKeys(): array
    {
        return array_column(self::qualities(), 'key');
    }

    /** @return array<string, mixed>|null */
    public static function find(string $key): ?array
    {
        return collect(self::groups())->flatMap(fn (array $g) => $g['presets'])->firstWhere('key', $key);
    }

    /** @return array<string, mixed> */
    private static function preset(string $key, string $label, int $width, int $height, string $note): array
    {
        return ['key' => $key, 'label' => $label, 'width_cm' => $width, 'height_cm' => $height, 'note' => $note];
    }
}