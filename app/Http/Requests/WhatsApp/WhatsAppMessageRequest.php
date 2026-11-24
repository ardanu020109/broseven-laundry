<?php

namespace App\Http\Requests\WhatsApp;

use App\Enums\TransactionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class WhatsAppMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('view', $this->route('transaction'));

        return true;
    }

    public function rules(): array
    {
        return [
            // Jenis pesan = salah satu status. Kosong berarti mengikuti status saat ini.
            'template' => ['nullable', Rule::enum(TransactionStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'template.enum' => 'Jenis pesan tidak valid.',
        ];
    }
}