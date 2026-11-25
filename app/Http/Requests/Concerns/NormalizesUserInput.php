<?php

namespace App\Http\Requests\Concerns;

use App\Support\PhoneNumber;

/**
 * Pembersihan input akun: nama dibersihkan dari tag HTML, username dan email huruf kecil,
 * nomor WhatsApp dinormalisasi ke format internasional (kosong menjadi null).
 */
trait NormalizesUserInput
{
    use CleansInput;

    /**
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    protected function normalizedUserFields(array $fields = ['name', 'username', 'email', 'phone']): array
    {
        $merge = [];

        foreach ($fields as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);

            $merge[$field] = match ($field) {
                'name' => $this->cleanText($value),
                'username', 'email' => $this->lowerTrim($value),
                'phone' => $this->phoneOrNull($value),
                default => $value,
            };
        }

        return $merge;
    }

    private function lowerTrim(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = mb_strtolower(trim((string) $value));

        return $text === '' ? null : $text;
    }

    /** Teks yang tidak bisa dinormalisasi dibiarkan apa adanya agar ditolak oleh validasi. */
    private function phoneOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        $phone = PhoneNumber::normalize($raw);

        return $phone === '' ? $raw : $phone;
    }
}