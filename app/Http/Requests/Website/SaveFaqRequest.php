<?php

namespace App\Http\Requests\Website;

use App\Http\Requests\Concerns\CleansInput;
use App\Models\LaundryFaq;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveFaqRequest extends FormRequest
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

        if ($this->has('question')) {
            $merge['question'] = $this->cleanText($this->input('question'));
        }

        if ($this->has('answer')) {
            $merge['answer'] = $this->cleanText($this->input('answer'), true);
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        $required = $this->route('faq') instanceof LaundryFaq ? ['sometimes', 'required'] : ['required'];

        return [
            'question' => [...$required, 'string', 'max:255'],
            'answer' => [...$required, 'string', 'max:2000'],
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