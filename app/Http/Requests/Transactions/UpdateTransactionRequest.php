<?php

namespace App\Http\Requests\Transactions;

use App\Enums\PackageUnit;
use App\Http\Requests\Concerns\CleansInput;
use App\Models\LaundryPackage;
use App\Rules\WhatsAppNumber;
use App\Support\Money;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
{
    use CleansInput;

    public function authorize(): bool
    {
        // Policy menolak jika transaksi sudah final (COMPLETED / CANCELLED).
        Gate::authorize('update', $this->route('transaction'));

        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('customer_name')) {
            $merge['customer_name'] = $this->cleanText($this->input('customer_name'));
        }

        if ($this->has('customer_phone')) {
            $phone = $this->input('customer_phone');
            $merge['customer_phone'] = PhoneNumber::normalize(is_scalar($phone) ? (string) $phone : '');
        }

        if ($this->has('notes')) {
            $merge['notes'] = $this->cleanText($this->input('notes'), true);
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        $currentPackageId = $this->route('transaction')?->package_id;

        return [
            'customer_name' => ['sometimes', 'required', 'string', 'max:100'],
            'customer_phone' => ['sometimes', 'required', 'string', new WhatsAppNumber()],
            'package_id' => [
                'sometimes',
                'required',
                'integer',
                // Paket aktif, atau paket yang sudah dipakai transaksi ini.
                Rule::exists('laundry_packages', 'id')->where(function ($query) use ($currentPackageId) {
                    $query->whereNull('deleted_at')->where(function ($q) use ($currentPackageId) {
                        $q->where('is_active', true)->orWhere('id', $currentPackageId);
                    });
                }),
            ],
            'quantity' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'estimated_completion_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:today'],
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

            if (! $this->has('quantity') && ! $this->has('package_id')) {
                return;
            }

            $transaction = $this->route('transaction');
            $package = LaundryPackage::query()->find($this->input('package_id', $transaction->package_id));
            $quantity = $this->input('quantity', $transaction->quantity);

            if ($package?->unit === PackageUnit::Item && ! Money::isWhole($quantity)) {
                $validator->errors()->add('quantity', 'Jumlah untuk paket per item harus berupa bilangan bulat.');
            }
        });
    }
}