<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\TestCase;

class ClinicalEncounterSummaryRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_close_endpoint_rejects_an_overlong_summary(): void
    {
        $this->seed();
        $clinician = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $clinician->roles()->attach(Role::where('slug', 'doctor')->firstOrFail());
        $encounter = ClinicalEncounter::factory()->create(['clinician_id' => $clinician->id]);

        $this->withoutMiddleware(PreventRequestForgery::class)
            ->actingAs($clinician)
            ->post(route('encounters.close', $encounter), ['summary' => str_repeat('x', 5001)])
            ->assertSessionHasErrors('summary');
    }
}
