<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\ClinicalEncounterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClinicalEncounterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_encounter_can_be_started_from_an_appointment(): void
    {
        [$facility, $department, $servicePoint] = $this->structure();
        $patient = Patient::factory()->create(['facility_id' => $facility->id, 'status' => Patient::STATUS_ACTIVE]);
        $clinician = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $appointment = Appointment::factory()->create([
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id,
            'provider_id' => $clinician->id,
            'status' => Appointment::STATUS_CHECKED_IN,
        ]);

        $encounter = app(ClinicalEncounterService::class)->open($appointment, $clinician);

        $this->assertSame(ClinicalEncounter::STATUS_OPEN, $encounter->status);
        $this->assertSame($appointment->id, $encounter->appointment_id);
        $this->assertSame($clinician->id, $encounter->clinician_id);
        $this->assertStringStartsWith('ENC-', $encounter->encounter_number);
    }

    public function test_inactive_patient_cannot_open_encounter(): void
    {
        [$facility, $department, $servicePoint] = $this->structure();
        $patient = Patient::factory()->create(['facility_id' => $facility->id, 'status' => Patient::STATUS_INACTIVE]);
        $clinician = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $appointment = Appointment::factory()->create([
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id,
            'provider_id' => $clinician->id,
            'status' => Appointment::STATUS_CHECKED_IN,
        ]);

        $this->expectException(ValidationException::class);
        app(ClinicalEncounterService::class)->open($appointment, $clinician);
    }

    public function test_same_patient_cannot_have_two_open_encounters(): void
    {
        [$facility, $department, $servicePoint] = $this->structure();
        $patient = Patient::factory()->create(['facility_id' => $facility->id, 'status' => Patient::STATUS_ACTIVE]);
        $clinician = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $appointmentOne = Appointment::factory()->create(['facility_id' => $facility->id, 'department_id' => $department->id, 'service_point_id' => $servicePoint->id, 'patient_id' => $patient->id, 'provider_id' => $clinician->id, 'status' => Appointment::STATUS_CHECKED_IN]);
        $appointmentTwo = Appointment::factory()->create(['facility_id' => $facility->id, 'department_id' => $department->id, 'service_point_id' => $servicePoint->id, 'patient_id' => $patient->id, 'provider_id' => $clinician->id, 'status' => Appointment::STATUS_CHECKED_IN]);
        $service = app(ClinicalEncounterService::class);
        $service->open($appointmentOne, $clinician);

        $this->expectException(ValidationException::class);
        $service->open($appointmentTwo, $clinician);
    }

    public function test_only_checked_in_appointments_can_open_encounters(): void
    {
        [$facility, $department, $servicePoint] = $this->structure();
        $patient = Patient::factory()->create(['facility_id' => $facility->id, 'status' => Patient::STATUS_ACTIVE]);
        $clinician = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $appointment = Appointment::factory()->create(['facility_id' => $facility->id, 'department_id' => $department->id, 'service_point_id' => $servicePoint->id, 'patient_id' => $patient->id, 'provider_id' => $clinician->id, 'status' => Appointment::STATUS_SCHEDULED]);

        $this->expectException(ValidationException::class);
        app(ClinicalEncounterService::class)->open($appointment, $clinician);
    }

    public function test_encounter_can_be_closed_and_cancelled_with_valid_transitions(): void
    {
        [$facility, $department, $servicePoint] = $this->structure();
        $patient = Patient::factory()->create(['facility_id' => $facility->id, 'status' => Patient::STATUS_ACTIVE]);
        $clinician = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $appointment = Appointment::factory()->create(['facility_id' => $facility->id, 'department_id' => $department->id, 'service_point_id' => $servicePoint->id, 'patient_id' => $patient->id, 'provider_id' => $clinician->id, 'status' => Appointment::STATUS_CHECKED_IN]);
        $service = app(ClinicalEncounterService::class);
        $encounter = $service->open($appointment, $clinician);

        $service->close($encounter, 'Consultation completed');
        $this->assertTrue($encounter->fresh()->isClosed());
        $this->assertNotNull($encounter->fresh()->closed_at);

        $appointmentTwo = Appointment::factory()->create(['facility_id' => $facility->id, 'department_id' => $department->id, 'service_point_id' => $servicePoint->id, 'patient_id' => $patient->id, 'provider_id' => $clinician->id, 'status' => Appointment::STATUS_CHECKED_IN]);
        $encounterTwo = $service->open($appointmentTwo, $clinician);
        $service->cancel($encounterTwo);
        $this->assertSame(ClinicalEncounter::STATUS_CANCELLED, $encounterTwo->fresh()->status);
    }

    private function structure(): array
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $servicePoint = ServicePoint::factory()->create(['department_id' => $department->id]);
        return [$facility, $department, $servicePoint];
    }
}
