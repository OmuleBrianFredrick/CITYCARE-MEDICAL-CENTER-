<?php

namespace App\Services;

use App\Models\ClinicalEncounter;
use App\Models\Medication;
use App\Models\MedicationDispensing;
use App\Models\MedicationDispensingItem;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PharmacyService
{
    public function prescribe(ClinicalEncounter $encounter, User $prescriber, array $data): Prescription
    {
        $this->assertActiveStaffOnOpenEncounter($encounter, $prescriber);

        $items = $data['items'] ?? [];
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'At least one medication is required.']);
        }

        $medicationIds = collect($items)->pluck('medication_id')->map(fn ($id) => (int) $id)->unique()->values();
        $medications = Medication::query()
            ->whereIn('id', $medicationIds)
            ->where('facility_id', $encounter->facility_id)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($medications->count() !== $medicationIds->count()) {
            throw ValidationException::withMessages(['items' => 'All medications must be active and belong to the encounter facility.']);
        }

        foreach ($items as $index => $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            if ($quantity <= 0) {
                throw ValidationException::withMessages(["items.{$index}.quantity" => 'Quantity must be greater than zero.']);
            }
            if (isset($item['medication_formulation_id'])) {
                $formulation = $medications[$item['medication_id']]->formulations()->find($item['medication_formulation_id']);
                if (! $formulation || ! $formulation->is_active) {
                    throw ValidationException::withMessages(["items.{$index}.medication_formulation_id" => 'Selected formulation is invalid or inactive.']);
                }
            }
        }

        return DB::transaction(function () use ($encounter, $prescriber, $data, $medications): Prescription {
            $prescription = Prescription::create([
                'facility_id' => $encounter->facility_id,
                'patient_id' => $encounter->patient_id,
                'encounter_id' => $encounter->id,
                'prescribed_by' => $prescriber->id,
                'prescription_number' => 'RX-'.strtoupper(fake()->unique()->numerify('########')),
                'status' => Prescription::STATUS_PRESCRIBED,
                'notes' => $data['notes'] ?? null,
                'prescribed_at' => now(),
            ]);

            foreach ($data['items'] as $item) {
                $prescription->items()->create([
                    'medication_id' => $item['medication_id'],
                    'medication_formulation_id' => $item['medication_formulation_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'dose' => $item['dose'] ?? null,
                    'route' => $item['route'] ?? $medications[$item['medication_id']]->route,
                    'frequency' => $item['frequency'] ?? null,
                    'duration' => $item['duration'] ?? null,
                    'instructions' => $item['instructions'] ?? null,
                    'status' => PrescriptionItem::STATUS_PRESCRIBED,
                ]);
            }

            return $prescription->load('items.medication', 'items.formulation');
        });
    }

    public function dispense(Prescription $prescription, User $dispenser, array $items, ?string $notes = null): MedicationDispensing
    {
        if (! $dispenser->isStaff() || ! $dispenser->is_active) {
            throw ValidationException::withMessages(['dispenser' => 'Active staff access is required.']);
        }
        if ($prescription->isCancelled() || $prescription->isCompleted()) {
            throw ValidationException::withMessages(['status' => 'This prescription cannot be dispensed.']);
        }
        $prescription->loadMissing('items');

        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'At least one prescription item is required.']);
        }

        $itemIds = collect($items)->pluck('prescription_item_id')->map(fn ($id) => (int) $id)->unique()->values();
        if ($itemIds->count() !== count($items)) {
            throw ValidationException::withMessages(['items' => 'Each prescription item may appear only once per dispensing.']);
        }

        $prescriptionItems = $prescription->items->keyBy('id');
        foreach ($items as $index => $item) {
            $prescriptionItem = $prescriptionItems->get((int) ($item['prescription_item_id'] ?? 0));
            $quantity = (float) ($item['quantity_dispensed'] ?? 0);
            if (! $prescriptionItem) {
                throw ValidationException::withMessages(["items.{$index}.prescription_item_id" => 'Prescription item does not belong to this prescription.']);
            }
            if (in_array($prescriptionItem->status, [PrescriptionItem::STATUS_DISPENSED, PrescriptionItem::STATUS_CANCELLED], true)) {
                throw ValidationException::withMessages(["items.{$index}.prescription_item_id" => 'This prescription item cannot be dispensed.']);
            }
            if ($quantity <= 0) {
                throw ValidationException::withMessages(["items.{$index}.quantity_dispensed" => 'Dispensed quantity must be greater than zero.']);
            }

            $alreadyDispensed = (float) $prescriptionItem->dispensingItems()->sum('quantity_dispensed');
            $remaining = (float) $prescriptionItem->quantity - $alreadyDispensed;
            if ($quantity > $remaining) {
                throw ValidationException::withMessages(["items.{$index}.quantity_dispensed" => 'Dispensed quantity exceeds the remaining prescribed quantity.']);
            }
        }

        return DB::transaction(function () use ($prescription, $dispenser, $items, $notes, $prescriptionItems): MedicationDispensing {
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

            foreach ($items as $item) {
                $prescriptionItem = $prescriptionItems[(int) $item['prescription_item_id']];
                $dispensing->items()->create([
                    'prescription_item_id' => $prescriptionItem->id,
                    'quantity_dispensed' => $item['quantity_dispensed'],
                    'batch_number' => $item['batch_number'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
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

    public function cancelPrescription(Prescription $prescription, User $staff): Prescription
    {
        if (! $staff->isStaff() || ! $staff->is_active) {
            throw ValidationException::withMessages(['staff' => 'Active staff access is required.']);
        }
        if ($prescription->isCompleted() || $prescription->isCancelled()) {
            throw ValidationException::withMessages(['status' => 'This prescription cannot be cancelled.']);
        }

        return DB::transaction(function () use ($prescription): Prescription {
            $prescription->items()->whereNotIn('status', [PrescriptionItem::STATUS_DISPENSED, PrescriptionItem::STATUS_CANCELLED])->update(['status' => PrescriptionItem::STATUS_CANCELLED]);
            $prescription->update(['status' => Prescription::STATUS_CANCELLED, 'cancelled_at' => now()]);
            return $prescription->fresh('items');
        });
    }

    private function assertActiveStaffOnOpenEncounter(ClinicalEncounter $encounter, User $staff): void
    {
        if (! $staff->isStaff() || ! $staff->is_active) {
            throw ValidationException::withMessages(['prescriber' => 'Active staff access is required.']);
        }
        if (! $encounter->isOpen()) {
            throw ValidationException::withMessages(['encounter' => 'Medication prescribing requires an open clinical encounter.']);
        }
    }
}
