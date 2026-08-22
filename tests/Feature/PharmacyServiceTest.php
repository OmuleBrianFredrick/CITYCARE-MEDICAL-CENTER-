<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\Facility;
use App\Models\Medication;
use App\Models\MedicationFormulation;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Role;
use App\Models\User;
use App\Services\PharmacyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PharmacyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_active_doctor_can_prescribe_multiple_medications_on_open_encounter(): void
    {
        $doctor = $this->staffWithRole('doctor');
        $encounter = ClinicalEncounter::factory()->create([
            'clinician_id' => $doctor->id,
            'status' => ClinicalEncounter::STATUS_OPEN,
        ]);
        $medicationA = Medication::factory()->create([
            'facility_id' => $encounter->facility_id,
            'is_active' => true,
        ]);
        $medicationB = Medication::factory()->create([
            'facility_id' => $encounter->facility_id,
            'is_active' => true,
        ]);

        $prescription = app(PharmacyService::class)->prescribe($encounter, $doctor, [
            'items' => [
                ['medication_id' => $medicationA->id, 'quantity' => 10, 'dose' => '1 tablet'],
                ['medication_id' => $medicationB->id, 'quantity' => 5, 'dose' => '1 tablet'],
            ],
        ]);

        $this->assertSame(Prescription::STATUS_PRESCRIBED, $prescription->status);
        $this->assertCount(2, $prescription->items);
        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescription->id,
            'patient_id' => $encounter->patient_id,
            'encounter_id' => $encounter->id,
            'prescribed_by' => $doctor->id,
            'status' => Prescription::STATUS_PRESCRIBED,
        ]);
    }

    public function test_closed_encounter_and_inactive_staff_cannot_prescribe(): void
    {
        $service = app(PharmacyService::class);
        $doctor = $this->staffWithRole('doctor');
        $encounter = ClinicalEncounter::factory()->create([
            'clinician_id' => $doctor->id,
            'status' => ClinicalEncounter::STATUS_CLOSED,
        ]);
        $medication = Medication::factory()->create(['facility_id' => $encounter->facility_id, 'is_active' => true]);

        $this->expectException(ValidationException::class);
        $service->prescribe($encounter, $doctor, ['items' => [['medication_id' => $medication->id, 'quantity' => 1]]]);

        $inactive = $this->staffWithRole('doctor');
        $inactive->update(['is_active' => false]);
        $openEncounter = ClinicalEncounter::factory()->create(['status' => ClinicalEncounter::STATUS_OPEN]);
        $this->expectException(ValidationException::class);
        $service->prescribe($openEncounter, $inactive, ['items' => [['medication_id' => $medication->id, 'quantity' => 1]]]);
    }

    public function test_inactive_or_wrong_facility_medication_cannot_be_prescribed(): void
    {
        $service = app(PharmacyService::class);
        $doctor = $this->staffWithRole('doctor');
        $encounter = ClinicalEncounter::factory()->create(['status' => ClinicalEncounter::STATUS_OPEN]);
        $inactive = Medication::factory()->create(['facility_id' => $encounter->facility_id, 'is_active' => false]);
        $wrongFacility = Medication::factory()->create(['facility_id' => Facility::factory()->create()->id, 'is_active' => true]);

        foreach ([$inactive, $wrongFacility] as $medication) {
            try {
                $service->prescribe($encounter, $doctor, ['items' => [['medication_id' => $medication->id, 'quantity' => 1]]]);
                $this->fail('Expected invalid medication validation exception.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('items', $exception->errors());
            }
        }
    }

    public function test_invalid_quantity_and_inactive_formulation_are_rejected(): void
    {
        $service = app(PharmacyService::class);
        $doctor = $this->staffWithRole('doctor');
        $encounter = ClinicalEncounter::factory()->create(['status' => ClinicalEncounter::STATUS_OPEN]);
        $medication = Medication::factory()->create(['facility_id' => $encounter->facility_id, 'is_active' => true]);
        $formulation = MedicationFormulation::factory()->create(['medication_id' => $medication->id, 'is_active' => false]);

        try {
            $service->prescribe($encounter, $doctor, ['items' => [['medication_id' => $medication->id, 'quantity' => 0]]]);
            $this->fail('Expected quantity validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.0.quantity', $exception->errors());
        }

        try {
            $service->prescribe($encounter, $doctor, ['items' => [['medication_id' => $medication->id, 'quantity' => 1, 'medication_formulation_id' => $formulation->id]]]);
            $this->fail('Expected formulation validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.0.medication_formulation_id', $exception->errors());
        }
    }

    public function test_partial_and_full_dispensing_update_lifecycle(): void
    {
        $service = app(PharmacyService::class);
        $doctor = $this->staffWithRole('doctor');
        $pharmacist = $this->staffWithRole('pharmacy');
        $encounter = ClinicalEncounter::factory()->create(['status' => ClinicalEncounter::STATUS_OPEN]);
        $medication = Medication::factory()->create(['facility_id' => $encounter->facility_id, 'is_active' => true]);
        $prescription = $service->prescribe($encounter, $doctor, ['items' => [['medication_id' => $medication->id, 'quantity' => 10]]]);
        $item = $prescription->items->first();

        $first = $service->dispense($prescription, $pharmacist, [['prescription_item_id' => $item->id, 'quantity_dispensed' => 4]]);
        $this->assertSame(Prescription::STATUS_PARTIALLY_DISPENSED, $first->prescription->fresh()->status);
        $this->assertSame(PrescriptionItem::STATUS_PARTIALLY_DISPENSED, $item->fresh()->status);

        $second = $service->dispense($prescription->fresh(), $pharmacist, [['prescription_item_id' => $item->id, 'quantity_dispensed' => 6]]);
        $this->assertSame(Prescription::STATUS_COMPLETED, $second->prescription->fresh()->status);
        $this->assertSame(PrescriptionItem::STATUS_DISPENSED, $item->fresh()->status);
    }

    public function test_over_dispensing_and_duplicate_items_are_rejected(): void
    {
        $service = app(PharmacyService::class);
        $doctor = $this->staffWithRole('doctor');
        $pharmacist = $this->staffWithRole('pharmacy');
        $encounter = ClinicalEncounter::factory()->create(['status' => ClinicalEncounter::STATUS_OPEN]);
        $medication = Medication::factory()->create(['facility_id' => $encounter->facility_id, 'is_active' => true]);
        $prescription = $service->prescribe($encounter, $doctor, ['items' => [['medication_id' => $medication->id, 'quantity' => 5]]]);
        $item = $prescription->items->first();

        try {
            $service->dispense($prescription, $pharmacist, [['prescription_item_id' => $item->id, 'quantity_dispensed' => 6]]);
            $this->fail('Expected over-dispensing validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.0.quantity_dispensed', $exception->errors());
        }

        try {
            $service->dispense($prescription, $pharmacist, [
                ['prescription_item_id' => $item->id, 'quantity_dispensed' => 1],
                ['prescription_item_id' => $item->id, 'quantity_dispensed' => 1],
            ]);
            $this->fail('Expected duplicate-item validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }
    }

    public function test_completed_or_cancelled_prescription_cannot_be_dispensed_or_cancelled(): void
    {
        $service = app(PharmacyService::class);
        $doctor = $this->staffWithRole('doctor');
        $pharmacist = $this->staffWithRole('pharmacy');
        $encounter = ClinicalEncounter::factory()->create(['status' => ClinicalEncounter::STATUS_OPEN]);
        $medication = Medication::factory()->create(['facility_id' => $encounter->facility_id, 'is_active' => true]);
        $prescription = $service->prescribe($encounter, $doctor, ['items' => [['medication_id' => $medication->id, 'quantity' => 2]]]);
        $item = $prescription->items->first();
        $service->dispense($prescription, $pharmacist, [['prescription_item_id' => $item->id, 'quantity_dispensed' => 2]]);

        try {
            $service->dispense($prescription->fresh(), $pharmacist, [['prescription_item_id' => $item->id, 'quantity_dispensed' => 1]]);
            $this->fail('Expected completed prescription validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $second = $service->prescribe($encounter, $doctor, ['items' => [['medication_id' => $medication->id, 'quantity' => 1]]]);
        $cancelled = $service->cancelPrescription($second, $pharmacist);
        $this->assertSame(Prescription::STATUS_CANCELLED, $cancelled->status);
        $this->assertSame(PrescriptionItem::STATUS_CANCELLED, $cancelled->items->first()->status);

        try {
            $service->cancelPrescription($cancelled, $pharmacist);
            $this->fail('Expected cancelled prescription validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
    }

    private function staffWithRole(string $roleSlug): User
    {
        $user = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => true,
            'password' => Hash::make('Password123!'),
        ]);
        $user->roles()->attach(Role::where('slug', $roleSlug)->firstOrFail());
        return $user;
    }
}
