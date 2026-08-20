<?php

namespace Tests\Feature;

use App\Models\ClinicalDiagnosis;
use App\Models\ClinicalEncounter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicalDiagnosisModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagnosis_links_to_encounter_and_recorder(): void
    {
        $encounter = ClinicalEncounter::factory()->create();
        $recorder = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);

        $diagnosis = ClinicalDiagnosis::create([
            'encounter_id' => $encounter->id,
            'recorded_by' => $recorder->id,
            'diagnosis' => 'Malaria',
            'diagnosis_code' => 'B54',
            'type' => 'primary',
            'notes' => 'Clinical diagnosis recorded.',
        ]);

        $this->assertTrue($diagnosis->encounter->is($encounter));
        $this->assertTrue($diagnosis->recorder->is($recorder));
    }

    public function test_encounter_exposes_diagnoses(): void
    {
        $encounter = ClinicalEncounter::factory()->create();
        ClinicalDiagnosis::factory()->create(['encounter_id' => $encounter->id]);

        $this->assertCount(1, $encounter->fresh()->diagnoses);
    }
}
