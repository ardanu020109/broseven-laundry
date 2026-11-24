<?php

namespace App\Http\Requests\Website;

use App\Services\WebsiteSettingService;
use App\Support\ThemeColors;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateColorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('manage-website');

        return true;
    }

    protected function prepareForValidation(): void
    {
        $colors = $this->input('colors');

        if (! is_array($colors)) {
            return;
        }

        $this->merge([
            'colors' => array_map(
                fn ($value) => is_string($value) ? strtoupper(trim($value)) : $value,
                $colors
            ),
        ]);
    }

    public function rules(): array
    {
        $rules = ['colors' => ['required', 'array', 'min:1']];

        foreach (ThemeColors::keys() as $key) {
            $rules["colors.{$key}"] = ['sometimes', 'required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'colors.*.regex' => 'Warna harus berupa kode HEX 6 digit, contoh #ADD8E6.',
        ];
    }

    /**
     * Menjaga agar website tetap terbaca: kontras teks terhadap latar dan kartu,
     * serta teks terhadap warna tombol dan kartu.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $input = $validator->validated()['colors'];
            $provided = array_keys($input);
            $merged = array_merge(app(WebsiteSettingService::class)->colors(), $input);

            $fail = function (string $first, string $second, string $message) use ($validator, $provided) {
                $target = in_array($second, $provided, true)
                    ? $second
                    : (in_array($first, $provided, true) ? $first : null);

                if ($target !== null) {
                    $validator->errors()->add("colors.{$target}", $message);
                }
            };

            if (ThemeColors::contrast($merged['text'], $merged['background']) < ThemeColors::MIN_TEXT_CONTRAST) {
                $fail('text', 'background', 'Kontras warna teks dan latar belakang minimal 4,5:1 agar tulisan mudah dibaca.');
            }

            if (ThemeColors::contrast($merged['text'], ThemeColors::SURFACE) < ThemeColors::MIN_TEXT_CONTRAST) {
                $fail('text', 'text', 'Warna teks harus cukup gelap agar terbaca di atas kartu putih (kontras minimal 4,5:1).');
            }

            foreach (['primary', 'secondary', 'accent'] as $brand) {
                if (ThemeColors::contrast($merged['text'], $merged[$brand]) < ThemeColors::MIN_BRAND_CONTRAST) {
                    $fail('text', $brand, 'Kontras dengan warna teks minimal 3:1 agar tulisan pada tombol dan kartu terbaca.');
                }
            }
        });
    }

    /** @return array<string, string> */
    public function payload(): array
    {
        return $this->validated()['colors'];
    }
}