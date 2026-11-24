<?php

namespace App\Http\Requests\Verifications;

use App\Models\LaundryTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class VerificationInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('viewAny', LaundryTransaction::class);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');

        $this->merge([
            'code' => is_string($code) ? trim($code) : $code,
        ]);
    }

    public function rules(): array
    {
        // Bisa berisi token QR, URL hasil scan, atau kode transaksi.
        return [
            'code' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode transaksi atau hasil scan QR wajib diisi.',
            'code.max' => 'Kode terlalu panjang.',
        ];
    }
}