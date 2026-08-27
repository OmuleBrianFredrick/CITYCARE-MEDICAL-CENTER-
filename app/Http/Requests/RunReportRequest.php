<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RunReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staff = $this->user();

        return $staff?->isStaff()
            && $staff->isActive()
            && $staff->hasPermissionTo('reports.view');
    }

    public function rules(): array
    {
        return [
            'facility_id' => ['nullable', 'integer', 'exists:facilities,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'filters' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $filters = $this->input('filters', []);

        if (! is_array($filters)) {
            return;
        }

        $this->merge([
            'filters' => array_filter([
                ...$filters,
                'facility_id' => $this->input('facility_id'),
                'date_from' => $this->input('date_from'),
                'date_to' => $this->input('date_to'),
            ], static fn ($value) => $value !== null && $value !== ''),
        ]);
    }
}
