<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->isStaff() === true
            && $user->isActive()
            && $user->hasPermissionTo('staff.manage');
    }

    public function rules(): array
    {
        $staff = $this->route('staff');
        $staffId = $staff instanceof User ? $staff->getKey() : $staff;
        $profileId = $staff instanceof User ? $staff->staffProfile()->value('id') : null;

        return [
            'facility_id' => ['required', 'integer', Rule::exists('facilities', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($staffId)],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'service_point_id' => ['nullable', 'integer', Rule::exists('service_points', 'id')],
            'employee_number' => ['nullable', 'string', 'max:50', Rule::unique('staff_profiles', 'employee_number')->ignore($profileId)],
            'job_title' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'joined_at' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }
}
