<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBillingInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'charges' => ['required', 'array', 'min:1'],
            'charges.*' => ['required', 'integer', 'distinct', 'exists:charges,id'],
            'discount_amount' => ['nullable', 'numeric', 'gte:0'],
            'adjustment_amount' => ['nullable', 'numeric'],
            'currency' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'encounter_id' => ['nullable', 'integer', 'exists:clinical_encounters,id'],
        ];
    }
}
