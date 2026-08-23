<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\Facility;
use App\Models\InventoryItem;
use App\Models\InventoryStockBalance;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\Medication;
use App\Models\MedicationFormulation;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\PharmacyInventoryDispensingService;
use App\Services\PharmacyService;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PharmacyInventoryDispensingTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_dispensing_deducts_stock_and_records_issue_movement(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$staff, $store, $medication, $inventoryItem, $patient, $encounter, $formulation] = $this->context();

        InventoryStockBalance::factory()->create([
            'store_id' => $store->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'quantity_available' => 10,
        ]);

        $prescription = app(PharmacyService::class)->prescribe($encounter, $staff, [
            'items' => [[
                'medication_id' => $medication->id,
                'medication_formulation_id' => $formulation->id,
                'quantity' => 4,
            ]],
        ]);

        $dispensing = app(PharmacyInventoryDispensingService::class)->dispenseWithInventory(
            $prescription,
            $staff,
            $store,
            [[
                'prescription_item_id' => $prescription->items->first()->id,
                'quantity_dispensed' => 4,
            ]]
        );

        $balance = InventoryStockBalance::where('store_id', $store->id)->where('inventory_item_id', $inventoryItem->id)->firstOrFail();
        $this->assertSame(6.0, (float) $balance->quantity_on_hand);
        $this->assertSame(6.0, (float) $balance->quantity_available);
        $this->assertSame('completed', $prescription->fresh()->status);
        $this->assertSame(-4.0, (float) InventoryStockMovement::where('reference_id', $dispensing->id)->value('quantity'));
        $this->assertSame(6.0, (float) InventoryStockMovement::where('reference_id', $dispensing->id)->value('balance_after'));
    }

    public function test_insufficient_stock_rejects_dispensing_without_changing_stock_or_prescription(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$staff, $store, $medication, $inventoryItem, $patient, $encounter, $formulation] = $this->context();

        InventoryStockBalance::factory()->create([
            'store_id' => $store->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity_on_hand' => 2,
            'quantity_reserved' => 0,
            'quantity_available' => 2,
        ]);

        $prescription = app(PharmacyService::class)->prescribe($encounter, $staff, [
            'items' => [[
                'medication_id' => $medication->id,
                'medication_formulation_id' => $formulation->id,
                'quantity' => 4,
            ]],
        ]);

        $this->expectException(ValidationException::class);
        try {
            app(PharmacyInventoryDispensingService::class)->dispenseWithInventory(
                $prescription,
                $staff,
                $store,
                [[
                    'prescription_item_id' => $prescription->items->first()->id,
                    'quantity_dispensed' => 4,
                ]]
            );
        } finally {
            $balance = InventoryStockBalance::where('store_id', $store->id)->where('inventory_item_id', $inventoryItem->id)->firstOrFail();
            $this->assertSame(2.0, (float) $balance->quantity_on_hand);
            $this->assertSame('prescribed', $prescription->fresh()->status);
            $this->assertSame(0, InventoryStockMovement::where('inventory_item_id', $inventoryItem->id)->count());
        }
    }

    public function test_partial_dispensing_deducts_each_issue_and_completes_on_final_quantity(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$staff, $store, $medication, $inventoryItem, $patient, $encounter, $formulation] = $this->context();

        InventoryStockBalance::factory()->create([
            'store_id' => $store->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'quantity_available' => 10,
        ]);

        $prescription = app(PharmacyService::class)->prescribe($encounter, $staff, [
            'items' => [[
                'medication_id' => $medication->id,
                'medication_formulation_id' => $formulation->id,
                'quantity' => 6,
            ]],
        ]);
        $itemId = $prescription->items->first()->id;
        $service = app(PharmacyInventoryDispensingService::class);

        $service->dispenseWithInventory($prescription, $staff, $store, [[
            'prescription_item_id' => $itemId,
            'quantity_dispensed' => 2,
        ]]);

        $this->assertSame('partially_dispensed', $prescription->fresh()->status);
        $this->assertSame(8.0, (float) InventoryStockBalance::where('store_id', $store->id)->where('inventory_item_id', $inventoryItem->id)->value('quantity_on_hand'));

        $service->dispenseWithInventory($prescription->fresh(), $staff, $store, [[
            'prescription_item_id' => $itemId,
            'quantity_dispensed' => 4,
        ]]);

        $this->assertSame('completed', $prescription->fresh()->status);
        $this->assertSame(4.0, (float) InventoryStockBalance::where('store_id', $store->id)->where('inventory_item_id', $inventoryItem->id)->value('quantity_on_hand'));
        $this->assertSame(2, InventoryStockMovement::where('inventory_item_id', $inventoryItem->id)->count());
    }

    private function context(): array
    {
        $facility = Facility::factory()->create();
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->attach(Role::where('slug', 'pharmacy')->valueOrFail('id'));

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
