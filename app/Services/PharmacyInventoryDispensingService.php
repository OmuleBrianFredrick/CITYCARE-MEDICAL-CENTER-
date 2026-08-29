<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryStockBalance;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\Medication;
use App\Models\MedicationDispensing;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PharmacyInventoryDispensingService
{
    public function dispenseWithInventory(
        Prescription $prescription,
        User $dispenser,
        InventoryStore $store,
        array $items,
        ?string $notes = null
    ): MedicationDispensing {
        if (! $dispenser->isStaff() || ! $dispenser->isActive()) {
            throw ValidationException::withMessages(['dispenser' => 'Active staff access is required.']);
        }

        if ($store->facility_id !== $prescription->facility_id) {
            throw ValidationException::withMessages(['store_id' => 'The dispensing store belongs to another facility.']);
        }

        if (! $store->is_active) {
            throw ValidationException::withMessages(['store_id' => 'The selected store is inactive.']);
        }

        if ($prescription->isCancelled() || $prescription->isCompleted()) {
            throw ValidationException::withMessages(['status' => 'This prescription cannot be dispensed.']);
        }

        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'At least one prescription item is required.']);
        }

        $prescription->loadMissing('items.medication');
        $itemIds = collect($items)->pluck('prescription_item_id')->map(fn ($id) => (int) $id)->values();
        if ($itemIds->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['items' => 'Each prescription item may appear only once per dispensing.']);
        }

        return DB::transaction(function () use ($prescription, $dispenser, $store, $items, $notes): MedicationDispensing {
            $stockBalances = [];

            foreach ($items as $index => $item) {
                $prescriptionItem = $prescription->items->firstWhere('id', (int) ($item['prescription_item_id'] ?? 0));
                if (! $prescriptionItem) {
                    throw ValidationException::withMessages(["items.{$index}.prescription_item_id" => 'Prescription item does not belong to this prescription.']);
                }

                if (in_array($prescriptionItem->status, [PrescriptionItem::STATUS_DISPENSED, PrescriptionItem::STATUS_CANCELLED], true)) {
                    throw ValidationException::withMessages(["items.{$index}.prescription_item_id" => 'This prescription item cannot be dispensed.']);
                }

                $quantity = (float) ($item['quantity_dispensed'] ?? 0);
                if ($quantity <= 0) {
                    throw ValidationException::withMessages(["items.{$index}.quantity_dispensed" => 'Dispensed quantity must be greater than zero.']);
                }

                $alreadyDispensed = (float) $prescriptionItem->dispensingItems()->sum('quantity_dispensed');
                $remainingPrescription = round((float) $prescriptionItem->quantity - $alreadyDispensed, 3);
                if ($quantity > $remainingPrescription) {
                    throw ValidationException::withMessages(["items.{$index}.quantity_dispensed" => 'Dispensed quantity exceeds the remaining prescribed quantity.']);
                }

                $inventoryItem = $this->resolveInventoryItem($prescriptionItem->medication);
                if (! $inventoryItem) {
                    throw ValidationException::withMessages(["items.{$index}.quantity_dispensed" => 'No inventory item is mapped to this medication.']);
                }

                $balance = InventoryStockBalance::query()
                    ->where('store_id', $store->id)
                    ->where('inventory_item_id', $inventoryItem->id)
                    ->lockForUpdate()
                    ->first();

                if (! $balance || $balance->status !== 'active') {
                    throw ValidationException::withMessages(["items.{$index}.quantity_dispensed" => 'No active inventory balance exists for this medication at the dispensing store.']);
                }

                if ((float) $balance->quantity_available < $quantity) {
                    throw ValidationException::withMessages(["items.{$index}.quantity_dispensed" => 'Insufficient available inventory for the requested dispensing quantity.']);
                }

                $stockBalances[] = [
                    $prescriptionItem,
                    $inventoryItem,
                    $balance,
                    $quantity,
                    $item['batch_number'] ?? null,
                    $item['expiry_date'] ?? null,
                ];
            }

            $dispensing = MedicationDispensing::create([
                'facility_id' => $prescription->facility_id,
                'prescription_id' => $prescription->id,
                'patient_id' => $prescription->patient_id,
                'dispensed_by' => $dispenser->id,
                'dispensing_number' => 'DISP-'.strtoupper(fake()->unique()->numerify('########')),
                'status' => MedicationDispensing::STATUS_COMPLETED,
                'notes' => $notes,
                'dispensed_at' => now(),
            ]);

            foreach ($stockBalances as [$prescriptionItem, $inventoryItem, $balance, $quantity, $batchNumber, $expiryDate]) {
                $dispensingItem = $dispensing->items()->create([
                    'prescription_item_id' => $prescriptionItem->id,
                    'quantity_dispensed' => $quantity,
                    'batch_number' => $batchNumber,
                    'expiry_date' => $expiryDate,
                ]);

                $newOnHand = round((float) $balance->quantity_on_hand - $quantity, 3);
                $newAvailable = round((float) $balance->quantity_available - $quantity, 3);
                $balance->update([
                    'quantity_on_hand' => $newOnHand,
                    'quantity_available' => max(0, $newAvailable),
                ]);

                InventoryStockMovement::create([
                    'facility_id' => $prescription->facility_id,
                    'store_id' => $store->id,
                    'inventory_item_id' => $inventoryItem->id,
                    'goods_receipt_item_id' => null,
                    'performed_by_id' => $dispenser->id,
                    'movement_type' => 'issue',
                    'quantity' => -$quantity,
                    'balance_after' => $newOnHand,
                    'reference_type' => MedicationDispensing::class,
                    'reference_id' => $dispensing->id,
                    'notes' => $notes,
                ]);

                $alreadyDispensed = (float) $prescriptionItem->dispensingItems()->sum('quantity_dispensed');
                $prescriptionItem->update([
                    'status' => $alreadyDispensed >= (float) $prescriptionItem->quantity
                        ? PrescriptionItem::STATUS_DISPENSED
                        : PrescriptionItem::STATUS_PARTIALLY_DISPENSED,
                ]);
            }

            $prescription->refresh()->load('items');
            $allDone = $prescription->items->every(fn (PrescriptionItem $item) => in_array($item->status, [PrescriptionItem::STATUS_DISPENSED, PrescriptionItem::STATUS_CANCELLED], true));
            $prescription->update([
                'status' => $allDone ? Prescription::STATUS_COMPLETED : Prescription::STATUS_PARTIALLY_DISPENSED,
                'completed_at' => $allDone ? now() : null,
            ]);

            return $dispensing->load('items.prescriptionItem');
        });
    }

    private function resolveInventoryItem(Medication $medication): ?InventoryItem
    {
        return InventoryItem::query()
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
    }
}
