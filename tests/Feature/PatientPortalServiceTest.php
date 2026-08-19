<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\PatientPortalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PatientPortalServiceTest extends TestCase
{
    use RefreshDatabase;

    private PatientPortalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->service = app(PatientPortalService::class);
    }

    public function test_patient_can_be_provisioned_with_an_inactive_portal_account(): void
    {
        $patient = Patient::factory()->create([
            'email' => 'patient.portal@citycare.test',
        ]);

        $user = $this->service->provision($patient->fresh());

        $this->assertSame('patient', $user->user_type);
        $this->assertFalse($user->is_active);
        $this->assertSame($user->id, $patient->fresh()->user_id);
        $this->assertNotNull($patient->fresh()->portal_invited_at);
    }

    public function test_patient_without_email_cannot_be_provisioned(): void
    {
        $patient = Patient::factory()->create(['email' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('email address is required');

        $this->service->provision($patient);
    }

    public function test_existing_user_email_cannot_be_reused_for_patient_portal(): void
    {
        User::factory()->create(['email' => 'existing@citycare.test']);
        $patient = Patient::factory()->create(['email' => 'existing@citycare.test']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already exists for this email');

        $this->service->provision($patient);
    }

    public function test_portal_can_be_activated_and_disabled(): void
    {
        $patient = Patient::factory()->create(['email' => 'lifecycle@citycare.test']);
        $this->service->provision($patient);
        $patient->refresh();

        $this->service->activate($patient);
        $patient->refresh();

        $this->assertTrue($patient->user->fresh()->is_active);
        $this->assertNotNull($patient->portal_activated_at);
        $this->assertNull($patient->portal_disabled_at);

        $this->service->disable($patient);
        $patient->refresh();

        $this->assertFalse($patient->user->fresh()->is_active);
        $this->assertNotNull($patient->portal_disabled_at);
    }

    public function test_patient_cannot_be_provisioned_twice(): void
    {
        $patient = Patient::factory()->create(['email' => 'duplicate.portal@citycare.test']);
        $this->service->provision($patient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already has a portal account');

        $this->service->provision($patient->fresh());
    }
}
