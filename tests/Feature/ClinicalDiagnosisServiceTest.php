<?php

namespace Tests\Feature;

use App\Models\ClinicalDiagnosis;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\ClinicalDiagnosisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClinicalDiagnosisServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_staff_can_record_diagnosis_on_open_encounter(): void
    {
        [$encounter, $clinician] = $this->encounter();

        $diagnosis = app(ClinicalDiagnosisService::class)->record($encounter, $clinician, [
            'diagnosis' => 'Malaria',
            'diagnosis_code' => 'B50',
            'type' => 'primary',
            'notes' => 'Confirmed after clinical assessment.',
        ]);

        $this->assertSame($encounter->id, $diagnosis->encounter_id);
        $this->assertSame($clinician->id, $diagnosis->recorded_by);
        $this->assertSame('Malaria', $diagnosis->diagnosis);
        $this->assertSame('primary', $diagnosis->type);
    }

    public function test_closed_encounter_rejects_new_diagnosis(): void
    {
        [$encounter, $clinician] = $this->encounter();
        $encounter->forceFill(['status' => ClinicalEncounter::STATUS_CLOSED, 'closed_at' => now()])->save();

        $this->expectException(ValidationException::class);
        app(ClinicalDiagnosisService::class)->record($encounter, $clinician, ['diagnosis' => 'Blocked diagnosis']);
    }

    public function test_inactive_staff_cannot_record_diagnosis(): void
    {
        [$encounter] = $this->encounter();
        $inactive = User::factory()->create(['user_type' => 'staff', 'is_active' => false]);

        $this->expectException(ValidationException::class);
        app(ClinicalDiagnosisService::class)->record($encounter, $inactive, ['diagnosis' => 'Blocked diagnosis']);
    }

    public function test_secondary_diagnosis_can_be_recorded(): void
    {
        [$encounter, $clinician] = $this->encounter();

        $diagnosis = app(ClinicalDiagnosisService::class)->record($encounter, $clinician, [
            'diagnosis' => 'Hypertension',
            'type' => 'secondary',
        ]);

        $this->assertSame('secondary', $diagnosis->type);
        $this->assertNull($diagnosis->diagnosis_code);
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
