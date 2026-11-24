<?php

namespace App\Http\Requests\Transactions;

use App\Enums\TransactionStatus;
use App\Models\LaundryTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IndexTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('viewAny', LaundryTransaction::class);

        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            // IN_PROGRESS = RECEIVED, WASHING, DRYING, IRONING
            'status' => ['nullable', Rule::in([...TransactionStatus::values(), 'IN_PROGRESS'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}