<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationFacilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->mayManageRequestedFacility();
    }

    public function rules(): array
    {
        return [
            'facility_id' => [
                'required',
                'integer',
                Rule::exists('facilities', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'registration_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('facilities', 'registration_number')->ignore($this->input('facility_id')),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    public function facilityAttributes(): array
    {
        return [
            ...$this->safe()->except('facility_id'),
            'currency' => strtoupper($this->validated('currency')),
        ];
    }

    private function mayManageRequestedFacility(): bool
    {
        $user = $this->user();

        if (! $user?->isStaff() || ! $user->isActive() || ! $user->hasPermissionTo('organization.manage')) {
            return false;
        }

        if ($user->hasRole('super-admin')) {
            return true;
        }

        $user->loadMissing('staffProfile.department');

        return $user->staffProfile?->department?->facility_id !== null
            && (int) $user->staffProfile->department->facility_id === (int) $this->input('facility_id');
    }
}
