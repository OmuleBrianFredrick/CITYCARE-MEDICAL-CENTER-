<?php

namespace Database\Factories;

use App\Models\ClinicalEncounter;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Prescription> */
class PrescriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'facility_id' => fn () => Patient::factory()->create()->facility_id,
            'patient_id' => Patient::factory(),
            'encounter_id' => ClinicalEncounter::factory(),
            'prescribed_by' => User::factory(),
            'prescription_number' => 'RX-'.strtoupper(fake()->unique()->numerify('########')),
            'status' => 'prescribed',
            'notes' => fake()->optional()->sentence(),
            'prescribed_at' => now(),
            'completed_at' => null,
            'cancelled_at' => null,
        ];
    }
}
