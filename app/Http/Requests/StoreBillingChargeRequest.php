<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBillingChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'billable_service_id' => ['required', 'integer', 'exists:billable_services,id'],
            'service_price_id' => ['required', 'integer', 'exists:service_prices,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'discount_amount' => ['nullable', 'numeric', 'gte:0'],
            'adjustment_amount' => ['nullable', 'numeric'],
            'encounter_id' => ['nullable', 'integer', 'exists:clinical_encounters,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
