<?php

namespace Database\Factories;

use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

class MedicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'name' => fake()->words(2, true),
            'generic_name' => fake()->word().' '.$this->faker->word(),
            'code' => strtoupper(fake()->unique()->bothify('MED-####')),
            'route' => fake()->randomElement(['oral', 'topical', 'intramuscular', 'intravenous']),
            'dosage_form' => fake()->randomElement(['tablet', 'capsule', 'syrup', 'injection', 'cream']),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
