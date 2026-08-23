<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Tests\TestCase;

class RequestValidationMassAssignmentSecurityTest extends TestCase
{
    public function test_appointment_request_does_not_accept_server_managed_fields(): void
    {
        $request = new \App\Http\Requests\StoreAppointmentRequest();
        $request->merge([
            'facility_id' => 1,
            'department_id' => 1,
            'service_point_id' => 1,
            'patient_id' => 1,
            'scheduled_start' => now()->addDay()->toDateTimeString(),
            'scheduled_end' => now()->addDay()->addHour()->toDateTimeString(),
            'status' => Appointment::STATUS_COMPLETED,
            'created_by' => 999999,
        ]);

        $rules = $request->rules();

        $this->assertArrayNotHasKey('status', $rules);
        $this->assertArrayNotHasKey('created_by', $rules);
    }

    public function test_user_model_fails_fast_on_unapproved_mass_assignment_in_tests(): void
    {
        $this->expectException(MassAssignmentException::class);

        (new User())->fill([
            'name' => 'Safe User',
            'last_login_at' => now(),
        ]);
    }

    public function test_appointment_model_fails_fast_on_unexpected_mass_assignment_in_tests(): void
    {
        $this->expectException(MassAssignmentException::class);

        (new Appointment())->fill([
            'reason' => 'Routine review',
            'appointment_number' => 'APT-SECURE',
            'unexpected_privileged_flag' => true,
        ]);
    }
}
