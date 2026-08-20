<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClinicalVitalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'temperature_c' => ['nullable', 'numeric', 'between:25,45'],
            'pulse_bpm' => ['nullable', 'integer', 'between:20,250'],
            'respiratory_rate' => ['nullable', 'integer', 'between:5,80'],
            'oxygen_saturation' => ['nullable', 'numeric', 'between:50,100'],
            'systolic_bp' => ['nullable', 'integer', 'between:50,300'],
            'diastolic_bp' => ['nullable', 'integer', 'between:20,200'],
            'weight_kg' => ['nullable', 'numeric', 'between:0.1,500'],
            'height_cm' => ['nullable', 'numeric', 'between:20,250'],
            'bmi' => ['nullable', 'numeric', 'between:5,100'],
            'pain_score' => ['nullable', 'integer', 'between:0,10'],
            'capillary_glucose_mmol_l' => ['nullable', 'numeric', 'between:0.1,100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
