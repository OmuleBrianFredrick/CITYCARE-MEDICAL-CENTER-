<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Facility;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\InventoryStockBalance;
use App\Models\InventoryStore;
use App\Models\InventorySupplier;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\InventoryProcurementService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DataIntegrityAtomicityReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_enforces_one_stock_balance_per_store_and_inventory_item(): void
    {
        [$store, $item] = $this->context();

        InventoryStockBalance::factory()->create([
            'store_id' => $store->id,
            'inventory_item_id' => $item->id,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        InventoryStockBalance::factory()->create([
            'store_id' => $store->id,
            'inventory_item_id' => $item->id,
        ]);
    }

    public function test_failed_multi_item_receipt_rolls_back_receipt_items_and_stock_changes(): void
    {
        [$store, $item, $staff, $supplier] = $this->context();
        $service = app(InventoryProcurementService::class);

        $order = $service->createPurchaseOrder($staff, $supplier, $store, [
            'items' => [['inventory_item_id' => $item->id, 'quantity_ordered' => 10, 'unit_cost' => 100]],
        ]);
        $order->update(['status' => 'ordered']);
        $poItem = $order->items()->firstOrFail();

        try {
            $service->receiveStock($staff, $order, $store, [
                'items' => [
                    ['purchase_order_item_id' => $poItem->id, 'quantity_received' => 4],
                    ['purchase_order_item_id' => $poItem->id, 'quantity_received' => 1],
                ],
            ]);
            $this->fail('Duplicate receipt items should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $this->assertSame(0, GoodsReceipt::query()->count());
        $this->assertSame(0, GoodsReceiptItem::query()->count());
        $this->assertSame(0, InventoryStockBalance::query()->count());
    }

    private function context(): array
    {
        $facility = Facility::factory()->create();
        $staff = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => true,
        ]);
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        StaffProfile::create(['user_id' => $staff->id, 'department_id' => $department->id]);
        $store = InventoryStore::factory()->create([
            'facility_id' => $facility->id,
            'is_active' => true,
        ]);
        $supplier = InventorySupplier::factory()->create([
            'facility_id' => $facility->id,
            'is_active' => true,
        ]);
        $item = InventoryItem::factory()->create([
            'facility_id' => $facility->id,
            'is_active' => true,
        ]);

        return [$store, $item, $staff, $supplier];
    }
}
