<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Tests\TestCase;

class RequestValidationMassAssignmentSecurityTest extends TestCase
{
    public function test_appointment_request_rejects_server_managed_fields(): void
    {
        $user = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);

        $request = new \App\Http\Requests\StoreAppointmentRequest();
        $request->setUserResolver(fn () => $user);
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

    public function test_user_model_rejects_unapproved_mass_assignment(): void
    {
        $user = new User();

        try {
            $user->fill(['name' => 'Safe User', 'last_login_at' => now()]);
        } catch (MassAssignmentException) {
            $this->assertTrue(true);
            return;
        }

        $this->assertNull($user->last_login_at);
    }

    public function test_appointment_model_only_allows_explicitly_fillable_fields(): void
    {
        $appointment = new Appointment();
        $appointment->fill([
            'reason' => 'Routine review',
            'appointment_number' => 'APT-SECURE',
            'unexpected_privileged_flag' => true,
        ]);

        $this->assertSame('Routine review', $appointment->reason);
        $this->assertSame('APT-SECURE', $appointment->appointment_number);
        $this->assertNull($appointment->getAttribute('unexpected_privileged_flag'));
    }
}
