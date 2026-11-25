<?php

namespace App\Http\Requests\Employees;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SetEmployeeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('employee'));

        return true;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'is_active.required' => 'Status wajib diisi.',
            'is_active.boolean' => 'Status tidak valid.',
        ];
    }
}