<?php

namespace App\Http\Requests\Website;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UploadBrandingImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('manage-website');

        return true;
    }

    /** logo | favicon (dibatasi di route). */
    public function kind(): string
    {
        return (string) $this->route('kind');
    }

    public function rules(): array
    {
        // SVG sengaja tidak diizinkan (dapat memuat skrip).
        $rules = $this->kind() === 'favicon'
            ? ['bail', 'required', 'file', 'mimes:png,webp,ico', 'max:512']
            : ['bail', 'required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'];

        return ['image' => $rules];
    }

    public function messages(): array
    {
        $favicon = $this->kind() === 'favicon';

        return [
            'image.required' => 'Pilih file gambar terlebih dahulu.',
            'image.mimes' => $favicon
                ? 'Favicon harus berupa file PNG, WEBP, atau ICO.'
                : 'Logo harus berupa file PNG, JPG, atau WEBP.',
            'image.max' => 'Ukuran file terlalu besar (maksimal :max KB).',
            'image.dimensions' => 'Dimensi gambar maksimal 4000 x 4000 piksel.',
            'image.uploaded' => 'File gagal diunggah. Pastikan ukuran file tidak melebihi batas server.',
        ];
    }
}