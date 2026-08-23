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
use App\Models\PrescriptionItem;
use App\Models\Role;
use App\Models\User;
use App\Services\PharmacyInventoryService;
use App\Services\PharmacyService;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PharmacyInventoryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pharmacy_staff_can_view_mapped_stock_without_pharmacy_inventory_write_authority(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$staff, $store, $medication, $inventoryItem] = $this->context('pharmacy');

        InventoryStockBalance::factory()->create([
            'store_id' => $store->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity_on_hand' => 12,
            'quantity_reserved' => 2,
            'quantity_available' => 10,
        ]);

        $balance = app(PharmacyInventoryService::class)->stockForMedication($staff, $medication, $store);

        $this->assertSame(12.0, (float) $balance->quantity_on_hand);
        $this->assertSame(10.0, (float) $balance->quantity_available);
        $this->assertFalse($staff->hasPermissionTo('inventory.manage'));
        $this->assertTrue($staff->hasPermissionTo('inventory.view'));
    }

    public function test_dispensing_requires_sufficient_inventory_and_keeps_prescription_workflow_intact(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$staff, $store, $medication, $inventoryItem, $patient, $encounter, $formulation] = $this->context('pharmacy');

        InventoryStockBalance::factory()->create([
            'store_id' => $store->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'quantity_available' => 5,
        ]);

        $prescription = app(PharmacyService::class)->prescribe($encounter, $staff, [
            'items' => [[
                'medication_id' => $medication->id,
                'medication_formulation_id' => $formulation->id,
                'quantity' => 3,
            ]],
        ]);

        $this->expectException(ValidationException::class);
        app(PharmacyInventoryService::class)->assertDispensingStockAvailable($staff, $prescription, $store, [[
            'prescription_item_id' => $prescription->items->first()->id,
            'quantity_dispensed' => 6,
        ]]);
    }

    public function test_cross_facility_store_cannot_be_used_for_pharmacy_inventory(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$staff, $store, $medication] = $this->context('pharmacy');
        $otherStore = InventoryStore::factory()->create(['facility_id' => Facility::factory()->create()->id, 'is_active' => true]);

        $this->expectException(ValidationException::class);
        app(PharmacyInventoryService::class)->stockForMedication($staff, $medication, $otherStore);
    }

    private function context(string $roleSlug): array
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
        $medication = Medication::factory()->create([
            'facility_id' => $facility->id,
            'name' => 'Paracetamol 500mg',
            'generic_name' => 'Paracetamol',
            'code' => 'PCM500',
            'dosage_form' => 'tablet',
            'is_active' => true,
        ]);
        $formulation = MedicationFormulation::factory()->create([
            'medication_id' => $medication->id,
            'is_active' => true,
        ]);
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $encounter = ClinicalEncounter::factory()->create([
            'facility_id' => $facility->id,
            'patient_id' => $patient->id,
            'clinician_id' => $staff->id,
            'status' => ClinicalEncounter::STATUS_OPEN,
        ]);

        return [$staff, $store, $medication, $inventoryItem, $patient, $encounter, $formulation];
    }
}
