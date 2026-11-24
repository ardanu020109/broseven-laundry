<?php

namespace App\Http\Requests\Transactions;

use App\Enums\PackageUnit;
use App\Http\Requests\Concerns\CleansInput;
use App\Models\LaundryPackage;
use App\Models\LaundryTransaction;
use App\Rules\WhatsAppNumber;
use App\Support\Money;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    use CleansInput;

    public function authorize(): bool
    {
        Gate::authorize('create', LaundryTransaction::class);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('customer_phone');

        $this->merge([
            'customer_name' => $this->cleanText($this->input('customer_name')),
            'customer_phone' => PhoneNumber::normalize(is_scalar($phone) ? (string) $phone : ''),
            'notes' => $this->cleanText($this->input('notes'), true),
        ]);
    }

    /**
     * Hanya field ini yang dibaca. total_price, status, code, dan harga
     * snapshot tidak pernah berasal dari request.
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['required', 'string', new WhatsAppNumber()],
            'package_id' => [
                'required',
                'integer',
                Rule::exists('laundry_packages', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')
                ),
            ],
            'quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'estimated_completion_at' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'package_id.exists' => 'Paket laundry tidak tersedia.',
            'quantity.min' => 'Berat/jumlah cucian harus lebih dari 0.',
            'quantity.max' => 'Berat/jumlah cucian maksimal 1.000.',
            'estimated_completion_at.after_or_equal' => 'Estimasi selesai tidak boleh sebelum hari ini.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $package = LaundryPackage::query()->find($this->input('package_id'));

            if ($package?->unit === PackageUnit::Item && ! Money::isWhole($this->input('quantity'))) {
                $validator->errors()->add('quantity', 'Jumlah untuk paket per item harus berupa bilangan bulat.');
            }
        });
    }
}