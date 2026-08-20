<?php

namespace Database\Factories;

use App\Models\ClinicalEncounter;
use App\Models\LaboratoryOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LaboratoryOrderFactory extends Factory
{
    protected $model = LaboratoryOrder::class;

    public function definition(): array
    {
        $encounter = ClinicalEncounter::factory();
        return ['facility_id' => $encounter->facility_id ?? null, 'patient_id' => $encounter->patient_id ?? null, 'encounter_id' => $encounter, 'ordered_by' => User::factory()->state(['user_type' => 'staff', 'is_active' => true]), 'order_number' => strtoupper(fake()->unique()->bothify('LABORD-########')), 'status' => LaboratoryOrder::STATUS_ORDERED, 'notes' => fake()->sentence(), 'ordered_at' => now()];
    }
}
