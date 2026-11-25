<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        // SVG sengaja tidak diizinkan (dapat memuat skrip).
        return [
            'photo' => [
                'bail', 'required', 'file',
                'mimes:png,jpg,jpeg,webp', 'max:2048',
                'dimensions:max_width=3000,max_height=3000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'Pilih foto terlebih dahulu.',
            'photo.mimes' => 'Foto harus berupa file PNG, JPG, atau WEBP.',
            'photo.max' => 'Ukuran foto maksimal 2 MB.',
            'photo.dimensions' => 'Dimensi foto maksimal 3000 x 3000 piksel.',
            'photo.uploaded' => 'Foto gagal diunggah. Pastikan ukuran file tidak melebihi batas server.',
        ];
    }
}