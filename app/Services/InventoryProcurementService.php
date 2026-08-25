<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\InventoryStockBalance;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\InventorySupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryProcurementService
{
    public function __construct(private readonly FacilityAccessService $facilities) {}

    public function createPurchaseOrder(User $staff, InventorySupplier $supplier, InventoryStore $store, array $data): PurchaseOrder
    {
        $this->assertActiveStaff($staff);
        $this->assertActiveStore($store);
        $this->assertActiveSupplier($supplier);
        $this->assertFacilityAccess($staff, $store->facility_id);
        $this->assertSameFacility($supplier->facility_id, $store->facility_id, 'Supplier and store must belong to the same facility.');

        $items = $data['items'] ?? [];
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'At least one procurement item is required.']);
        }

        return DB::transaction(function () use ($staff, $supplier, $store, $data, $items) {
            $order = PurchaseOrder::create([
                'facility_id' => $store->facility_id,
                'supplier_id' => $supplier->id,
                'store_id' => $store->id,
                'created_by_id' => $staff->id,
                'order_number' => $this->nextOrderNumber(),
                'status' => PurchaseOrder::STATUS_DRAFT,
                'ordered_at' => null,
                'notes' => $data['notes'] ?? null,
                'subtotal' => 0,
                'total' => 0,
            ]);

            $subtotal = 0.0;
            $seen = [];
            foreach ($items as $itemData) {
                $itemId = (int) ($itemData['inventory_item_id'] ?? 0);
                $quantity = (float) ($itemData['quantity_ordered'] ?? 0);
                $unitCost = (float) ($itemData['unit_cost'] ?? 0);
                $this->assertPositive($quantity, 'quantity_ordered');
                $this->assertNonNegative($unitCost, 'unit_cost');
                if (isset($seen[$itemId])) {
                    throw ValidationException::withMessages(['items' => 'Duplicate inventory items are not allowed in a purchase order.']);
                }
                $seen[$itemId] = true;

                $inventoryItem = InventoryItem::query()->find($itemId);
                if (! $inventoryItem || ! $inventoryItem->is_active || $inventoryItem->facility_id !== $store->facility_id) {
                    throw ValidationException::withMessages(['inventory_item_id' => 'The selected inventory item is invalid, inactive, or belongs to another facility.']);
                }

                $lineTotal = round($quantity * $unitCost, 2);
                $subtotal += $lineTotal;
                $order->items()->create([
                    'inventory_item_id' => $inventoryItem->id,
                    'quantity_ordered' => $quantity,
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                ]);
            }

            $order->update(['subtotal' => round($subtotal, 2), 'total' => round($subtotal, 2)]);

            return $order->load('items');
        });
    }

    public function addPurchaseOrderItem(User $staff, PurchaseOrder $order, array $data): PurchaseOrderItem
    {
        $this->assertActiveStaff($staff);
        $this->assertFacilityAccess($staff, $order->facility_id);

        $quantity = (float) ($data['quantity_ordered'] ?? 0);
        $unitCost = (float) ($data['unit_cost'] ?? 0);
        $this->assertPositive($quantity, 'quantity_ordered');
        $this->assertNonNegative($unitCost, 'unit_cost');
        $lineTotal = round($quantity * $unitCost, 2);

        return DB::transaction(function () use ($order, $data, $quantity, $unitCost, $lineTotal) {
            $order = PurchaseOrder::query()->with('store')->lockForUpdate()->findOrFail($order->id);
            $this->assertEditableOrder($order);
            $this->assertActiveStore($order->store);

            $inventoryItem = InventoryItem::query()->find($data['inventory_item_id'] ?? null);
            if (! $inventoryItem || ! $inventoryItem->is_active || $inventoryItem->facility_id !== $order->facility_id) {
                throw ValidationException::withMessages(['inventory_item_id' => 'The selected inventory item is invalid, inactive, or belongs to another facility.']);
            }
            if ($order->items()->where('inventory_item_id', $inventoryItem->id)->exists()) {
                throw ValidationException::withMessages(['inventory_item_id' => 'This inventory item is already present on the purchase order.']);
            }

            $item = $order->items()->create([
                'inventory_item_id' => $inventoryItem->id,
                'quantity_ordered' => $quantity,
                'unit_cost' => $unitCost,
                'line_total' => $lineTotal,
            ]);
            $this->recalculateOrder($order->fresh('items'));

            return $item->refresh();
        }, 3);
    }

    public function submitPurchaseOrder(User $staff, PurchaseOrder $order): PurchaseOrder
    {
        $this->assertActiveStaff($staff);
        $this->assertFacilityAccess($staff, $order->facility_id);

        return DB::transaction(function () use ($order) {
            $order = PurchaseOrder::query()
                ->with(['items', 'store', 'supplier'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            if (! $order->isDraft()) {
                throw ValidationException::withMessages(['purchase_order' => 'Only draft purchase orders can be submitted.']);
            }
            if ($order->items->isEmpty()) {
                throw ValidationException::withMessages(['purchase_order' => 'A purchase order must contain at least one item before submission.']);
            }

            $this->assertActiveStore($order->store);
            $this->assertActiveSupplier($order->supplier);

            $order->update([
                'status' => PurchaseOrder::STATUS_ORDERED,
                'ordered_at' => now()->toDateString(),
            ]);

            return $order->refresh();
        }, 3);
    }

    public function receiveStock(User $staff, PurchaseOrder $order, InventoryStore $store, array $data): GoodsReceipt
    {
        $this->assertActiveStaff($staff);
        $this->assertActiveStore($store);
        $this->assertFacilityAccess($staff, $order->facility_id);
        if ($store->facility_id !== $order->facility_id || $store->id !== $order->store_id) {
            throw ValidationException::withMessages(['store_id' => 'The receiving store must match the purchase order store and facility.']);
        }
        if (! in_array($order->status, [PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED], true)) {
            throw ValidationException::withMessages(['purchase_order' => 'Only open purchase orders can receive stock.']);
        }

        $items = $data['items'] ?? [];
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'At least one receiving item is required.']);
        }

        return DB::transaction(function () use ($staff, $order, $store, $items, $data) {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            if (! in_array($order->status, [PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED], true)) {
                throw ValidationException::withMessages(['purchase_order' => 'Only open purchase orders can receive stock.']);
            }

            $receipt = GoodsReceipt::create([
                'facility_id' => $order->facility_id,
                'purchase_order_id' => $order->id,
                'store_id' => $store->id,
                'received_by_id' => $staff->id,
                'receipt_number' => $this->nextReceiptNumber(),
                'status' => 'posted',
                'received_at' => $data['received_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $seen = [];
            foreach ($items as $itemData) {
                $poItem = PurchaseOrderItem::query()
                    ->where('purchase_order_id', $order->id)
                    ->whereKey($itemData['purchase_order_item_id'] ?? null)
                    ->lockForUpdate()
                    ->first();
                if (! $poItem) {
                    throw ValidationException::withMessages(['purchase_order_item_id' => 'The purchase order item is invalid.']);
                }
                if (isset($seen[$poItem->id])) {
                    throw ValidationException::withMessages(['items' => 'A purchase order item cannot be received twice on the same receipt.']);
                }
                $seen[$poItem->id] = true;

                $quantity = (float) ($itemData['quantity_received'] ?? 0);
                $this->assertPositive($quantity, 'quantity_received');
                $alreadyReceived = (float) GoodsReceiptItem::query()->where('purchase_order_item_id', $poItem->id)->sum('quantity_received');
                $remaining = round((float) $poItem->quantity_ordered - $alreadyReceived, 3);
                if ($quantity > $remaining) {
                    throw ValidationException::withMessages(['quantity_received' => 'Received quantity cannot exceed the remaining ordered quantity.']);
                }

                $inventoryItem = $poItem->inventoryItem;
                if (! $inventoryItem || ! $inventoryItem->is_active) {
                    throw ValidationException::withMessages(['inventory_item_id' => 'The inventory item is inactive or unavailable.']);
                }
                $unitCost = (float) ($itemData['unit_cost'] ?? $poItem->unit_cost);
                $this->assertNonNegative($unitCost, 'unit_cost');
                $lineTotal = round($quantity * $unitCost, 2);

                $receiptItem = $receipt->items()->create([
                    'purchase_order_item_id' => $poItem->id,
                    'inventory_item_id' => $inventoryItem->id,
                    'quantity_received' => $quantity,
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                ]);

                $balance = InventoryStockBalance::query()
                    ->where('store_id', $store->id)
                    ->where('inventory_item_id', $inventoryItem->id)
                    ->lockForUpdate()
                    ->first();

                if (! $balance) {
                    InventoryStockBalance::create([
                        'store_id' => $store->id,
                        'inventory_item_id' => $inventoryItem->id,
                        'quantity_on_hand' => 0,
                        'quantity_reserved' => 0,
                        'quantity_available' => 0,
                        'status' => 'active',
                    ]);

                    $balance = InventoryStockBalance::query()
                        ->where('store_id', $store->id)
                        ->where('inventory_item_id', $inventoryItem->id)
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                $newOnHand = round((float) $balance->quantity_on_hand + $quantity, 3);
                $available = round($newOnHand - (float) $balance->quantity_reserved, 3);
                $balance->update(['quantity_on_hand' => $newOnHand, 'quantity_available' => max(0, $available), 'status' => 'active']);

                InventoryStockMovement::create([
                    'facility_id' => $order->facility_id,
                    'store_id' => $store->id,
                    'inventory_item_id' => $inventoryItem->id,
                    'goods_receipt_item_id' => $receiptItem->id,
                    'performed_by_id' => $staff->id,
                    'movement_type' => 'receipt',
                    'quantity' => $quantity,
                    'balance_after' => $newOnHand,
                    'reference_type' => GoodsReceipt::class,
                    'reference_id' => $receipt->id,
                    'notes' => $data['notes'] ?? null,
                ]);
            }

            $this->refreshPurchaseOrderStatus($order->fresh('items'));

            return $receipt->load('items');
        }, 3);
    }

    public function cancelPurchaseOrder(User $staff, PurchaseOrder $order, string $reason): PurchaseOrder
    {
        $this->assertActiveStaff($staff);
        $this->assertFacilityAccess($staff, $order->facility_id);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A cancellation reason is required.']);
        }

        return DB::transaction(function () use ($order, $reason) {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            if (! in_array($order->status, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_ORDERED], true)) {
                throw ValidationException::withMessages(['purchase_order' => 'Only draft or unreceived ordered purchase orders can be cancelled.']);
            }
            if ($order->goodsReceipts()->exists()) {
                throw ValidationException::withMessages(['purchase_order' => 'A purchase order with received stock cannot be cancelled.']);
            }

            $order->update([
                'status' => PurchaseOrder::STATUS_CANCELLED,
                'notes' => trim(($order->notes ? $order->notes."\n" : '').'Cancellation: '.$reason),
            ]);

            return $order->refresh();
        }, 3);
    }

    private function refreshPurchaseOrderStatus(PurchaseOrder $order): void
    {
        $fullyReceived = $order->items->every(function (PurchaseOrderItem $item) {
            $received = (float) GoodsReceiptItem::query()->where('purchase_order_item_id', $item->id)->sum('quantity_received');

            return $received >= (float) $item->quantity_ordered;
        });
        $hasReceipts = $order->items->contains(function (PurchaseOrderItem $item) {
            return GoodsReceiptItem::query()->where('purchase_order_item_id', $item->id)->exists();
        });

        $order->update(['status' => $fullyReceived ? PurchaseOrder::STATUS_COMPLETED : ($hasReceipts ? PurchaseOrder::STATUS_PARTIALLY_RECEIVED : $order->status)]);
    }

    private function recalculateOrder(PurchaseOrder $order): void
    {
        $subtotal = round($order->items->sum(fn (PurchaseOrderItem $item) => (float) $item->line_total), 2);
        $order->update(['subtotal' => $subtotal, 'total' => $subtotal]);
    }

    private function assertActiveStaff(User $staff): void
    {
        if (! $staff->isStaff() || ! $staff->isActive()) {
            throw ValidationException::withMessages(['staff_id' => 'Only active staff can perform inventory and procurement operations.']);
        }
    }

    private function assertActiveStore(InventoryStore $store): void
    {
        if (! $store->is_active) {
            throw ValidationException::withMessages(['store_id' => 'The selected store is inactive.']);
        }
    }

    private function assertActiveSupplier(InventorySupplier $supplier): void
    {
        if (! $supplier->is_active) {
            throw ValidationException::withMessages(['supplier_id' => 'The selected supplier is inactive.']);
        }
    }

    private function assertSameFacility(int $leftFacilityId, int $rightFacilityId, string $message): void
    {
        if ($leftFacilityId !== $rightFacilityId) {
            throw ValidationException::withMessages(['facility_id' => $message]);
        }
    }

    private function assertFacilityAccess(User $staff, int $facilityId): void
    {
        $this->facilities->assertFacilityAccessible($staff, $facilityId);
    }

    private function assertEditableOrder(PurchaseOrder $order): void
    {
        if (! $order->isDraft()) {
            throw ValidationException::withMessages(['purchase_order' => 'Only draft purchase orders can be modified.']);
        }
    }

    private function assertPositive(float $value, string $field): void
    {
        if ($value <= 0) {
            throw ValidationException::withMessages([$field => 'The value must be greater than zero.']);
        }
    }

    private function assertNonNegative(float $value, string $field): void
    {
        if ($value < 0) {
            throw ValidationException::withMessages([$field => 'The value cannot be negative.']);
        }
    }

    private function nextOrderNumber(): string
    {
        do {
            $number = 'PO-'.now()->format('Ymd').'-'.str()->upper(str()->random(6));
        } while (PurchaseOrder::query()->where('order_number', $number)->exists());

        return $number;
    }

    private function nextReceiptNumber(): string
    {
        do {
            $number = 'GRN-'.now()->format('Ymd').'-'.str()->upper(str()->random(6));
        } while (GoodsReceipt::query()->where('receipt_number', $number)->exists());

        return $number;
    }
}
