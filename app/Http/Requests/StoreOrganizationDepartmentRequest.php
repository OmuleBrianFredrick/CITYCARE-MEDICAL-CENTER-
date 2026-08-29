<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationDepartmentRequest extends FormRequest
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
            'code' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('departments', 'code')->where(
                    fn ($query) => $query->where('facility_id', $this->input('facility_id'))
                ),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function departmentAttributes(): array
    {
        return [
            ...$this->safe()->except('facility_id'),
            'code' => strtoupper($this->validated('code')),
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
