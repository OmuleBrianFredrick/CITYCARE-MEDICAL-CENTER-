<?php

namespace Database\Factories;

use App\Models\Medication;
use App\Models\MedicationFormulation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MedicationFormulation> */
class MedicationFormulationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'medication_id' => Medication::factory(),
            'strength' => fake()->randomElement(['250', '500', '5', '10']),
            'unit' => fake()->randomElement(['mg', 'mg/ml', 'mcg']),
            'pack_size' => fake()->randomElement(['10 tablets', '20 tablets', '100 ml']),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-######')),
            'is_active' => true,
        ];
    }
}
