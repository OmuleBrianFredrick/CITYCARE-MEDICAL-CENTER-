<?php

namespace Tests\Feature;

use App\Models\ClinicalTreatmentPlan;
use App\Models\User;
use App\Services\ClinicalTreatmentPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClinicalTreatmentPlanServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_staff_can_create_treatment_plan_on_open_encounter(): void
    {
        $this->seed();
        $service = app(ClinicalTreatmentPlanService::class);
        $author = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => true,
            'password' => Hash::make('Password123!'),
        ]);
        $encounter = \App\Models\ClinicalEncounter::factory()->create(['status' => \App\Models\ClinicalEncounter::STATUS_OPEN]);

        $plan = $service->create($encounter, $author, [
            'plan' => 'Start treatment and review in 7 days.',
            'follow_up_date' => now()->addDays(7)->toDateString(),
        ]);

        $this->assertSame($encounter->id, $plan->encounter_id);
        $this->assertSame($author->id, $plan->author_id);
        $this->assertTrue($plan->isActive());
    }

    public function test_closed_encounter_rejects_new_treatment_plan(): void
    {
        $service = app(ClinicalTreatmentPlanService::class);
        $author = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $encounter = \App\Models\ClinicalEncounter::factory()->create(['status' => \App\Models\ClinicalEncounter::STATUS_CLOSED]);

        $this->expectException(ValidationException::class);
        $service->create($encounter, $author, ['plan' => 'Should fail.']);
    }

    public function test_inactive_staff_cannot_create_treatment_plan(): void
    {
        $service = app(ClinicalTreatmentPlanService::class);
        $author = User::factory()->create(['user_type' => 'staff', 'is_active' => false]);
        $encounter = \App\Models\ClinicalEncounter::factory()->create();

        $this->expectException(ValidationException::class);
        $service->create($encounter, $author, ['plan' => 'Should fail.']);
    }

    public function test_active_plan_can_be_completed_and_cancelled(): void
    {
        $service = app(ClinicalTreatmentPlanService::class);
        $plan = ClinicalTreatmentPlan::factory()->create(['status' => ClinicalTreatmentPlan::STATUS_ACTIVE]);

        $completed = $service->complete($plan);
        $this->assertTrue($completed->isCompleted());
        $this->assertNotNull($completed->completed_at);

        $another = ClinicalTreatmentPlan::factory()->create(['status' => ClinicalTreatmentPlan::STATUS_ACTIVE]);
        $cancelled = $service->cancel($another);
        $this->assertSame(ClinicalTreatmentPlan::STATUS_CANCELLED, $cancelled->status);
    }
}
