<?php

namespace Tests\Feature;

use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Models\User;
use Tests\TestCase;

class RequestValidationMassAssignmentSecurityTest extends TestCase
{
    public function test_appointment_request_excludes_server_managed_fields(): void
    {
        $request = new StoreAppointmentRequest();
        $rules = $request->rules();

        $this->assertArrayNotHasKey('status', $rules);
        $this->assertArrayNotHasKey('created_by', $rules);
        $this->assertArrayNotHasKey('appointment_number', $rules);
        $this->assertArrayNotHasKey('checked_in_at', $rules);
        $this->assertArrayNotHasKey('cancelled_at', $rules);
        $this->assertArrayNotHasKey('completed_at', $rules);
    }

    public function test_user_model_does_not_whitelist_last_login_timestamp(): void
    {
        $this->assertNotContains('last_login_at', (new User())->getFillable());
    }

    public function test_appointment_request_boundary_is_independent_from_internal_model_fields(): void
    {
        $fillable = (new Appointment())->getFillable();

        $this->assertContains('status', $fillable);
        $this->assertContains('created_by', $fillable);

        $rules = (new StoreAppointmentRequest())->rules();

        $this->assertArrayNotHasKey('status', $rules);
        $this->assertArrayNotHasKey('created_by', $rules);
    }
}
