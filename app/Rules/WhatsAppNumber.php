<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class WhatsAppNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = PhoneNumber::normalize(is_scalar($value) ? (string) $value : '');

        if (! PhoneNumber::isValid($normalized)) {
            $fail('Nomor WhatsApp tidak valid. Contoh yang benar: 081234567890.');
        }
    }
}