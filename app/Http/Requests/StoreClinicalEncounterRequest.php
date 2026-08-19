<?php

namespace App\Http\Requests;

use App\Models\ClinicalEncounter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClinicalEncounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('clinical.encounters.create') === true;
    }

    public function rules(): array
    {
        return [
            'appointment_id' => ['required', 'integer', 'exists:appointments,id'],
            'type' => ['required', Rule::in([
                ClinicalEncounter::TYPE_OUTPATIENT,
                ClinicalEncounter::TYPE_FOLLOW_UP,
                ClinicalEncounter::TYPE_EMERGENCY,
            ])],
            'summary' => ['nullable', 'string'],
        ];
    }
}
