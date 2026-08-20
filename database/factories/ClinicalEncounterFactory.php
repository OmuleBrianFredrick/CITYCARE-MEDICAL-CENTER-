<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClinicalEncounter> */
class ClinicalEncounterFactory extends Factory
{
    protected $model = ClinicalEncounter::class;

    public function definition(): array
    {
        $facility = Facility::factory();
        $department = Department::factory(['facility_id' => $facility]);
        $servicePoint = ServicePoint::factory(['department_id' => $department]);
        $patient = Patient::factory(['facility_id' => $facility]);
        $clinician = User::factory()->state(['user_type' => 'staff', 'is_active' => true]);
        $appointment = Appointment::factory()->state([
            'facility_id' => $facility,
            'department_id' => $department,
            'service_point_id' => $servicePoint,
            'patient_id' => $patient,
            'provider_id' => $clinician,
        ]);

        return [
            'facility_id' => $facility,
            'department_id' => $department,
            'service_point_id' => $servicePoint,
            'patient_id' => $patient,
            'appointment_id' => $appointment,
            'clinician_id' => $clinician,
            'encounter_number' => 'ENC-'.fake()->unique()->numerify('########'),
            'type' => ClinicalEncounter::TYPE_OUTPATIENT,
            'status' => ClinicalEncounter::STATUS_OPEN,
            'started_at' => now(),
            'closed_at' => null,
            'summary' => null,
        ];
    }
}
