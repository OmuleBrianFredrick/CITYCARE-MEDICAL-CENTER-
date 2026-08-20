<?php

namespace Database\Factories;

use App\Models\ClinicalEncounter;
use App\Models\ClinicalReferral;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClinicalReferral> */
class ClinicalReferralFactory extends Factory
{
    protected $model = ClinicalReferral::class;

    public function definition(): array
    {
        return [
            'encounter_id' => ClinicalEncounter::factory(),
            'author_id' => User::factory()->state(['user_type' => 'staff', 'is_active' => true]),
            'referred_to' => fake()->randomElement(['Internal Medicine', 'Laboratory', 'Pharmacy', 'Specialist Clinic']),
            'reason' => fake()->sentence(),
            'priority' => ClinicalReferral::PRIORITY_ROUTINE,
            'status' => ClinicalReferral::STATUS_PENDING,
            'notes' => fake()->optional()->sentence(),
            'completed_at' => null,
        ];
    }
}
