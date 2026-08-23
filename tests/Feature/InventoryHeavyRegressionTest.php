<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\Facility;
use App\Models\InventoryItem;
use App\Models\InventoryStockBalance;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\InventorySupplier;
use App\Models\Medication;
use App\Models\MedicationFormulation;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryProcurementService;
use App\Services\PharmacyInventoryDispensingService;
use App\Services\PharmacyService;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryHeavyRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_item_procurement_receiving_preserves_balances_movements_and_lifecycle(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$staff, $facility, $store, $supplier, $itemA, $itemB] = $this->inventoryContext('inventory');
        $service = app(InventoryProcurementService::class);

        $order = $service->createPurchaseOrder($staff, $supplier, $store, [
            'items' => [
                ['inventory_item_id' => $itemA->id, 'quantity_ordered' => 10, 'unit_cost' => 1000],
                ['inventory_item_id' => $itemB->id, 'quantity_ordered' => 6, 'unit_cost' => 2000],
            ],
        ]);

        $order->update(['status' => 'ordered']);
        $service->receiveStock($staff, $order, $store, [
            'items' => [
                ['purchase_order_item_id' => $order->items()->where('inventory_item_id', $itemA->id)->value('id'), 'quantity_received' => 4],
                ['purchase_order_item_id' => $order->items()->where('inventory_item_id', $itemB->id)->value('id'), 'quantity_received' => 6],
            ],
        ]);

        $this->assertSame('partially_received', $order->fresh()->status);

        $service->receiveStock($staff, $order->fresh(), $store, [
            'items' => [
                ['purchase_order_item_id' => $order->items()->where('inventory_item_id', $itemA->id)->value('id'), 'quantity_received' => 6],
            ],
        ]);

        $order = $order->fresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame(10.0, (float) InventoryStockBalance::where(['store_id' => $store->id, 'inventory_item_id' => $itemA->id])->value('quantity_on_hand'));
        $this->assertSame(6.0, (float) InventoryStockBalance::where(['store_id' => $store->id, 'inventory_item_id' => $itemB->id])->value('quantity_on_hand'));
        $this->assertSame(3, InventoryStockMovement::where('store_id', $store->id)->count());
    }

    public function test_invalid_receiving_states_leave_inventory_unchanged(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$staff, $facility, $store, $supplier, $itemA] = $this->inventoryContext('inventory');
        $service = app(InventoryProcurementService::class);
        $order = $service->createPurchaseOrder($staff, $supplier, $store, [
            'items' => [['inventory_item_id' => $itemA->id, 'quantity_ordered' => 5, 'unit_cost' => 1000]],
        ]);
        $order->update(['status' => 'ordered']);
        $poItemId = $order->items()->value('id');

        $this->expectException(ValidationException::class);
        try {
            $service->receiveStock($staff, $order, $store, [
                'items' => [['purchase_order_item_id' => $poItemId, 'quantity_received' => 6]],
            ]);
        } finally {
            $balance = InventoryStockBalance::where(['store_id' => $store->id, 'inventory_item_id' => $itemA->id])->first();
            $this->assertNull($balance);
        }
    }

    public function test_inactive_and_cross_facility_inventory_access_is_rejected(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$staff, $facility, $store, $supplier, $itemA] = $this->inventoryContext('inventory');
        $service = app(InventoryProcurementService::class);
        $otherFacility = Facility::factory()->create();
        $otherStore = InventoryStore::factory()->create(['facility_id' => $otherFacility->id, 'is_active' => true]);
        $order = $service->createPurchaseOrder($staff, $supplier, $store, [
            'items' => [['inventory_item_id' => $itemA->id, 'quantity_ordered' => 2, 'unit_cost' => 1000]],
        ]);

        $this->expectException(ValidationException::class);
        $service->receiveStock($staff, $order, $otherStore, [
            'items' => [['purchase_order_item_id' => $order->items()->value('id'), 'quantity_received' => 1]],
        ]);
    }

    public function test_pharmacy_dispensing_consumes_stock_and_records_negative_issue_movement(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$staff, $facility, $store, $supplier, $inventoryItem] = $this->inventoryContext('pharmacy');

        $medication = Medication::factory()->create([
            'facility_id' => $facility->id,
            'name' => $inventoryItem->name,
            'generic_name' => 'Test medication',
            'code' => $inventoryItem->code,
            'dosage_form' => $inventoryItem->unit,
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

        app(PharmacyInventoryDispensingService::class)->dispenseWithInventory(
            $prescription,
            $staff,
            $store,
            [['prescription_item_id' => $prescription->items->first()->id, 'quantity_dispensed' => 3]],
        );

        $balance = InventoryStockBalance::where(['store_id' => $store->id, 'inventory_item_id' => $inventoryItem->id])->firstOrFail();
        $movement = InventoryStockMovement::where(['store_id' => $store->id, 'inventory_item_id' => $inventoryItem->id])->latest('id')->firstOrFail();
        $this->assertSame(2.0, (float) $balance->quantity_on_hand);
        $this->assertSame(-3.0, (float) $movement->quantity);
        $this->assertSame('issue', $movement->movement_type);
        $this->assertSame(Prescription::STATUS_COMPLETED, $prescription->fresh()->status);
    }

    public function test_insufficient_pharmacy_stock_produces_no_partial_mutation(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$staff, $facility, $store, $supplier, $inventoryItem] = $this->inventoryContext('pharmacy');
        $medication = Medication::factory()->create([
            'facility_id' => $facility->id,
            'name' => $inventoryItem->name,
            'generic_name' => 'Test medication',
            'code' => $inventoryItem->code,
            'dosage_form' => $inventoryItem->unit,
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
        InventoryStockBalance::factory()->create([
            'store_id' => $store->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity_on_hand' => 2,
            'quantity_reserved' => 0,
            'quantity_available' => 2,
        ]);
        $prescription = app(PharmacyService::class)->prescribe($encounter, $staff, [
            'items' => [['medication_id' => $medication->id, 'medication_formulation_id' => $formulation->id, 'quantity' => 3]],
        ]);

        $this->expectException(ValidationException::class);
        try {
            app(PharmacyInventoryDispensingService::class)->dispenseWithInventory(
                $prescription,
                $staff,
                $store,
                [['prescription_item_id' => $prescription->items->first()->id, 'quantity_dispensed' => 3]],
            );
        } finally {
            $balance = InventoryStockBalance::where(['store_id' => $store->id, 'inventory_item_id' => $inventoryItem->id])->firstOrFail();
            $this->assertSame(2.0, (float) $balance->quantity_on_hand);
            $this->assertSame(0, InventoryStockMovement::where(['store_id' => $store->id, 'inventory_item_id' => $inventoryItem->id])->count());
            $this->assertSame(Prescription::STATUS_PRESCRIBED, $prescription->fresh()->status);
        }
    }

    private function inventoryContext(string $roleSlug): array
    {
        $facility = Facility::factory()->create();
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->attach(Role::where('slug', $roleSlug)->valueOrFail('id'));
        $store = InventoryStore::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);
        $supplier = InventorySupplier::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);
        $itemA = InventoryItem::factory()->create(['facility_id' => $facility->id, 'is_active' => true, 'unit' => 'unit']);
        return [$staff, $facility, $store, $supplier, $itemA, InventoryItem::factory()->create(['facility_id' => $facility->id, 'is_active' => true, 'unit' => 'unit'])];
    }
}
