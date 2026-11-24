<?php

namespace App\Http\Requests\Website;

use App\Enums\PackageUnit;
use App\Http\Requests\Concerns\CleansInput;
use App\Models\LaundryPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SavePackageRequest extends FormRequest
{
    use CleansInput;

    public function authorize(): bool
    {
        $package = $this->route('package');

        if ($package instanceof LaundryPackage) {
            Gate::authorize('update', $package);
        } else {
            Gate::authorize('create', LaundryPackage::class);
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('name')) {
            $merge['name'] = $this->cleanText($this->input('name'));
        }

        if ($this->has('description')) {
            $merge['description'] = $this->cleanText($this->input('description'), true);
        }

        if ($this->has('features')) {
            $features = $this->input('features');

            $merge['features'] = is_array($features)
                ? array_values(array_unique(array_filter(array_map(fn ($item) => $this->cleanText($item), $features))))
                : $features;
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        $package = $this->route('package');
        $isUpdate = $package instanceof LaundryPackage;
        $required = $isUpdate ? ['sometimes', 'required'] : ['required'];

        return [
            'name' => [
                ...$required, 'string', 'max:100',
                Rule::unique('laundry_packages', 'name')->whereNull('deleted_at')->ignore($isUpdate ? $package->id : null),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => [...$required, 'numeric', 'decimal:0,2', 'min:0.01', 'max:10000000'],
            'unit' => [...$required, Rule::enum(PackageUnit::class)],
            'estimated_hours' => [...$required, 'integer', 'min:1', 'max:720'],
            'features' => ['nullable', 'array', 'max:12'],
            'features.*' => ['string', 'max:80'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Nama paket sudah digunakan paket lain.',
            'price.min' => 'Harga harus lebih dari 0.',
            'price.max' => 'Harga maksimal Rp10.000.000.',
            'price.decimal' => 'Harga maksimal 2 angka di belakang koma.',
            'estimated_hours.min' => 'Estimasi waktu minimal 1 jam.',
            'estimated_hours.max' => 'Estimasi waktu maksimal 720 jam (30 hari).',
            'features.max' => 'Fasilitas maksimal 12 item.',
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $data = $this->validated();

        if (array_key_exists('features', $data) && $data['features'] === null) {
            $data['features'] = [];
        }

        return $data;
    }
}