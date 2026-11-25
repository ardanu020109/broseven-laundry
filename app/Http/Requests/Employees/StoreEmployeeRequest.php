<?php

namespace App\Http\Requests\Employees;

use App\Http\Requests\Concerns\NormalizesUserInput;
use App\Models\User;
use App\Rules\WhatsAppNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreEmployeeRequest extends FormRequest
{
    use NormalizesUserInput;

    public function authorize(): bool
    {
        Gate::authorize('create', User::class);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedUserFields());
    }

    /** Hanya field ini yang dibaca. role dan is_active tidak pernah berasal dari request. */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => [
                'nullable', 'string', 'regex:/^[a-z0-9._-]{3,30}$/',
                Rule::unique('users', 'username'),
            ],
            'email' => ['required', 'string', 'email:rfc', 'max:150', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', new WhatsAppNumber()],
            'password' => ['required', 'string', 'max:100', Password::min(8)->letters()->numbers()],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, strip, dan garis bawah (3-30 karakter).',
            'email.unique' => 'Email sudah digunakan akun lain.',
            'username.unique' => 'Username sudah digunakan akun lain.',
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return Arr::only($this->validated(), ['name', 'username', 'email', 'phone', 'password']);
    }
}