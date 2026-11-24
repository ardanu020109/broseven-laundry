<?php

namespace App\Http\Requests\Website;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReorderRequest extends FormRequest
{
    private const TABLES = [
        'packages' => 'laundry_packages',
        'procedures' => 'laundry_procedures',
        'faqs' => 'laundry_faqs',
    ];

    public function authorize(): bool
    {
        Gate::authorize('manage-website');

        return true;
    }

    /** Jenis daftar diambil dari URL: /api/admin/{packages|procedures|faqs}/reorder */
    public function type(): string
    {
        $segment = (string) $this->segment(3);

        abort_unless(isset(self::TABLES[$segment]), 404);

        return $segment;
    }

    public function rules(): array
    {
        $type = $this->type();
        $exists = Rule::exists(self::TABLES[$type], 'id');

        if ($type === 'packages') {
            $exists->whereNull('deleted_at');
        }

        return [
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'distinct', $exists],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.*.exists' => 'Ada item yang tidak ditemukan. Muat ulang halaman lalu coba lagi.',
            'ids.*.distinct' => 'Daftar urutan memuat item ganda.',
        ];
    }

    /** @return array<int, int> */
    public function ids(): array
    {
        return array_map('intval', $this->validated('ids'));
    }
}