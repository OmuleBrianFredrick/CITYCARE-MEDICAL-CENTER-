<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClinicalDiagnosisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('clinical.diagnoses.manage') === true;
    }

    public function rules(): array
    {
        return [
            'diagnosis' => ['required', 'string', 'max:255'],
            'diagnosis_code' => ['nullable', 'string', 'max:100'],
            'type' => ['required', Rule::in(['primary', 'secondary'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
