<?php

namespace Tests\Feature;

use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\InventoryStockBalance;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\InventorySupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Services\InventoryProcurementService;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryProcurementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CityCareAccessSeeder::class);
    }

    public function test_active_staff_can_create_multi_item_purchase_order(): void
    {
        [$staff, $store, $supplier, $itemA, $itemB] = $this->context();
        $service = app(InventoryProcurementService::class);

        $order = $service->createPurchaseOrder($staff, $supplier, $store, [
            'items' => [
                ['inventory_item_id' => $itemA->id, 'quantity_ordered' => 5, 'unit_cost' => 1000],
                ['inventory_item_id' => $itemB->id, 'quantity_ordered' => 2, 'unit_cost' => 5000],
            ],
        ]);

        $this->assertSame(15000.0, (float) $order->total);
        $this->assertCount(2, $order->items);
        $this->assertSame('draft', $order->status);
    }

    public function test_invalid_duplicate_and_cross_facility_procurement_items_are_rejected(): void
    {
        [$staff, $store, $supplier, $itemA] = $this->context();
        $service = app(InventoryProcurementService::class);

        $cases = [
            ['items' => [['inventory_item_id' => $itemA->id, 'quantity_ordered' => 0, 'unit_cost' => 1]]],
            ['items' => [
                ['inventory_item_id' => $itemA->id, 'quantity_ordered' => 1, 'unit_cost' => 1],
                ['inventory_item_id' => $itemA->id, 'quantity_ordered' => 1, 'unit_cost' => 1],
            ]],
        ];

        foreach ($cases as $data) {
            try {
                $service->createPurchaseOrder($staff, $supplier, $store, $data);
                $this->fail('Expected procurement validation exception.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        $otherStore = InventoryStore::factory()->create();
        $otherSupplier = InventorySupplier::factory()->create(['facility_id' => $otherStore->facility_id]);

        $this->expectException(ValidationException::class);
        $service->createPurchaseOrder($staff, $otherSupplier, $store, [
            'items' => [['inventory_item_id' => $itemA->id, 'quantity_ordered' => 1, 'unit_cost' => 1]],
        ]);
    }

    public function test_partial_and_full_receiving_increases_stock_and_tracks_movements(): void
    {
        [$staff, $store, $supplier, $itemA] = $this->context();
        $service = app(InventoryProcurementService::class);

        $order = $service->createPurchaseOrder($staff, $supplier, $store, [
            'items' => [['inventory_item_id' => $itemA->id, 'quantity_ordered' => 10, 'unit_cost' => 1000]],
        ]);
        $order->update(['status' => 'ordered']);
        $poItem = $order->items()->first();

        $partial = $service->receiveStock($staff, $order->fresh('store'), $store, [
            'items' => [['purchase_order_item_id' => $poItem->id, 'quantity_received' => 4]],
        ]);

        $this->assertSame('partially_received', $order->fresh()->status);
        $this->assertSame(1, $partial->items()->count());
        $this->assertSame(4.0, (float) InventoryStockBalance::where('store_id', $store->id)->where('inventory_item_id', $itemA->id)->value('quantity_on_hand'));
        $this->assertSame(4.0, (float) InventoryStockMovement::where('goods_receipt_item_id', $partial->items()->first()->id)->value('quantity'));

        $service->receiveStock($staff, $order->fresh('store'), $store, [
            'items' => [['purchase_order_item_id' => $poItem->id, 'quantity_received' => 6]],
        ]);

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(10.0, (float) InventoryStockBalance::where('store_id', $store->id)->where('inventory_item_id', $itemA->id)->value('quantity_on_hand'));
        $this->assertSame(10, InventoryStockMovement::where('inventory_item_id', $itemA->id)->count());
    }

    public function test_over_receiving_duplicate_receiving_and_invalid_store_are_rejected(): void
    {
        [$staff, $store, $supplier, $itemA] = $this->context();
        $service = app(InventoryProcurementService::class);

        $order = $service->createPurchaseOrder($staff, $supplier, $store, [
            'items' => [['inventory_item_id' => $itemA->id, 'quantity_ordered' => 5, 'unit_cost' => 1000]],
        ]);
        $order->update(['status' => 'ordered']);
        $poItem = $order->items()->first();

        $service->receiveStock($staff, $order, $store, [
            'items' => [['purchase_order_item_id' => $poItem->id, 'quantity_received' => 3]],
        ]);

        try {
            $service->receiveStock($staff, $order->fresh(), $store, [
                'items' => [['purchase_order_item_id' => $poItem->id, 'quantity_received' => 3]],
            ]);
            $this->fail('Over-receiving should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }

        $otherStore = InventoryStore::factory()->create(['facility_id' => $store->facility_id, 'is_active' => true]);
        $this->expectException(ValidationException::class);
        $service->receiveStock($staff, $order->fresh(), $otherStore, [
            'items' => [['purchase_order_item_id' => $poItem->id, 'quantity_received' => 1]],
        ]);
    }

    public function test_inactive_staff_or_store_cannot_perform_inventory_work(): void
    {
        [$staff, $store, $supplier, $itemA] = $this->context();
        $inactive = User::factory()->create(['user_type' => 'staff', 'is_active' => false]);
        $inactiveStore = InventoryStore::factory()->create(['facility_id' => $store->facility_id, 'is_active' => false]);
        $service = app(InventoryProcurementService::class);

        $this->expectException(ValidationException::class);
        $service->createPurchaseOrder($inactive, $supplier, $store, [
            'items' => [['inventory_item_id' => $itemA->id, 'quantity_ordered' => 1, 'unit_cost' => 1000]],
        ]);

        $order = $service->createPurchaseOrder($staff, $supplier, $store, [
            'items' => [['inventory_item_id' => $itemA->id, 'quantity_ordered' => 1, 'unit_cost' => 1000]],
        ]);
        $order->update(['status' => 'ordered']);

        try {
            $service->receiveStock($staff, $order, $inactiveStore, [
                'items' => [['purchase_order_item_id' => $order->items()->first()->id, 'quantity_received' => 1]],
            ]);
            $this->fail('Inactive store should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_cancelled_or_completed_orders_cannot_be_reopened_or_modified(): void
    {
        [$staff, $store, $supplier, $itemA] = $this->context();
        $service = app(InventoryProcurementService::class);

        $order = $service->createPurchaseOrder($staff, $supplier, $store, [
            'items' => [['inventory_item_id' => $itemA->id, 'quantity_ordered' => 1, 'unit_cost' => 1000]],
        ]);
        $cancelled = $service->cancelPurchaseOrder($staff, $order, 'Supplier cancelled order.');
        $this->assertSame('cancelled', $cancelled->status);

        try {
            $service->addPurchaseOrderItem($staff, $cancelled, ['inventory_item_id' => $itemA->id, 'quantity_ordered' => 1, 'unit_cost' => 1000]);
            $this->fail('Cancelled order should not be editable.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    private function context(): array
    {
        $facility = \App\Models\Facility::factory()->create();
        $department = \App\Models\Department::factory()->create(['facility_id' => $facility->id]);
        $servicePoint = \App\Models\ServicePoint::factory()->create(['department_id' => $department->id]);
        $store = InventoryStore::factory()->create(['facility_id' => $facility->id, 'service_point_id' => $servicePoint->id]);
        $supplier = InventorySupplier::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);
        $itemA = InventoryItem::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);
        $itemB = InventoryItem::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);
        $staff = $this->staff();
        return [$staff, $store, $supplier, $itemA, $itemB];
    }

    private function staff(): User
    {
        $user = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $user->roles()->attach(\App\Models\Role::where('slug', 'administrator')->firstOrFail());
        return $user;
    }
}
