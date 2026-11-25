<?php

namespace App\Http\Requests\Employees;

use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EmployeeActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('viewAny', User::class);

        return true;
    }

    public function rules(): array
    {
        return [
            'group' => ['nullable', Rule::in(EmployeeService::ACTIVITY_GROUPS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}