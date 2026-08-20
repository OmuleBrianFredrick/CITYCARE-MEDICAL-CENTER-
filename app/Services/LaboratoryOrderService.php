<?php

namespace App\Services;

use App\Models\ClinicalEncounter;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryOrderItem;
use App\Models\LaboratoryResult;
use App\Models\LaboratoryTest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaboratoryOrderService
{
    public function create(ClinicalEncounter $encounter, User $author, array $data): LaboratoryOrder
    {
        $this->assertCanWork($encounter, $author);
        $testIds = array_values(array_unique(array_map('intval', $data['test_ids'] ?? [])));
        if ($testIds === []) {
            throw ValidationException::withMessages(['test_ids' => 'At least one laboratory test is required.']);
        }

        $tests = LaboratoryTest::query()
            ->whereKey($testIds)
            ->where('facility_id', $encounter->facility_id)
            ->where('is_active', true)
            ->get();

        if ($tests->count() !== count($testIds)) {
            throw ValidationException::withMessages(['test_ids' => 'All selected laboratory tests must be active and belong to the encounter facility.']);
        }

        return DB::transaction(function () use ($encounter, $author, $data, $tests): LaboratoryOrder {
            $order = LaboratoryOrder::create([
                'facility_id' => $encounter->facility_id,
                'patient_id' => $encounter->patient_id,
                'encounter_id' => $encounter->id,
                'ordered_by' => $author->id,
                'order_number' => 'LABORD-'.strtoupper(fake()->unique()->numerify('########')),
                'status' => LaboratoryOrder::STATUS_ORDERED,
                'notes' => $data['notes'] ?? null,
                'ordered_at' => now(),
            ]);

            foreach ($tests as $test) {
                $order->items()->create([
                    'laboratory_test_id' => $test->id,
                    'status' => LaboratoryOrderItem::STATUS_ORDERED,
                    'notes' => null,
                ]);
            }

            return $order->load('items.laboratoryTest');
        });
    }

    public function recordResult(LaboratoryOrderItem $item, User $author, array $data): LaboratoryResult
    {
        if (! $author->isStaff() || ! $author->is_active) {
            throw ValidationException::withMessages(['author' => 'Active staff access is required.']);
        }

        $item->loadMissing('order.encounter', 'laboratoryTest');
        $order = $item->order;
        $encounter = $order?->encounter;
        if (! $order || ! $encounter || ! $encounter->isOpen()) {
            throw ValidationException::withMessages(['encounter' => 'Laboratory results require an open clinical encounter.']);
        }
        if ($order->isCancelled() || $order->isCompleted()) {
            throw ValidationException::withMessages(['status' => 'This laboratory order cannot accept a new result.']);
        }
        if ($item->isCancelled() || $item->isCompleted()) {
            throw ValidationException::withMessages(['status' => 'This laboratory item cannot accept a new result.']);
        }
        if ($item->result()->exists()) {
            throw ValidationException::withMessages(['result' => 'A result has already been recorded for this item.']);
        }

        return DB::transaction(function () use ($item, $author, $data): LaboratoryResult {
            $result = $item->result()->create([
                'recorded_by' => $author->id,
                'result_value' => $data['result_value'],
                'unit' => $data['unit'] ?? $item->laboratoryTest->unit,
                'reference_range' => $data['reference_range'] ?? $item->laboratoryTest->reference_range,
                'is_abnormal' => (bool) ($data['is_abnormal'] ?? false),
                'comments' => $data['comments'] ?? null,
                'recorded_at' => now(),
            ]);

            $item->update([
                'status' => LaboratoryOrderItem::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            $order = $item->order()->with('items')->firstOrFail();
            if ($order->items->every(fn (LaboratoryOrderItem $orderItem) => $orderItem->isCompleted() || $orderItem->isCancelled())) {
                $allCancelled = $order->items->every(fn (LaboratoryOrderItem $orderItem) => $orderItem->isCancelled());
                $order->update([
                    'status' => $allCancelled ? LaboratoryOrder::STATUS_CANCELLED : LaboratoryOrder::STATUS_COMPLETED,
                    'completed_at' => $allCancelled ? null : now(),
                ]);
            } else {
                $order->update(['status' => LaboratoryOrder::STATUS_IN_PROGRESS]);
            }

            return $result;
        });
    }

    public function cancelOrder(LaboratoryOrder $order, User $author): LaboratoryOrder
    {
        $this->assertCanWork($order->encounter, $author);
        if ($order->isCompleted() || $order->isCancelled()) {
            throw ValidationException::withMessages(['status' => 'This laboratory order cannot be cancelled.']);
        }

        return DB::transaction(function () use ($order): LaboratoryOrder {
            $order->items()->whereNotIn('status', [LaboratoryOrderItem::STATUS_COMPLETED, LaboratoryOrderItem::STATUS_CANCELLED])->update([
                'status' => LaboratoryOrderItem::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);
            $order->update(['status' => LaboratoryOrder::STATUS_CANCELLED, 'cancelled_at' => now()]);
            return $order->fresh('items');
        });
    }

    private function assertCanWork(?ClinicalEncounter $encounter, User $author): void
    {
        if (! $author->isStaff() || ! $author->is_active) {
            throw ValidationException::withMessages(['author' => 'Active staff access is required.']);
        }
        if (! $encounter || ! $encounter->isOpen()) {
            throw ValidationException::withMessages(['encounter' => 'Laboratory work requires an open clinical encounter.']);
        }
    }
}
