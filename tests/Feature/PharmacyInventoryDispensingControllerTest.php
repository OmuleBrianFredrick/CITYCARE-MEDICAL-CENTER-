<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\Facility;
use App\Models\InventoryItem;
use App\Models\InventoryStockBalance;
use App\Models\InventoryStore;
use App\Models\Medication;
use App\Models\MedicationFormulation;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyInventoryDispensingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_pharmacy_staff_can_dispense_through_http_and_inventory_is_deducted(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$staff, $store, $medication, $inventoryItem, $prescription] = $this->context('pharmacy', 5);

        $item = $prescription->items->first();

        $response = $this->actingAs($staff)->post(route('encounters.prescriptions.dispense', $prescription), [
            'store_id' => $store->id,
            'items' => [[
                'prescription_item_id' => $item->id,
                'quantity_dispensed' => 2,
                'batch_number' => 'BATCH-1',
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
        ]);

        $response->assertRedirect();
        $this->assertSame(3.0, (float) InventoryStockBalance::where('store_id', $store->id)->where('inventory_item_id', $inventoryItem->id)->value('quantity_available'));
        $this->assertSame(Prescription::STATUS_PARTIALLY_DISPENSED, $prescription->fresh()->status);
    }

    public function test_user_without_dispensing_permission_cannot_post_pharmacy_inventory_dispensing(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$doctor, $store, , , $prescription] = $this->context('doctor', 5);
        $item = $prescription->items->first();

        $this->actingAs($doctor)
            ->post(route('encounters.prescriptions.dispense', $prescription), [
                'store_id' => $store->id,
                'items' => [[
                    'prescription_item_id' => $item->id,
                    'quantity_dispensed' => 1,
                ]],
            ])
            ->assertForbidden();
    }

    public function test_http_validation_rejects_invalid_dispensing_payload(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$staff, $store, , , $prescription] = $this->context('pharmacy', 5);

        $this->actingAs($staff)
            ->post(route('encounters.prescriptions.dispense', $prescription), [
                'store_id' => $store->id,
                'items' => [[
                    'prescription_item_id' => $prescription->items->first()->id,
                    'quantity_dispensed' => 0,
                ]],
            ])
            ->assertSessionHasErrors('items.0.quantity_dispensed');
    }

    private function context(string $roleSlug, float $stock): array
    {
        $facility = Facility::factory()->create();
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->attach(Role::where('slug', $roleSlug)->valueOrFail('id'));

        $store = InventoryStore::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);
        $inventoryItem = InventoryItem::factory()->create([
            'facility_id' => $facility->id,
            'name' => 'Paracetamol 500mg',
            'code' => 'PCM500',
            'unit' => 'tablet',
            'is_active' => true,
        ]);
        InventoryStockBalance::factory()->create([
            'store_id' => $store->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity_on_hand' => $stock,
            'quantity_reserved' => 0,
            'quantity_available' => $stock,
            'status' => 'active',
        ]);

        $medication = Medication::factory()->create([
            'facility_id' => $facility->id,
            'name' => 'Paracetamol 500mg',
            'generic_name' => 'Paracetamol',
            'code' => 'PCM500',
            'dosage_form' => 'tablet',
            'is_active' => true,
        ]);
        $formulation = MedicationFormulation::factory()->create(['medication_id' => $medication->id, 'is_active' => true]);
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $encounter = ClinicalEncounter::factory()->create([
            'facility_id' => $facility->id,
            'patient_id' => $patient->id,
            'clinician_id' => $staff->id,
            'status' => ClinicalEncounter::STATUS_OPEN,
        ]);

        $prescription = Prescription::factory()->create([
            'facility_id' => $facility->id,
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'prescribed_by' => $staff->id,
            'status' => Prescription::STATUS_PRESCRIBED,
        ]);
        $prescription->items()->create([
            'medication_id' => $medication->id,
            'medication_formulation_id' => $formulation->id,
            'quantity' => 5,
            'status' => 'prescribed',
        ]);

        return [$staff, $store, $medication, $inventoryItem, $prescription->fresh('items')];
    }
}
