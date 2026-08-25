<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\ClinicalEncounterService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicalWorkspaceViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_clinician_worklist_only_surfaces_eligible_checked_in_appointments(): void
    {
        [$doctor, $department, $servicePoint] = $this->staffContext('doctor');
        $eligible = $this->checkedInAppointment($doctor, $department, $servicePoint, 'Eligible patient');
        $otherDoctor = $this->staffContext('doctor')[0];
        $assignedElsewhere = $this->checkedInAppointment($otherDoctor, $department, $servicePoint, 'Other clinician patient');

        $this->actingAs($doctor)
            ->get(route('encounters.index'))
            ->assertOk()
            ->assertSee('Ready for consultation')
            ->assertSee($eligible->patient->full_name)
            ->assertDontSee($assignedElsewhere->patient->full_name);
    }

    public function test_opening_encounter_from_browser_form_preserves_type_and_initial_summary(): void
    {
        [$doctor, $department, $servicePoint] = $this->staffContext('doctor');
        $appointment = $this->checkedInAppointment($doctor, $department, $servicePoint, 'Follow-up patient');

        $this->actingAs($doctor)
            ->get(route('encounters.create', ['appointment_id' => $appointment->id]))
            ->assertOk()
            ->assertSee($appointment->appointment_number);

        $this->actingAs($doctor)
            ->post(route('encounters.store'), [
                'appointment_id' => $appointment->id,
                'type' => ClinicalEncounter::TYPE_FOLLOW_UP,
                'summary' => 'Initial follow-up assessment retained from the browser form.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('clinical_encounters', [
            'appointment_id' => $appointment->id,
            'type' => ClinicalEncounter::TYPE_FOLLOW_UP,
            'summary' => 'Initial follow-up assessment retained from the browser form.',
        ]);
    }

    public function test_nurse_sees_triage_and_note_controls_but_not_clinician_only_controls(): void
    {
        [$doctor, $department, $servicePoint] = $this->staffContext('doctor');
        $appointment = $this->checkedInAppointment($doctor, $department, $servicePoint, 'Triage patient');
        $encounter = app(ClinicalEncounterService::class)->open($appointment, $doctor);

        [$nurse] = $this->staffContext('nurse', $department, $servicePoint);

        $this->actingAs($nurse)
            ->get(route('encounters.show', $encounter))
            ->assertOk()
            ->assertSee('Record vitals')
            ->assertSee('Add clinical note')
            ->assertDontSee('Record diagnosis')
            ->assertDontSee('Create treatment plan')
            ->assertDontSee('Create referral')
            ->assertDontSee('Close encounter');
    }

    /** @return array{0: User, 1: Department, 2: ServicePoint} */
    private function staffContext(string $roleSlug, ?Department $department = null, ?ServicePoint $servicePoint = null): array
    {
        $department ??= Department::factory()->create(['facility_id' => Facility::factory()->create()->id]);
        $servicePoint ??= ServicePoint::factory()->create(['department_id' => $department->id]);
        $user = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', $roleSlug)->firstOrFail());
        StaffProfile::query()->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
        ]);

        return [$user, $department, $servicePoint];
    }

    private function checkedInAppointment(User $provider, Department $department, ServicePoint $servicePoint, string $firstName): Appointment
    {
        $patient = Patient::factory()->create([
            'facility_id' => $department->facility_id,
            'first_name' => $firstName,
            'status' => Patient::STATUS_ACTIVE,
        ]);

        return Appointment::factory()->create([
            'facility_id' => $department->facility_id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id,
            'provider_id' => $provider->id,
            'status' => Appointment::STATUS_CHECKED_IN,
            'checked_in_at' => now(),
        ])->load('patient');
    }
}
