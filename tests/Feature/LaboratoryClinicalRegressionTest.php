<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ClinicalNote;
use App\Models\ClinicalVital;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\ClinicalEncounterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaboratoryClinicalRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_encounter_workspace_regression_keeps_existing_clinical_data_visible_with_diagnostics(): void
    {
        $clinician = $this->staffWithRole('doctor');
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        StaffProfile::query()->updateOrCreate(
            ['user_id' => $clinician->id],
            ['department_id' => $department->id]
        );
        $servicePoint = ServicePoint::factory()->create(['department_id' => $department->id]);
        $patient = Patient::factory()->create([
            'facility_id' => $facility->id,
            'status' => Patient::STATUS_ACTIVE,
        ]);
        $appointment = Appointment::factory()->create([
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id,
            'provider_id' => $clinician->id,
            'status' => Appointment::STATUS_CHECKED_IN,
        ]);
        $encounter = app(ClinicalEncounterService::class)->open($appointment, $clinician);

        ClinicalNote::factory()->create([
            'encounter_id' => $encounter->id,
            'author_id' => $clinician->id,
        ]);
        ClinicalVital::factory()->create([
            'encounter_id' => $encounter->id,
            'recorded_by' => $clinician->id,
        ]);

        $response = $this->actingAs($clinician)->get(route('encounters.show', $encounter));

        $response->assertOk()
            ->assertSee('Clinical notes')
            ->assertSee('Vitals')
            ->assertSee('Laboratory workflow');
    }

    private function staffWithRole(string $roleSlug): User
    {
        $user = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', $roleSlug)->firstOrFail());

        return $user;
    }
}
