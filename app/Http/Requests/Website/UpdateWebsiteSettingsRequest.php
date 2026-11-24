<?php

namespace App\Http\Requests\Website;

use App\Http\Requests\Concerns\CleansInput;
use App\Rules\WhatsAppNumber;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateWebsiteSettingsRequest extends FormRequest
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

        foreach (['business_name', 'tagline', 'hero_title', 'operating_hours'] as $key) {
            if ($this->has($key)) {
                $merge[$key] = $this->cleanText($this->input($key));
            }
        }

        foreach (['description', 'hero_subtitle', 'address', 'vision'] as $key) {
            if ($this->has($key)) {
                $merge[$key] = $this->cleanText($this->input($key), true);
            }
        }

        if ($this->has('whatsapp')) {
            $raw = $this->input('whatsapp');
            $phone = is_scalar($raw) ? PhoneNumber::normalize((string) $raw) : '';
            $merge['whatsapp'] = $phone === '' ? null : $phone;
        }

        if ($this->has('social_links') && is_array($this->input('social_links'))) {
            $merge['social_links'] = array_map(
                fn ($url) => $this->cleanText($url),
                $this->input('social_links')
            );
        }

        if ($this->has('missions')) {
            $missions = $this->input('missions');

            $merge['missions'] = is_array($missions)
                ? array_values(array_filter(array_map(fn ($item) => $this->cleanText($item), $missions)))
                : $missions;
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'business_name' => ['sometimes', 'required', 'string', 'max:100'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'hero_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'hero_subtitle' => ['sometimes', 'nullable', 'string', 'max:300'],
            'address' => ['sometimes', 'nullable', 'string', 'max:300'],
            'whatsapp' => ['sometimes', 'nullable', 'string', new WhatsAppNumber()],
            'operating_hours' => ['sometimes', 'nullable', 'string', 'max:150'],
            'social_links' => ['sometimes', 'nullable', 'array'],
            'social_links.instagram' => ['nullable', 'url:http,https', 'max:200'],
            'social_links.facebook' => ['nullable', 'url:http,https', 'max:200'],
            'social_links.tiktok' => ['nullable', 'url:http,https', 'max:200'],
            'social_links.youtube' => ['nullable', 'url:http,https', 'max:200'],
            'vision' => ['sometimes', 'nullable', 'string', 'max:500'],
            'missions' => ['sometimes', 'nullable', 'array', 'max:10'],
            'missions.*' => ['string', 'max:200'],
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->validated();
    }
}