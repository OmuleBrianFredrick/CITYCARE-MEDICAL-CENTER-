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
        $patient = Patient::factory()->create();
        $prescription = Prescription::factory()->create([
            'facility_id' => $patient->facility_id,
            'patient_id' => $patient->id,
        ]);

        return [
            'facility_id' => $patient->facility_id,
            'prescription_id' => $prescription->id,
            'patient_id' => $patient->id,
            'dispensed_by' => User::factory(),
            'dispensing_number' => 'DISP-'.strtoupper(fake()->unique()->numerify('########')),
            'status' => 'completed',
            'notes' => fake()->optional()->sentence(),
            'dispensed_at' => now(),
        ];
    }
}
