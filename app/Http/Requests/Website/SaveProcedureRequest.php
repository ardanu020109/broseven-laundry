<?php

namespace App\Http\Requests\Website;

use App\Http\Requests\Concerns\CleansInput;
use App\Models\LaundryProcedure;
use App\Support\ProcedureIcons;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveProcedureRequest extends FormRequest
{
    use CleansInput;

    public function authorize(): bool
    {
        Gate::authorize('manage-website');

        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('title')) {
            $merge['title'] = $this->cleanText($this->input('title'));
        }

        if ($this->has('description')) {
            $merge['description'] = $this->cleanText($this->input('description'), true);
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        $required = $this->route('procedure') instanceof LaundryProcedure ? ['sometimes', 'required'] : ['required'];

        return [
            'title' => [...$required, 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', Rule::in(ProcedureIcons::keys())],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->validated();
    }
}