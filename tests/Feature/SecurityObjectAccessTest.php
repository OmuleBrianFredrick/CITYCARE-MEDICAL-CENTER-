<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityObjectAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_workspace_does_not_expose_cross_facility_patient(): void
    {
        $this->seed(CityCareAccessSeeder::class);

        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->attach(Role::where('slug', 'administrator')->firstOrFail());

        $facilityA = Facility::factory()->create();
        $facilityB = Facility::factory()->create();
        $patient = Patient::factory()->create(['facility_id' => $facilityB->id]);

        $this->actingAs($staff)
            ->get(route('patients.show', $patient))
            ->assertForbidden();
    }

    public function test_clinical_encounter_workspace_does_not_expose_cross_facility_encounter(): void
    {
        $this->seed(CityCareAccessSeeder::class);

        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->attach(Role::where('slug', 'doctor')->firstOrFail());

        $facilityA = Facility::factory()->create();
        $facilityB = Facility::factory()->create();
        $patient = Patient::factory()->create(['facility_id' => $facilityB->id]);
        $encounter = ClinicalEncounter::factory()->create(['facility_id' => $facilityB->id, 'patient_id' => $patient->id]);

        $this->actingAs($staff)
            ->get(route('encounters.show', $encounter))
            ->assertForbidden();
    }
}
