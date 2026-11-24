<?php

namespace App\Http\Requests\Website;

use App\Support\ThemeColors;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ResetColorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('manage-website');

        return true;
    }

    public function rules(): array
    {
        return [
            'keys' => ['nullable', 'array', 'min:1'],
            'keys.*' => ['string', Rule::in(ThemeColors::keys())],
        ];
    }

    /** @return array<int, string>|null  null = semua warna */
    public function keysToReset(): ?array
    {
        $keys = $this->validated('keys');

        return $keys === null ? null : array_values(array_unique($keys));
    }
}