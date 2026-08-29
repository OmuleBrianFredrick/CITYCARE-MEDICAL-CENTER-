<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryStockBalance;
use App\Models\InventoryStore;
use App\Models\Medication;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PharmacyInventoryService
{
    public function stockForMedication(User $staff, Medication $medication, InventoryStore $store): InventoryStockBalance
    {
        $this->assertActiveStaff($staff);

        if ($medication->facility_id !== $store->facility_id) {
            throw ValidationException::withMessages(['store_id' => 'Medication and store must belong to the same facility.']);
        }
        if (! $store->is_active) {
            throw ValidationException::withMessages(['store_id' => 'The selected store is inactive.']);
        }

        $inventoryItem = InventoryItem::query()
            ->where('facility_id', $medication->facility_id)
            ->where('is_active', true)
            ->where(function ($query) use ($medication) {
                $query->where('code', $medication->code)
                    ->orWhere(function ($nested) use ($medication) {
                        $nested->where('name', $medication->name)
                            ->where('unit', $medication->dosage_form ?? 'unit');
                    });
            })
            ->first();

        if (! $inventoryItem) {
            throw ValidationException::withMessages(['medication_id' => 'No inventory item is mapped to this medication.']);
        }

        return InventoryStockBalance::query()->firstOrCreate(
            ['store_id' => $store->id, 'inventory_item_id' => $inventoryItem->id],
            ['quantity_on_hand' => 0, 'quantity_reserved' => 0, 'quantity_available' => 0, 'status' => 'active']
        );
    }

    public function assertDispensingStockAvailable(User $staff, Prescription $prescription, InventoryStore $store, array $items): void
    {
        $this->assertActiveStaff($staff);
        if (! $store->is_active) {
            throw ValidationException::withMessages(['store_id' => 'The selected store is inactive.']);
        }
        if ($store->facility_id !== $prescription->facility_id) {
            throw ValidationException::withMessages(['store_id' => 'The dispensing store belongs to another facility.']);
        }

        $prescription->loadMissing('items.medication');
        foreach ($items as $index => $item) {
            $prescriptionItem = $prescription->items->firstWhere('id', (int) ($item['prescription_item_id'] ?? 0));
            if (! $prescriptionItem) {
                throw ValidationException::withMessages(["items.{$index}.prescription_item_id" => 'Prescription item does not belong to this prescription.']);
            }

            $balance = $this->stockForMedication($staff, $prescriptionItem->medication, $store);
            $requested = (float) ($item['quantity_dispensed'] ?? 0);
            if ($requested > (float) $balance->quantity_available) {
                throw ValidationException::withMessages(["items.{$index}.quantity_dispensed" => 'Insufficient available inventory for the requested dispensing quantity.']);
            }
        }
    }

    private function assertActiveStaff(User $staff): void
    {
        if (! $staff->isStaff() || ! $staff->isActive()) {
            throw ValidationException::withMessages(['staff' => 'Active staff access is required.']);
        }
    }
}
