<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\Medication;
use App\Models\MedicationDispensing;
use App\Models\MedicationDispensingItem;
use App\Models\MedicationFormulation;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyMedicationRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_pharmacy_relationships_connect_patient_encounter_medication_prescriber_and_dispensing(): void
    {
        $this->seed();
        $patient = Patient::factory()->create();
        $encounter = ClinicalEncounter::factory()->create([
            'patient_id' => $patient->id,
            'facility_id' => $patient->facility_id,
        ]);
        $pharmacist = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $doctor = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $doctor->roles()->attach(Role::where('slug', 'doctor')->valueOrFail('id'));
        $pharmacist->roles()->attach(Role::where('slug', 'pharmacy')->valueOrFail('id'));

        $medication = Medication::factory()->create(['facility_id' => $patient->facility_id]);
        $formulation = MedicationFormulation::factory()->create(['medication_id' => $medication->id]);
        $prescription = Prescription::factory()->create([
            'facility_id' => $patient->facility_id,
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'prescribed_by' => $doctor->id,
        ]);
        $item = PrescriptionItem::factory()->create([
            'prescription_id' => $prescription->id,
            'medication_id' => $medication->id,
            'medication_formulation_id' => $formulation->id,
        ]);
        $dispensing = MedicationDispensing::factory()->create([
            'facility_id' => $patient->facility_id,
            'patient_id' => $patient->id,
            'prescription_id' => $prescription->id,
            'dispensed_by' => $pharmacist->id,
        ]);
        $dispensingItem = MedicationDispensingItem::factory()->create([
            'medication_dispensings_id' => $dispensing->id,
            'prescription_item_id' => $item->id,
        ]);

        $this->assertSame($patient->id, $prescription->patient->id);
        $this->assertSame($encounter->id, $prescription->encounter->id);
        $this->assertSame($doctor->id, $prescription->prescriber->id);
        $this->assertSame($medication->id, $item->medication->id);
        $this->assertSame($formulation->id, $item->formulation->id);
        $this->assertSame($prescription->id, $dispensing->prescription->id);
        $this->assertSame($pharmacist->id, $dispensing->dispenser->id);
        $this->assertSame($item->id, $dispensingItem->prescriptionItem->id);
        $this->assertSame($dispensing->id, $dispensingItem->dispensing->id);
        $this->assertTrue($encounter->prescriptions()->whereKey($prescription->id)->exists());
        $this->assertTrue($patient->prescriptions()->whereKey($prescription->id)->exists());
        $this->assertTrue($patient->medicationDispensings()->whereKey($dispensing->id)->exists());
    }
}
