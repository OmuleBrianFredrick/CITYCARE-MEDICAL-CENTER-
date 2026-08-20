<?php

namespace Database\Factories;

use App\Models\ClinicalEncounter;
use App\Models\Facility;
use App\Models\LaboratoryOrder;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LaboratoryOrderFactory extends Factory
{
    protected $model = LaboratoryOrder::class;

    public function definition(): array
    {
        $facility = Facility::factory();
        $patient = Patient::factory(['facility_id' => $facility]);
        $clinician = User::factory()->state(['user_type' => 'staff', 'is_active' => true]);
        $encounter = ClinicalEncounter::factory()->state([
            'facility_id' => $facility,
            'patient_id' => $patient,
            'clinician_id' => $clinician,
        ]);

        return [
            'facility_id' => $facility,
            'patient_id' => $patient,
            'encounter_id' => $encounter,
            'ordered_by' => $clinician,
            'order_number' => strtoupper(fake()->unique()->bothify('LABORD-########')),
            'status' => LaboratoryOrder::STATUS_ORDERED,
            'notes' => fake()->sentence(),
            'ordered_at' => now(),
        ];
    }
}
