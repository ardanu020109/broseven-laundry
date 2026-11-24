<?php

namespace App\Http\Requests\Transactions;

use App\Enums\TransactionStatus;
use App\Http\Requests\Concerns\CleansInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ChangeTransactionStatusRequest extends FormRequest
{
    use CleansInput;

    public function authorize(): bool
    {
        Gate::authorize('changeStatus', $this->route('transaction'));

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'note' => $this->cleanText($this->input('note'), true),
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TransactionStatus::class)],
            'note' => ['nullable', 'string', 'max:500', 'required_if:status,CANCELLED'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.required_if' => 'Alasan pembatalan wajib diisi.',
        ];
    }

    public function targetStatus(): TransactionStatus
    {
        return TransactionStatus::from($this->validated('status'));
    }
}