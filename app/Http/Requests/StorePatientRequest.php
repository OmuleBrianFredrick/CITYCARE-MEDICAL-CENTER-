<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('patients.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'facility_id' => [
                'required',
                'integer',
                Rule::exists('facilities', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'sex' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'national_id' => ['nullable', 'string', 'max:100', 'unique:patients,national_id'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:80'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'next_of_kin_name' => ['nullable', 'string', 'max:255'],
            'next_of_kin_relationship' => ['nullable', 'string', 'max:80'],
            'next_of_kin_phone' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'deceased', 'archived'])],
        ];
    }

    public function messages(): array
    {
        return [
            'national_id.unique' => 'A patient with this national ID is already registered.',
            'date_of_birth.before_or_equal' => 'Date of birth cannot be in the future.',
        ];
    }
}
