<?php

namespace Database\Factories;

use App\Models\ClinicalEncounter;
use App\Models\ClinicalVital;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClinicalVital> */
class ClinicalVitalFactory extends Factory
{
    protected $model = ClinicalVital::class;

    public function definition(): array
    {
        return [
            'encounter_id' => ClinicalEncounter::factory(),
            'recorded_by' => User::factory()->state(['user_type' => 'staff', 'is_active' => true]),
            'temperature_c' => fake()->randomFloat(1, 36.0, 39.5),
            'pulse_bpm' => fake()->numberBetween(50, 120),
            'respiratory_rate' => fake()->numberBetween(10, 30),
            'oxygen_saturation' => fake()->randomFloat(1, 90, 100),
            'systolic_bp' => fake()->numberBetween(90, 180),
            'diastolic_bp' => fake()->numberBetween(50, 110),
            'weight_kg' => fake()->randomFloat(2, 35, 150),
            'height_cm' => fake()->randomFloat(2, 120, 210),
            'bmi' => fake()->randomFloat(2, 15, 45),
            'pain_score' => fake()->numberBetween(0, 10),
            'capillary_glucose_mmol_l' => fake()->randomFloat(2, 3, 20),
            'notes' => null,
        ];
    }
}
