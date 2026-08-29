<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\ClinicalNote;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicalCareControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_clinician_can_record_vitals_through_http_workflow(): void
    {
        [$encounter, $clinician] = $this->encounterForRole('doctor');

        $response = $this->actingAs($clinician)->post(route('encounters.vitals.store', $encounter), [
            'temperature_c' => 37.2,
            'pulse_bpm' => 78,
            'respiratory_rate' => 16,
            'oxygen_saturation' => 98,
            'systolic_bp' => 120,
            'diastolic_bp' => 80,
            'weight_kg' => 72.5,
            'height_cm' => 175,
            'pain_score' => 2,
            'notes' => 'Patient stable.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('clinical_vitals', [
            'encounter_id' => $encounter->id,
            'recorded_by' => $clinician->id,
            'pulse_bpm' => 78,
        ]);
    }

    public function test_clinician_can_save_and_finalize_note_through_http_workflow(): void
    {
        [$encounter, $clinician] = $this->encounterForRole('doctor');

        $response = $this->actingAs($clinician)->post(route('encounters.notes.store', $encounter), [
            'chief_complaint' => 'Headache',
            'history_of_present_illness' => 'Two days.',
            'examination' => 'Stable.',
            'assessment' => 'No acute findings.',
            'diagnosis' => 'Tension headache.',
            'treatment_plan' => 'Supportive care.',
        ]);

        $response->assertRedirect();

        $note = ClinicalNote::firstOrFail();

        $this->actingAs($clinician)
            ->post(route('encounters.notes.finalize', $note))
            ->assertRedirect();

        $this->assertNotNull($note->fresh()->finalized_at);
    }

    public function test_user_without_vitals_permission_is_denied(): void
    {
        [$encounter, $user] = $this->encounterForRole('receptionist');

        $this->actingAs($user)
            ->post(route('encounters.vitals.store', $encounter), ['pulse_bpm' => 72])
            ->assertForbidden();
    }

    public function test_user_without_notes_permission_is_denied(): void
    {
        [$encounter, $user] = $this->encounterForRole('receptionist');

        $this->actingAs($user)
            ->post(route('encounters.notes.store', $encounter), ['chief_complaint' => 'Blocked'])
            ->assertForbidden();
    }

    private function encounterForRole(string $roleSlug): array
    {
        $user = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', $roleSlug)->firstOrFail());

        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $servicePoint = ServicePoint::factory()->create(['department_id' => $department->id]);
        StaffProfile::create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'employee_number' => 'CLINICAL-'.$user->id,
            'employment_status' => 'active',
        ]);
        $patient = Patient::factory()->create([
            'facility_id' => $facility->id,
            'status' => Patient::STATUS_ACTIVE,
        ]);

        $encounter = ClinicalEncounter::factory()->create([
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id,
            'clinician_id' => $user->id,
            'status' => ClinicalEncounter::STATUS_OPEN,
        ]);

        return [$encounter, $user];
    }
}
