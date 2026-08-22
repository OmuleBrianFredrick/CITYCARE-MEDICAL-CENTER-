<?php

namespace Database\Factories;

use App\Models\Medication;
use App\Models\MedicationFormulation;
use App\Models\Prescription;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\PrescriptionItem> */
class PrescriptionItemFactory extends Factory
{
    public function definition(): array
    {
        $medication = Medication::factory()->create();
        $formulation = MedicationFormulation::factory()->create(['medication_id' => $medication->id]);

        return [
            'prescription_id' => Prescription::factory(),
            'medication_id' => $medication->id,
            'medication_formulation_id' => $formulation->id,
            'quantity' => fake()->randomFloat(3, 1, 30),
            'dose' => fake()->randomElement(['1 tablet', '5 ml', '10 mg']),
            'route' => fake()->randomElement(['oral', 'topical']),
            'frequency' => fake()->randomElement(['once daily', 'twice daily', 'three times daily']),
            'duration' => fake()->randomElement(['3 days', '5 days', '7 days']),
            'instructions' => fake()->optional()->sentence(),
            'status' => 'prescribed',
        ];
    }
}
