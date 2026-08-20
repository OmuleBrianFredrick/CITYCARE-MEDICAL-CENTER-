<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClinicalEncounterSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('clinical.encounters.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'summary' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
