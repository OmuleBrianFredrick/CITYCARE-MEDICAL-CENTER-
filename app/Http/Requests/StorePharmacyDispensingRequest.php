<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePharmacyDispensingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('pharmacy.dispensing.manage') === true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', 'exists:inventory_stores,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.prescription_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity_dispensed' => ['required', 'numeric', 'gt:0'],
            'items.*.batch_number' => ['nullable', 'string', 'max:120'],
            'items.*.expiry_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'At least one prescription item is required.',
            'items.min' => 'At least one prescription item is required.',
            'items.*.prescription_item_id.distinct' => 'A prescription item may only appear once per dispensing.',
            'items.*.quantity_dispensed.gt' => 'Dispensed quantity must be greater than zero.',
        ];
    }
}
