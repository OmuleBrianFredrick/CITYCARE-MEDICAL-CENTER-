<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\ClinicalTreatmentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicalTreatmentPlanModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_treatment_plan_links_to_encounter_and_author(): void
    {
        $encounter = ClinicalEncounter::factory()->create();
        $author = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);

        $plan = ClinicalTreatmentPlan::create([
            'encounter_id' => $encounter->id,
            'author_id' => $author->id,
            'plan' => 'Continue treatment and review in one week.',
            'follow_up_date' => now()->addWeek()->toDateString(),
            'status' => ClinicalTreatmentPlan::STATUS_ACTIVE,
        ]);

        $this->assertTrue($plan->encounter->is($encounter));
        $this->assertTrue($plan->author->is($author));
        $this->assertTrue($plan->isActive());
        $this->assertFalse($plan->isCompleted());
    }

    public function test_encounter_exposes_treatment_plans(): void
    {
        $encounter = ClinicalEncounter::factory()->create();
        ClinicalTreatmentPlan::factory()->create(['encounter_id' => $encounter->id]);

        $this->assertCount(1, $encounter->fresh()->treatmentPlans);
    }

    public function test_treatment_plan_can_be_completed(): void
    {
        $plan = ClinicalTreatmentPlan::factory()->create();

        $plan->forceFill([
            'status' => ClinicalTreatmentPlan::STATUS_COMPLETED,
            'completed_at' => now(),
        ])->save();

        $plan = $plan->fresh();

        $this->assertTrue($plan->isCompleted());
        $this->assertFalse($plan->isActive());
        $this->assertNotNull($plan->completed_at);
    }
}
