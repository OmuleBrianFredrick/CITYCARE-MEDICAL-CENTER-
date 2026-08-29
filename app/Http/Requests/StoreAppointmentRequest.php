<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('appointments.manage') === true;
    }

    public function rules(): array
    {
        return [
            'facility_id' => [
                'required',
                'integer',
                Rule::exists('facilities', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'department_id' => [
                'required', 'integer',
                Rule::exists('departments', 'id')->where(fn ($query) => $query->where('facility_id', $this->input('facility_id'))),
            ],
            'service_point_id' => [
                'required', 'integer',
                Rule::exists('service_points', 'id')->where(fn ($query) => $query->where('department_id', $this->input('department_id'))),
            ],
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('patients', 'id')->where(fn ($query) => $query
                    ->where('facility_id', $this->input('facility_id'))
                    ->where('status', Patient::STATUS_ACTIVE)),
            ],
            'provider_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query
                        ->where('user_type', 'staff')
                        ->where('is_active', true)
                        ->whereExists(function ($profileQuery) {
                            $profileQuery
                                ->selectRaw('1')
                                ->from('staff_profiles')
                                ->join('departments', 'departments.id', '=', 'staff_profiles.department_id')
                                ->whereColumn('staff_profiles.user_id', 'users.id')
                                ->where('departments.facility_id', $this->input('facility_id'));
                        });
                }),
            ],
            'scheduled_start' => ['required', 'date'],
            'scheduled_end' => ['required', 'date', 'after:scheduled_start'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function appointmentData(): array
    {
        return [
            ...$this->validated(),
            'scheduled_start' => Carbon::parse($this->validated('scheduled_start')),
            'scheduled_end' => Carbon::parse($this->validated('scheduled_end')),
            'created_by' => $this->user()->id,
            'status' => Appointment::STATUS_SCHEDULED,
        ];
    }
}
