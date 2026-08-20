<?php

namespace Database\Factories;

use App\Models\ClinicalEncounter;
use App\Models\ClinicalTreatmentPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClinicalTreatmentPlan> */
class ClinicalTreatmentPlanFactory extends Factory
{
    protected $model = ClinicalTreatmentPlan::class;

    public function definition(): array
    {
        return [
            'encounter_id' => ClinicalEncounter::factory(),
            'author_id' => User::factory()->state(['user_type' => 'staff', 'is_active' => true]),
            'plan' => 'Continue current management and review response.',
            'follow_up_date' => now()->addDays(7)->toDateString(),
            'status' => ClinicalTreatmentPlan::STATUS_ACTIVE,
            'completed_at' => null,
        ];
    }
}
