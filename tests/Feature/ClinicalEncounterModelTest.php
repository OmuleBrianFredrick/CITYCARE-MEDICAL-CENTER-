<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicalEncounterModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_encounter_links_patient_appointment_and_clinician(): void
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $servicePoint = ServicePoint::factory()->create(['department_id' => $department->id]);
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $clinician = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $appointment = Appointment::factory()->create([
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id,
            'provider_id' => $clinician->id,
        ]);

        $encounter = ClinicalEncounter::create([
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'clinician_id' => $clinician->id,
            'encounter_number' => 'ENC-TEST-0001',
            'encounter_type' => 'outpatient',
            'status' => ClinicalEncounter::STATUS_OPEN,
            'started_at' => now(),
        ]);

        $this->assertTrue($encounter->patient->is($patient));
        $this->assertTrue($encounter->appointment->is($appointment));
        $this->assertTrue($encounter->clinician->is($clinician));
        $this->assertTrue($encounter->department->is($department));
        $this->assertTrue($encounter->servicePoint->is($servicePoint));
    }

    public function test_encounter_status_helpers_are_explicit(): void
    {
        $encounter = ClinicalEncounter::factory()->make(['status' => ClinicalEncounter::STATUS_OPEN]);
        $this->assertTrue($encounter->isOpen());
        $this->assertFalse($encounter->isClosed());

        $encounter->status = ClinicalEncounter::STATUS_CLOSED;
        $this->assertFalse($encounter->isOpen());
        $this->assertTrue($encounter->isClosed());
    }
}
