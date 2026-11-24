<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Mengubah berbagai format nomor menjadi format internasional tanpa tanda "+".
     *   0812-3456-7890    => 6281234567890
     *   +62 812 3456 7890 => 6281234567890
     *   81234567890       => 6281234567890
     *   620812...         => 62812...   (kesalahan umum: 0 setelah kode negara)
     * Nomor asing (bukan 0/8/62) dibiarkan apa adanya.
     */
    public static function normalize(?string $input): string
    {
        $digits = preg_replace('/\D+/', '', (string) $input) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '620')) {
            return '62'.substr($digits, 3);
        }

        if (str_starts_with($digits, '62')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits;
    }

    /** Memvalidasi nomor yang SUDAH dinormalisasi. */
    public static function isValid(string $normalized): bool
    {
        if (str_starts_with($normalized, '62')) {
            return (bool) preg_match('/^628\d{8,12}$/', $normalized);
        }

        return (bool) preg_match('/^[1-9]\d{7,14}$/', $normalized);
    }
}