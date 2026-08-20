<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\ClinicalNote;
use App\Models\ClinicalVital;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\ClinicalCareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClinicalCareServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_staff_can_record_vitals_on_open_encounter(): void
    {
        [$encounter, $clinician] = $this->encounter();

        $vital = app(ClinicalCareService::class)->recordVitals($encounter, $clinician, [
            'temperature_c' => 37.2,
            'pulse_bpm' => 78,
            'respiratory_rate' => 16,
            'oxygen_saturation' => 98.0,
            'systolic_bp' => 120,
            'diastolic_bp' => 80,
        ]);

        $this->assertSame($encounter->id, $vital->encounter_id);
        $this->assertSame($clinician->id, $vital->recorded_by);
        $this->assertSame('37.2', (string) $vital->temperature_c);
    }

    public function test_active_staff_can_save_and_finalize_note(): void
    {
        [$encounter, $clinician] = $this->encounter();

        $service = app(ClinicalCareService::class);
        $note = $service->saveNote($encounter, $clinician, [
            'chief_complaint' => 'Headache',
            'history_of_present_illness' => 'Two days.',
            'examination' => 'Stable.',
            'assessment' => 'No acute findings.',
            'diagnosis' => 'Tension headache.',
            'treatment_plan' => 'Supportive care.',
        ]);

        $finalized = $service->finalizeNote($note);

        $this->assertTrue($finalized->fresh()->isFinalized());
    }

    public function test_closed_encounter_rejects_new_clinical_documentation(): void
    {
        [$encounter, $clinician] = $this->encounter();
        $encounter->forceFill(['status' => ClinicalEncounter::STATUS_CLOSED, 'closed_at' => now()])->save();

        $service = app(ClinicalCareService::class);

        $this->expectException(ValidationException::class);
        $service->saveNote($encounter, $clinician, ['chief_complaint' => 'Blocked']);
    }

    public function test_inactive_staff_cannot_record_clinical_documentation(): void
    {
        [$encounter] = $this->encounter();
        $inactive = User::factory()->create(['user_type' => 'staff', 'is_active' => false]);

        $this->expectException(ValidationException::class);
        app(ClinicalCareService::class)->recordVitals($encounter, $inactive, ['pulse_bpm' => 72]);
    }

    private function encounter(): array
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $servicePoint = ServicePoint::factory()->create(['department_id' => $department->id]);
        $patient = Patient::factory()->create(['facility_id' => $facility->id, 'status' => Patient::STATUS_ACTIVE]);
        $clinician = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);

        $encounter = ClinicalEncounter::factory()->create([
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id,
            'clinician_id' => $clinician->id,
            'status' => ClinicalEncounter::STATUS_OPEN,
        ]);

        return [$encounter, $clinician];
    }
}
