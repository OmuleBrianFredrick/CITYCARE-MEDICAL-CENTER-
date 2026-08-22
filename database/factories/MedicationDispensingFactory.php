<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\MedicationDispensing> */
class MedicationDispensingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'facility_id' => fn () => Patient::factory()->create()->facility_id,
            'prescription_id' => Prescription::factory(),
            'patient_id' => Patient::factory(),
            'dispensed_by' => User::factory(),
            'dispensing_number' => 'DISP-'.strtoupper(fake()->unique()->numerify('########')),
            'status' => 'completed',
            'notes' => fake()->optional()->sentence(),
            'dispensed_at' => now(),
        ];
    }
}
