<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Facility;
use App\Models\InventoryItem;
use App\Models\InventoryStockBalance;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\InventorySupplier;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\InventoryProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryProcurementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_staff_can_create_multi_item_purchase_order(): void
    {
        [$staff, $store, $supplier, $itemA, $itemB] = $this->context();
        $order = app(InventoryProcurementService::class)->createPurchaseOrder($staff, $supplier, $store, [
            'items' => [
                ['inventory_item_id' => $itemA->id, 'quantity_ordered' => 10, 'unit_cost' => 1000],
                ['inventory_item_id' => $itemB->id, 'quantity_ordered' => 5, 'unit_cost' => 2000],
            ],
        ]);

        $this->assertSame('draft', $order->status);
        $this->assertSame(2, $order->items->count());
        $this->assertSame(20000.0, (float) $order->total);
    }

    public function test_add_item_updates_order_total_and_duplicate_item_is_rejected(): void
    {
        [$staff, $store, $supplier, $itemA, $itemB] = $this->context();
        $service = app(InventoryProcurementService::class);
        $order = $service->createPurchaseOrder($staff, $supplier, $store, [
            'items' => [['inventory_item_id' => $itemA->id, 'quantity_ordered' => 2, 'unit_cost' => 1000]],
        ]);

        $item = $service->addPurchaseOrderItem($staff, $order, [
            'inventory_item_id' => $itemB->id,
            'quantity_ordered' => 3,
            'unit_cost' => 500,
        ]);

        $this->assertSame($itemB->id, $item->inventory_item_id);
        $this->assertSame(3500.0, (float) $order->fresh()->total);

        $this->expectException(ValidationException::class);
        $service->addPurchaseOrderItem($staff, $order->fresh(), [
            'inventory_item_id' => $itemB->id,
            'quantity_ordered' => 1,
            'unit_cost' => 500,
        ]);
    }

    public function test_submission_transitions_draft_and_prevents_further_item_changes(): void
    {
        [$staff, $store, $supplier, $itemA, $itemB] = $this->context();
        $service = app(InventoryProcurementService::class);
        $order = $service->createPurchaseOrder($staff, $supplier, $store, [
            'items' => [['inventory_item_id' => $itemA->id, 'quantity_ordered' => 2, 'unit_cost' => 1000]],
        ]);

        $service->submitPurchaseOrder($staff, $order);

        $this->assertSame(PurchaseOrder::STATUS_ORDERED, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->ordered_at);

        try {
            $service->addPurchaseOrderItem($staff, $order->fresh(), [
                'inventory_item_id' => $itemB->id,
                'quantity_ordered' => 1,
                'unit_cost' => 100,
            ]);
            $this->fail('Submitted purchase orders must not accept new items.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('purchase_order', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        $service->submitPurchaseOrder($staff, $order->fresh());
    }

    public function test_inactive_supplier_cannot_be_used_for_procurement(): void
    {
        [$staff, $store, $supplier, $itemA] = $this->context();
        $supplier->update(['is_active' => false]);

        $this->expectException(ValidationException::class);
        app(InventoryProcurementService::class)->createPurchaseOrder($staff, $supplier->fresh(), $store, [
            'items' => [['inventory_item_id' => $itemA->id, 'quantity_ordered' => 1, 'unit_cost' => 1]],
        ]);
    }

    public function test_invalid_duplicate_and_cross_facility_procurement_items_are_rejected(): void
    {
        [$staff, $store, $supplier, $itemA] = $this->context();
        $service = app(InventoryProcurementService::class);

        $otherFacility = Facility::factory()->create();
        $otherItem = InventoryItem::factory()->create(['facility_id' => $otherFacility->id, 'is_active' => true]);

        try {
            $service->createPurchaseOrder($staff, $supplier, $store, [
                'items' => [
                    ['inventory_item_id' => $itemA->id, 'quantity_ordered' => 1, 'unit_cost' => 100],
                    ['inventory_item_id' => $itemA->id, 'quantity_ordered' => 1, 'unit_cost' => 100],
                ],
            ]);
            $this->fail('Duplicate procurement item should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        $service->createPurchaseOrder($staff, $supplier, $store, [
            'items' => [['inventory_item_id' => $otherItem->id, 'quantity_ordered' => 1, 'unit_cost' => 100]],
        ]);
    }

    public function test_partial_and_full_receiving_increases_stock_and_tracks_movements(): void
    {
        [$staff, $store, $supplier, $itemA] = $this->context();
        $service = app(InventoryProcurementService::class);

        $order = $service->createPurchaseOrder($staff, $supplier, $store, [
            'items' => [['inventory_item_id' => $itemA->id, 'quantity_ordered' => 10, 'unit_cost' => 1000]],
        ]);
        $service->submitPurchaseOrder($staff, $order);
        $poItem = $order->items()->first();

        $partial = $service->receiveStock($staff, $order->fresh('store'), $store, [
            'items' => [['purchase_order_item_id' => $poItem->id, 'quantity_received' => 4]],
        ]);

        $this->assertSame('partially_received', $order->fresh()->status);
        $this->assertSame(1, $partial->items()->count());
        $this->assertSame(4.0, (float) InventoryStockBalance::where('store_id', $store->id)->where('inventory_item_id', $itemA->id)->value('quantity_on_hand'));

        $service->receiveStock($staff, $order->fresh('store'), $store, [
            'items' => [['purchase_order_item_id' => $poItem->id, 'quantity_received' => 6]],
        ]);

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(10.0, (float) InventoryStockBalance::where('store_id', $store->id)->where('inventory_item_id', $itemA->id)->value('quantity_on_hand'));
        $this->assertSame(2, InventoryStockMovement::where('inventory_item_id', $itemA->id)->count());
        $this->assertSame(10.0, (float) InventoryStockMovement::where('inventory_item_id', $itemA->id)->orderByDesc('id')->value('balance_after'));
    }

    public function test_over_receiving_duplicate_receiving_and_invalid_store_are_rejected(): void
    {
        [$staff, $store, $supplier, $itemA] = $this->context();
        $service = app(InventoryProcurementService::class);
        $order = $service->createPurchaseOrder($staff, $supplier, $store, [
            'items' => [['inventory_item_id' => $itemA->id, 'quantity_ordered' => 5, 'unit_cost' => 1000]],
        ]);
        $service->submitPurchaseOrder($staff, $order);
        $poItem = $order->items()->first();

        $service->receiveStock($staff, $order->fresh('store'), $store, [
            'items' => [['purchase_order_item_id' => $poItem->id, 'quantity_received' => 3]],
        ]);

        try {
            $service->receiveStock($staff, $order->fresh('store'), $store, [
                'items' => [['purchase_order_item_id' => $poItem->id, 'quantity_received' => 3]],
            ]);
            $this->fail('Over-receiving should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity_received', $exception->errors());
        }

        $otherStore = InventoryStore::factory()->create(['facility_id' => $store->facility_id, 'is_active' => true]);
        $this->expectException(ValidationException::class);
        $service->receiveStock($staff, $order->fresh('store'), $otherStore, [
            'items' => [['purchase_order_item_id' => $poItem->id, 'quantity_received' => 2]],
        ]);
    }

    public function test_inactive_staff_or_store_cannot_perform_inventory_work(): void
    {
        [$staff, $store, $supplier, $itemA] = $this->context();
        $service = app(InventoryProcurementService::class);
        $inactiveStaff = clone $staff;
        $inactiveStaff->id = $staff->id;
        $inactiveStaff->exists = true;
        $inactiveStaff->is_active = false;

        try {
            $service->createPurchaseOrder($inactiveStaff, $supplier, $store, [
                'items' => [['inventory_item_id' => $itemA->id, 'quantity_ordered' => 1, 'unit_cost' => 1]],
            ]);
            $this->fail('Inactive staff should not create purchase orders.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('staff_id', $exception->errors());
        }

        $inactiveStore = InventoryStore::factory()->create(['facility_id' => $store->facility_id, 'is_active' => false]);
        $this->expectException(ValidationException::class);
        $service->createPurchaseOrder($staff, $supplier, $inactiveStore, [
            'items' => [['inventory_item_id' => $itemA->id, 'quantity_ordered' => 1, 'unit_cost' => 1]],
        ]);
    }

    public function test_cancelled_or_completed_orders_cannot_be_reopened_or_modified(): void
    {
        [$staff, $store, $supplier, $itemA, $itemB] = $this->context();
        $service = app(InventoryProcurementService::class);
        $order = $service->createPurchaseOrder($staff, $supplier, $store, [
            'items' => [['inventory_item_id' => $itemA->id, 'quantity_ordered' => 1, 'unit_cost' => 100]],
        ]);

        $service->cancelPurchaseOrder($staff, $order, 'Supplier unavailable.');
        $this->assertSame('cancelled', $order->fresh()->status);

        try {
            $service->addPurchaseOrderItem($staff, $order->fresh(), [
                'inventory_item_id' => $itemB->id,
                'quantity_ordered' => 1,
                'unit_cost' => 100,
            ]);
            $this->fail('Cancelled order should not be modified.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('purchase_order', $exception->errors());
        }

        $completed = $service->createPurchaseOrder($staff, $supplier, $store, [
            'items' => [['inventory_item_id' => $itemA->id, 'quantity_ordered' => 1, 'unit_cost' => 100]],
        ]);
        $completed->update(['status' => 'completed']);

        $this->expectException(ValidationException::class);
        $service->cancelPurchaseOrder($staff, $completed, 'Completed order cannot be cancelled.');
    }

    private function context(): array
    {
        $facility = Facility::factory()->create();
        $staff = $this->staffWithRole('storekeeper');
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        StaffProfile::create(['user_id' => $staff->id, 'department_id' => $department->id]);
        $store = InventoryStore::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);
        $supplier = InventorySupplier::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);
        $itemA = InventoryItem::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);
        $itemB = InventoryItem::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);

        return [$staff, $store, $supplier, $itemA, $itemB];
    }

    private function staffWithRole(string $roleSlug): User
    {
        $user = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $role = Role::where('slug', $roleSlug)->first();
        if ($role) {
            $user->roles()->attach($role);
        }

        return $user;
    }
}
