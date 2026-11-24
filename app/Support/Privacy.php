<?php

namespace App\Support;

class Privacy
{
    /**
     * Menyamarkan nama untuk halaman publik.
     *   "Budi Santoso" => "Bu** Sa*****"
     */
    public static function maskName(?string $name): string
    {
        $words = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map(function (string $word): string {
            $length = mb_strlen($word);

            if ($length <= 1) {
                return $word;
            }

            $keep = $length <= 3 ? 1 : 2;

            return mb_substr($word, 0, $keep).str_repeat('*', min($length - $keep, 6));
        }, $words));
    }
}