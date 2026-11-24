<?php

namespace App\Support;

/**
 * Perhitungan uang dan jumlah memakai bcmath (string), tidak pernah float.
 */
class Money
{
    /** Menormalkan angka ke string dengan jumlah desimal tetap, contoh "2.5" => "2.50". */
    public static function decimal(string|int|float $value, int $scale = 2): string
    {
        return bcadd((string) $value, '0', $scale);
    }

    /** Harga x jumlah, dibulatkan ke 2 desimal (half up). */
    public static function multiply(string $price, string $quantity): string
    {
        $product = bcmul($price, $quantity, 4);

        return bcadd(bcadd($product, '0.005', 4), '0', 2);
    }

    /**
     * Pembagian dengan pembulatan half up (untuk bilangan positif).
     * Pembagi 0 menghasilkan nol, bukan error.
     */
    public static function divide(string $dividend, string $divisor, int $scale = 2): string
    {
        if (bccomp($divisor, '0', 8) === 0) {
            return bcadd('0', '0', $scale);
        }

        $quotient = bcdiv($dividend, $divisor, $scale + 2);
        $half = '0.'.str_repeat('0', $scale).'5';

        return bcadd(bcadd($quotient, $half, $scale + 2), '0', $scale);
    }

    /** True jika angka tidak memiliki bagian pecahan. */
    public static function isWhole(string|int|float $value): bool
    {
        return bccomp(bcadd((string) $value, '0', 0), bcadd((string) $value, '0', 2), 2) === 0;
    }
}