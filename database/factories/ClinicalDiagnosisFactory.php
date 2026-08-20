<?php

namespace Database\Factories;

use App\Models\ClinicalDiagnosis;
use App\Models\ClinicalEncounter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClinicalDiagnosis> */
class ClinicalDiagnosisFactory extends Factory
{
    protected $model = ClinicalDiagnosis::class;

    public function definition(): array
    {
        return [
            'encounter_id' => ClinicalEncounter::factory(),
            'recorded_by' => User::factory()->state([
                'user_type' => 'staff',
                'is_active' => true,
            ]),
            'diagnosis' => 'Tension headache',
            'diagnosis_code' => 'G44.209',
            'type' => 'primary',
            'notes' => 'Clinical diagnosis recorded during consultation.',
        ];
    }
}
