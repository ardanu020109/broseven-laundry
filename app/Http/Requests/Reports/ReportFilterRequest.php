<?php

namespace App\Http\Requests\Reports;

use App\Support\ReportRange;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('view-reports');

        return true;
    }

    public function rules(): array
    {
        $custom = $this->input('period') === ReportRange::CUSTOM;

        return [
            'period' => ['nullable', Rule::in(ReportRange::PERIODS)],
            'date_from' => $custom
                ? ['required', 'date_format:Y-m-d']
                : ['nullable', 'date_format:Y-m-d'],
            'date_to' => $custom
                ? ['required', 'date_format:Y-m-d', 'after_or_equal:date_from']
                : ['nullable', 'date_format:Y-m-d'],
            // Paket yang sudah diarsipkan tetap boleh difilter.
            'package_id' => ['nullable', 'integer', Rule::exists('laundry_packages', 'id')],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.required' => 'Tanggal awal wajib diisi untuk rentang tanggal.',
            'date_to.required' => 'Tanggal akhir wajib diisi untuk rentang tanggal.',
            'date_from.date_format' => 'Format tanggal awal harus YYYY-MM-DD.',
            'date_to.date_format' => 'Format tanggal akhir harus YYYY-MM-DD.',
            'date_to.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || $this->input('period') !== ReportRange::CUSTOM) {
                return;
            }

            $days = ReportRange::daysBetween((string) $this->input('date_from'), (string) $this->input('date_to'));

            if ($days > ReportRange::MAX_DAYS) {
                $validator->errors()->add('date_to', 'Rentang tanggal maksimal 5 tahun.');
            }
        });
    }

    public function range(): ReportRange
    {
        return ReportRange::resolve(
            $this->input('period') ?: ReportRange::THIS_MONTH,
            $this->input('date_from'),
            $this->input('date_to'),
        );
    }

    public function packageId(): ?int
    {
        return $this->filled('package_id') ? (int) $this->input('package_id') : null;
    }

    public function searchTerm(): ?string
    {
        $term = trim((string) $this->input('search', ''));

        return $term === '' ? null : $term;
    }

    public function perPage(): int
    {
        return (int) ($this->input('per_page') ?: 15);
    }
}