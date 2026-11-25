<?php

namespace App\Http\Requests\Employees;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;

class ResetEmployeePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('employee'));

        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'max:100', Password::min(8)->letters()->numbers()],
        ];
    }
}