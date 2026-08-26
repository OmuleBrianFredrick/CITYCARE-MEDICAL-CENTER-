<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PatientPortalControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_staff_can_view_patient_portal_management(): void
    {
        $patient = $this->patient();
        $staff = $this->staffWithRole('receptionist', $patient->facility);

        $this->actingAs($staff)
            ->get(route('patients.portal.show', $patient))
            ->assertOk()
            ->assertSee($patient->full_name);
    }

    public function test_staff_can_provision_activate_and_disable_patient_portal(): void
    {
        $patient = $this->patient('portal@citycare.test');
        $staff = $this->staffWithRole('administrator', $patient->facility);

        $this->actingAs($staff)
            ->post(route('patients.portal.provision', $patient))
            ->assertRedirect()
            ->assertSessionHas('portal_activation_url');

        $patient->refresh();
        $this->assertNotNull($patient->user_id);
        $this->assertFalse($patient->user->is_active);

        $this->actingAs($staff)
            ->post(route('patients.portal.activate', $patient))
            ->assertRedirect();

        $patient->refresh();
        $this->assertTrue($patient->user->fresh()->is_active);
        $this->assertNotNull($patient->portal_activated_at);

        $this->actingAs($staff)
            ->post(route('patients.portal.disable', $patient))
            ->assertRedirect();

        $this->assertFalse($patient->user->fresh()->is_active);
        $this->assertNotNull($patient->fresh()->portal_disabled_at);

        $this->actingAs($staff)
            ->post(route('patients.portal.invitation', $patient))
            ->assertRedirect()
            ->assertSessionHas('portal_activation_url');
    }

    public function test_user_without_patient_update_permission_cannot_manage_portal(): void
    {
        $patient = $this->patient('blocked@citycare.test');
        $staff = $this->staffWithRole('inventory', $patient->facility);

        $this->actingAs($staff)
            ->get(route('patients.portal.show', $patient))
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('patients.portal.provision', $patient))
            ->assertForbidden();
    }

    public function test_portal_management_does_not_change_patient_record_identity(): void
    {
        $patient = $this->patient('identity@citycare.test');
        $staff = $this->staffWithRole('administrator', $patient->facility);
        $beforeMrn = $patient->medical_record_number;
        $beforeName = $patient->full_name;

        $this->actingAs($staff)->post(route('patients.portal.provision', $patient));

        $patient->refresh();
        $this->assertSame($beforeMrn, $patient->medical_record_number);
        $this->assertSame($beforeName, $patient->full_name);
    }

    public function test_staff_cannot_manage_a_patient_from_another_facility(): void
    {
        $homeFacility = Facility::query()->where('name', 'CityCare Medical Center')->firstOrFail();
        $otherFacility = Facility::factory()->create(['name' => 'CityCare East']);
        $staff = $this->staffWithRole('administrator', $homeFacility);
        $patient = Patient::factory()->create([
            'facility_id' => $otherFacility->id,
            'email' => 'other.facility@citycare.test',
        ]);

        $this->actingAs($staff)
            ->get(route('patients.portal.show', $patient))
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('patients.portal.provision', $patient))
            ->assertForbidden();

        $this->assertNull($patient->fresh()->user_id);
    }

    private function patient(?string $email = 'patient@citycare.test'): Patient
    {
        $facility = Facility::where('name', 'CityCare Medical Center')->firstOrFail();

        return Patient::factory()->create([
            'facility_id' => $facility->id,
            'email' => $email,
        ]);
    }

    private function staffWithRole(string $roleSlug, Facility $facility): User
    {
        $user = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => true,
            'password' => Hash::make('Password123!'),
        ]);

        $user->roles()->attach(Role::where('slug', $roleSlug)->firstOrFail());
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        StaffProfile::create(['user_id' => $user->id, 'department_id' => $department->id]);

        return $user;
    }
}
