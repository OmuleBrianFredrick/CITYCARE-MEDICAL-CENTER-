<?php

namespace Tests\Feature;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\InventoryStockBalance;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\InventorySupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ServicePoint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_foundation_models_persist_with_relationships_and_constraints(): void
    {
        $item = InventoryItem::factory()->create(['reorder_level' => 25]);
        $store = InventoryStore::factory()->create(['facility_id' => $item->facility_id]);
        $servicePoint = ServicePoint::factory()->create(['department_id' => $store->facility->departments()->first()?->id]);
        $store->update(['service_point_id' => $servicePoint->id]);

        $balance = InventoryStockBalance::factory()->create([
            'store_id' => $store->id,
            'inventory_item_id' => $item->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 20,
            'quantity_available' => 80,
        ]);

        $supplier = InventorySupplier::factory()->create(['facility_id' => $item->facility_id]);
        $order = PurchaseOrder::factory()->create([
            'facility_id' => $item->facility_id,
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'subtotal' => 50000,
            'total' => 50000,
        ]);
        $orderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'inventory_item_id' => $item->id,
            'quantity_ordered' => 50,
            'unit_cost' => 1000,
            'line_total' => 50000,
        ]);

        $receipt = GoodsReceipt::factory()->create([
            'facility_id' => $item->facility_id,
            'purchase_order_id' => $order->id,
            'store_id' => $store->id,
        ]);
        $receiptItem = GoodsReceiptItem::factory()->create([
            'goods_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $orderItem->id,
            'inventory_item_id' => $item->id,
            'quantity_received' => 25,
            'unit_cost' => 1000,
            'line_total' => 25000,
        ]);
        $movement = InventoryStockMovement::factory()->create([
            'facility_id' => $item->facility_id,
            'store_id' => $store->id,
            'inventory_item_id' => $item->id,
            'goods_receipt_item_id' => $receiptItem->id,
            'movement_type' => 'receipt',
            'quantity' => 25,
            'balance_after' => 125,
        ]);

        $this->assertTrue($item->facility()->exists());
        $this->assertSame($store->id, $balance->store->id);
        $this->assertSame($item->id, $balance->inventoryItem->id);
        $this->assertSame($supplier->id, $order->supplier->id);
        $this->assertSame($store->id, $order->store->id);
        $this->assertSame($item->id, $orderItem->inventoryItem->id);
        $this->assertSame($order->id, $receipt->purchaseOrder->id);
        $this->assertSame($orderItem->id, $receiptItem->purchaseOrderItem->id);
        $this->assertSame($receiptItem->id, $movement->goodsReceiptItem->id);
        $this->assertSame($item->id, $movement->inventoryItem->id);
        $this->assertSame(25.0, (float) $item->fresh()->reorder_level);
        $this->assertSame(80.0, (float) $balance->fresh()->quantity_available);
    }

    public function test_inventory_item_code_and_sku_are_unique_per_facility(): void
    {
        $first = InventoryItem::factory()->create(['code' => 'ITEM-001', 'sku' => 'SKU-001']);
        $this->expectException(UniqueConstraintViolationException::class);
        InventoryItem::factory()->create([
            'facility_id' => $first->facility_id,
            'code' => 'ITEM-001',
            'sku' => 'SKU-002',
        ]);
    }

    public function test_stock_balance_is_unique_per_store_and_item(): void
    {
        $item = InventoryItem::factory()->create();
        $store = InventoryStore::factory()->create(['facility_id' => $item->facility_id]);
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
}
