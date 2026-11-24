<?php

namespace App\Http\Requests\Concerns;

trait CleansInput
{
    /**
     * Membersihkan teks bebas: buang tag HTML, rapikan spasi, kosong menjadi null.
     */
    protected function cleanText(mixed $value, bool $multiline = false): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = strip_tags((string) $value);

        $text = $multiline
            ? preg_replace("/[ \t]+/", ' ', str_replace("\r\n", "\n", $text))
            : preg_replace('/\s+/u', ' ', $text);

        $text = trim((string) $text);

        return $text === '' ? null : $text;
    }
}