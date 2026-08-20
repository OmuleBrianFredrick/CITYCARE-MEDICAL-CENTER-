<?php

namespace Database\Factories;

use App\Models\ClinicalEncounter;
use App\Models\ClinicalNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClinicalNote> */
class ClinicalNoteFactory extends Factory
{
    protected $model = ClinicalNote::class;

    public function definition(): array
    {
        $encounter = ClinicalEncounter::factory();
        $author = User::factory()->state(['user_type' => 'staff', 'is_active' => true]);

        return [
            'encounter_id' => $encounter,
            'author_id' => $author,
            'chief_complaint' => 'Routine consultation',
            'history_of_present_illness' => 'History recorded during encounter.',
            'medical_history' => null,
            'examination' => 'Clinical examination documented.',
            'assessment' => 'Assessment documented.',
            'diagnosis' => 'Working diagnosis documented.',
            'treatment_plan' => 'Treatment plan documented.',
            'follow_up_plan' => null,
            'referral_notes' => null,
            'finalized_at' => null,
        ];
    }
}
