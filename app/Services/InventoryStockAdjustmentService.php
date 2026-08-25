<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryStockBalance;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryStockAdjustmentService
{
    public function __construct(private readonly FacilityAccessService $facilities) {}

    public function adjust(
        User $staff,
        InventoryStore $store,
        InventoryItem $item,
        string $direction,
        float $quantity,
        string $reason,
    ): InventoryStockMovement {
        $this->facilities->assertFacilityAccessible($staff, $store->facility_id);

        if (! $store->is_active || ! $item->is_active || $item->facility_id !== $store->facility_id) {
            throw ValidationException::withMessages(['inventory_item_id' => 'The selected active item and store must belong to the same facility.']);
        }
        if (! in_array($direction, ['increase', 'decrease'], true)) {
            throw ValidationException::withMessages(['direction' => 'The stock adjustment direction is invalid.']);
        }
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'The adjustment quantity must be greater than zero.']);
        }
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required for every stock adjustment.']);
        }

        return DB::transaction(function () use ($staff, $store, $item, $direction, $quantity, $reason) {
            InventoryStockBalance::query()->firstOrCreate(
                ['store_id' => $store->id, 'inventory_item_id' => $item->id],
                ['quantity_on_hand' => 0, 'quantity_reserved' => 0, 'quantity_available' => 0, 'status' => 'active'],
            );

            $balance = InventoryStockBalance::query()
                ->where('store_id', $store->id)
                ->where('inventory_item_id', $item->id)
                ->lockForUpdate()
                ->firstOrFail();

            $signedQuantity = $direction === 'increase' ? $quantity : -$quantity;
            if ($signedQuantity < 0 && abs($signedQuantity) > (float) $balance->quantity_available) {
                throw ValidationException::withMessages(['quantity' => 'A decrease cannot exceed the available stock quantity.']);
            }

            $newOnHand = round((float) $balance->quantity_on_hand + $signedQuantity, 3);
            $newAvailable = round($newOnHand - (float) $balance->quantity_reserved, 3);
            if ($newOnHand < 0 || $newAvailable < 0) {
                throw ValidationException::withMessages(['quantity' => 'The adjustment would create a negative stock balance.']);
            }

            $balance->update([
                'quantity_on_hand' => $newOnHand,
                'quantity_available' => $newAvailable,
                'status' => 'active',
            ]);

            return InventoryStockMovement::create([
                'facility_id' => $store->facility_id,
                'store_id' => $store->id,
                'inventory_item_id' => $item->id,
                'performed_by_id' => $staff->id,
                'movement_type' => $direction === 'increase' ? 'adjustment_in' : 'adjustment_out',
                'quantity' => round($signedQuantity, 3),
                'balance_after' => $newOnHand,
                'notes' => trim($reason),
            ]);
        }, 3);
    }
}
