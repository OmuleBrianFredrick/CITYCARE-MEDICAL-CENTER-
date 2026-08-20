<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClinicalNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'chief_complaint' => ['nullable', 'string', 'max:5000'],
            'history_of_present_illness' => ['nullable', 'string', 'max:10000'],
            'medical_history' => ['nullable', 'string', 'max:10000'],
            'examination' => ['nullable', 'string', 'max:10000'],
            'assessment' => ['nullable', 'string', 'max:10000'],
            'diagnosis' => ['nullable', 'string', 'max:5000'],
            'treatment_plan' => ['nullable', 'string', 'max:10000'],
            'follow_up_plan' => ['nullable', 'string', 'max:5000'],
            'referral_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
