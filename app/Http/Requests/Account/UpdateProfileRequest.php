<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\Concerns\NormalizesUserInput;
use App\Rules\WhatsAppNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    use NormalizesUserInput;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        // role, is_active, dan username tidak diproses di sini; tidak bisa diubah sendiri.
        $this->merge($this->normalizedUserFields(['name', 'email', 'phone']));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => [
                'sometimes', 'required', 'string', 'email:rfc', 'max:150',
                Rule::unique('users', 'email')->ignore($this->user()->getKey()),
            ],
            'phone' => ['sometimes', 'nullable', 'string', new WhatsAppNumber()],
            // Email adalah identitas login: mengubahnya wajib memasukkan password saat ini.
            'current_password' => [
                Rule::requiredIf(fn () => $this->emailIsChanging()),
                'nullable', 'string', 'current_password',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email sudah digunakan akun lain.',
            'current_password.required' => 'Masukkan password saat ini untuk mengubah email.',
            'current_password.current_password' => 'Password saat ini salah.',
        ];
    }

    private function emailIsChanging(): bool
    {
        $email = $this->input('email');

        return $this->has('email')
            && is_string($email)
            && mb_strtolower($email) !== mb_strtolower((string) $this->user()->email);
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return Arr::only($this->validated(), ['name', 'email', 'phone']);
    }
}