<?php

namespace Tests\Feature;

use App\Models\Medication;
use App\Models\MedicationDispensing;
use App\Models\MedicationDispensingItem;
use App\Models\MedicationFormulation;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyMedicationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_pharmacy_models_can_persist_the_defined_statuses_and_quantities(): void
    {
        $patient = Patient::factory()->create();
        $prescriber = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $medication = Medication::factory()->create(['facility_id' => $patient->facility_id]);
        $formulation = MedicationFormulation::factory()->create(['medication_id' => $medication->id]);
        $prescription = Prescription::factory()->create([
            'facility_id' => $patient->facility_id,
            'patient_id' => $patient->id,
            'prescribed_by' => $prescriber->id,
            'status' => Prescription::STATUS_PRESCRIBED,
        ]);
        $item = PrescriptionItem::factory()->create([
            'prescription_id' => $prescription->id,
            'medication_id' => $medication->id,
            'medication_formulation_id' => $formulation->id,
            'quantity' => 14.500,
            'status' => PrescriptionItem::STATUS_PRESCRIBED,
        ]);
        $dispensing = MedicationDispensing::factory()->create([
            'facility_id' => $patient->facility_id,
            'patient_id' => $patient->id,
            'prescription_id' => $prescription->id,
            'dispensed_by' => $prescriber->id,
        ]);
        $dispensingItem = MedicationDispensingItem::factory()->create([
            'medication_dispensings_id' => $dispensing->id,
            'prescription_item_id' => $item->id,
            'quantity_dispensed' => 7.250,
        ]);

        $this->assertSame(Prescription::STATUS_PRESCRIBED, $prescription->fresh()->status);
        $this->assertSame(PrescriptionItem::STATUS_PRESCRIBED, $item->fresh()->status);
        $this->assertSame('14.500', (string) $item->fresh()->quantity);
        $this->assertSame('7.250', (string) $dispensingItem->fresh()->quantity_dispensed);
        $this->assertSame(MedicationDispensing::STATUS_COMPLETED, $dispensing->fresh()->status);
    }
}
