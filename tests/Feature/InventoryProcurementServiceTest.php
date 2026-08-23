<?php

namespace Tests\Feature;

use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\InventoryStockBalance;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\InventorySupplier;
use App\Models\PurchaseOrder;
use App\Services\InventoryProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryProcurementServiceTest extends TestCase
{
    use RefreshDatabase;

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
        $this->assertSame(2, InventoryStockMovement::where('inventory_item_id', $itemA->id)->count());
        $this->assertSame(10.0, (float) InventoryStockMovement::where('inventory_item_id', $itemA->id)->orderByDesc('id')->value('balance_after'));
    }

    private function context(): array
    {
        $facility = \App\Models\Facility::factory()->create();
        $staff = $this->staffWithRole('storekeeper');
        $staff->update(['facility_id' => $facility->id]);
        $store = InventoryStore::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);
        $supplier = InventorySupplier::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);
        $itemA = InventoryItem::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);

        return [$staff, $store, $supplier, $itemA];
    }

    private function staffWithRole(string $roleSlug): \App\Models\User
    {
        $user = \App\Models\User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $role = \App\Models\Role::where('slug', $roleSlug)->first();
        if ($role) {
            $user->roles()->attach($role);
        }
        return $user;
    }
}
