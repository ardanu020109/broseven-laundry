<?php

namespace App\Http\Requests\Employees;

use App\Http\Requests\Concerns\NormalizesUserInput;
use App\Rules\WhatsAppNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    use NormalizesUserInput;

    public function authorize(): bool
    {
        // Policy: hanya admin, dan hanya untuk akun karyawan.
        Gate::authorize('update', $this->route('employee'));

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedUserFields());
    }

    /** Password, role, dan status tidak dapat diubah lewat endpoint ini. */
    public function rules(): array
    {
        $id = $this->route('employee')->getKey();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'username' => [
                'sometimes', 'nullable', 'string', 'regex:/^[a-z0-9._-]{3,30}$/',
                Rule::unique('users', 'username')->ignore($id),
            ],
            'email' => [
                'sometimes', 'required', 'string', 'email:rfc', 'max:150',
                Rule::unique('users', 'email')->ignore($id),
            ],
            'phone' => ['sometimes', 'nullable', 'string', new WhatsAppNumber()],
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
        return Arr::only($this->validated(), ['name', 'username', 'email', 'phone']);
    }
}