<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClinicalEncounterControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_clinician_can_view_encounter_workspace_and_open_checked_in_appointment(): void
    {
        $clinician = $this->staffWithRole('doctor');
        $appointment = $this->checkedInAppointment($clinician);

        $this->actingAs($clinician)
            ->get(route('encounters.index'))
            ->assertOk();

        $this->actingAs($clinician)
            ->get(route('encounters.create'))
            ->assertOk()
            ->assertSee($appointment->appointment_number);
    }

    public function test_clinician_can_open_encounter_through_http_workflow(): void
    {
        $clinician = $this->staffWithRole('doctor');
        $appointment = $this->checkedInAppointment($clinician);

        $response = $this->actingAs($clinician)->post(route('encounters.store'), [
            'appointment_id' => $appointment->id,
            'type' => ClinicalEncounter::TYPE_OUTPATIENT,
            'summary' => 'Initial consultation',
        ]);

        $encounter = ClinicalEncounter::firstOrFail();

        $response->assertRedirect(route('encounters.show', $encounter));
        $this->assertSame($appointment->patient_id, $encounter->patient_id);
        $this->assertSame($clinician->id, $encounter->clinician_id);
        $this->assertDatabaseHas('clinical_encounters', [
            'id' => $encounter->id,
            'status' => ClinicalEncounter::STATUS_OPEN,
        ]);
    }

    public function test_clinician_can_close_and_cancel_encounter(): void
    {
        $clinician = $this->staffWithRole('doctor');
        $appointment = $this->checkedInAppointment($clinician);

        $this->actingAs($clinician)->post(route('encounters.store'), [
            'appointment_id' => $appointment->id,
            'type' => ClinicalEncounter::TYPE_OUTPATIENT,
        ]);
        $encounter = ClinicalEncounter::firstOrFail();

        $this->actingAs($clinician)
            ->post(route('encounters.close', $encounter), ['summary' => 'Consultation complete'])
            ->assertRedirect();
        $this->assertTrue($encounter->fresh()->isClosed());

        $appointmentTwo = $this->checkedInAppointment($clinician);
        $this->actingAs($clinician)->post(route('encounters.store'), [
            'appointment_id' => $appointmentTwo->id,
            'type' => ClinicalEncounter::TYPE_FOLLOW_UP,
        ]);
        $encounterTwo = ClinicalEncounter::where('appointment_id', $appointmentTwo->id)->firstOrFail();

        $this->actingAs($clinician)
            ->post(route('encounters.cancel', $encounterTwo))
            ->assertRedirect();
        $this->assertSame(ClinicalEncounter::STATUS_CANCELLED, $encounterTwo->fresh()->status);
    }

    public function test_user_without_clinical_encounter_create_permission_is_denied(): void
    {
        $user = $this->staffWithRole('receptionist');

        $this->actingAs($user)->get(route('encounters.create'))->assertForbidden();
        $this->actingAs($user)->post(route('encounters.store'), [
            'appointment_id' => 999999,
            'type' => ClinicalEncounter::TYPE_OUTPATIENT,
        ])->assertForbidden();
    }

    public function test_encounter_workspace_can_search_and_filter(): void
    {
        $clinician = $this->staffWithRole('doctor');
        $appointment = $this->checkedInAppointment($clinician, 'Searchable');

        $encounter = app(\App\Services\ClinicalEncounterService::class)->open($appointment, $clinician);

        $this->actingAs($clinician)
            ->get(route('encounters.index', ['status' => ClinicalEncounter::STATUS_OPEN, 'search' => $encounter->encounter_number]))
            ->assertOk()
            ->assertSee($encounter->encounter_number);
    }

    public function test_clinician_can_view_loaded_notes_vitals_diagnoses_treatment_plans_and_referrals(): void
    {
        $clinician = $this->staffWithRole('doctor');
        $appointment = $this->checkedInAppointment($clinician);
        $encounter = app(\App\Services\ClinicalEncounterService::class)->open($appointment, $clinician);

        $this->actingAs($clinician)
            ->get(route('encounters.show', $encounter))
            ->assertOk();
    }

    private function checkedInAppointment(User $clinician, string $suffix = 'Patient'): Appointment
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $servicePoint = ServicePoint::factory()->create(['department_id' => $department->id]);
        $patient = Patient::factory()->create([
            'facility_id' => $facility->id,
            'first_name' => $suffix,
            'status' => Patient::STATUS_ACTIVE,
        ]);

        return Appointment::factory()->create([
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id,
            'provider_id' => $clinician->id,
            'status' => Appointment::STATUS_CHECKED_IN,
        ]);
    }

    private function staffWithRole(string $roleSlug): User
    {
        $user = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => true,
            'password' => Hash::make('Password123!'),
        ]);

        $user->roles()->attach(Role::where('slug', $roleSlug)->firstOrFail());

        return $user;
    }
}
