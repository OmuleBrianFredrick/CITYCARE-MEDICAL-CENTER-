<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\ClinicalVital;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicalVitalModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_vitals_link_to_encounter_and_recorder(): void
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $servicePoint = ServicePoint::factory()->create(['department_id' => $department->id]);
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $clinician = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $encounter = ClinicalEncounter::factory()->create([
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id,
            'clinician_id' => $clinician->id,
        ]);

        $vital = ClinicalVital::factory()->create([
            'encounter_id' => $encounter->id,
            'recorded_by' => $clinician->id,
            'temperature_c' => 37.2,
            'pulse_bpm' => 78,
            'respiratory_rate' => 18,
            'oxygen_saturation' => 98.0,
            'systolic_bp' => 120,
            'diastolic_bp' => 80,
            'pain_score' => 2,
        ]);

        $this->assertTrue($vital->encounter->is($encounter));
        $this->assertTrue($vital->recorder->is($clinician));
        $this->assertTrue($encounter->vitals->contains($vital));
    }

    public function test_vitals_store_measurement_values(): void
    {
        $vital = ClinicalVital::factory()->make([
            'temperature_c' => 36.8,
            'pulse_bpm' => 72,
            'respiratory_rate' => 16,
            'oxygen_saturation' => 99.0,
            'systolic_bp' => 118,
            'diastolic_bp' => 76,
            'weight_kg' => 68.50,
            'height_cm' => 171.00,
            'bmi' => 23.41,
            'pain_score' => 0,
            'capillary_glucose_mmol_l' => 5.20,
        ]);

        $this->assertSame('36.8', (string) $vital->temperature_c);
        $this->assertSame(72, $vital->pulse_bpm);
        $this->assertSame(16, $vital->respiratory_rate);
        $this->assertSame('99.0', (string) $vital->oxygen_saturation);
        $this->assertSame(118, $vital->systolic_bp);
        $this->assertSame(76, $vital->diastolic_bp);
        $this->assertSame('68.50', (string) $vital->weight_kg);
        $this->assertSame('171.00', (string) $vital->height_cm);
        $this->assertSame('23.41', (string) $vital->bmi);
        $this->assertSame(0, $vital->pain_score);
        $this->assertSame('5.20', (string) $vital->capillary_glucose_mmol_l);
    }
}
