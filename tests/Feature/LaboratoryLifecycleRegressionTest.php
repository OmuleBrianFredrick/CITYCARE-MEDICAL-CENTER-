<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryOrderItem;
use App\Models\LaboratoryTest;
use App\Models\User;
use App\Services\LaboratoryOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LaboratoryLifecycleRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_completion_keeps_order_in_progress_until_all_active_items_finish(): void
    {
        $this->seed();
        $service = app(LaboratoryOrderService::class);
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $encounter = ClinicalEncounter::factory()->create();
        $tests = LaboratoryTest::factory()->count(3)->create([
            'facility_id' => $encounter->facility_id,
            'is_active' => true,
        ]);

        $order = $service->create($encounter, $staff, ['test_ids' => $tests->pluck('id')->all()]);
        $items = $order->items()->orderBy('id')->get();

        $service->recordResult($items[0], $staff, ['result_value' => 'Negative']);
        $service->recordResult($items[1], $staff, ['result_value' => 'Positive']);

        $this->assertSame(LaboratoryOrder::STATUS_IN_PROGRESS, $order->fresh()->status);
        $this->assertSame(LaboratoryOrderItem::STATUS_ORDERED, $items[2]->fresh()->status);
    }

    public function test_closed_encounter_rejects_result_and_cancellation_workflows(): void
    {
        $service = app(LaboratoryOrderService::class);
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $encounter = ClinicalEncounter::factory()->create();
        $test = LaboratoryTest::factory()->create([
            'facility_id' => $encounter->facility_id,
            'is_active' => true,
        ]);
        $order = $service->create($encounter, $staff, ['test_ids' => [$test->id]]);
        $item = $order->items()->firstOrFail();

        $encounter->update(['status' => ClinicalEncounter::STATUS_CLOSED]);

        try {
            $service->recordResult($item, $staff, ['result_value' => 'Blocked']);
            $this->fail('Expected result recording on a closed encounter to be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        try {
            $service->cancelOrder($order, $staff);
            $this->fail('Expected cancellation on a closed encounter to be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function test_cancelled_items_are_excluded_from_active_completion_check(): void
    {
        $this->seed();
        $service = app(LaboratoryOrderService::class);
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $encounter = ClinicalEncounter::factory()->create();
        $tests = LaboratoryTest::factory()->count(2)->create([
            'facility_id' => $encounter->facility_id,
            'is_active' => true,
        ]);

        $order = $service->create($encounter, $staff, ['test_ids' => $tests->pluck('id')->all()]);
        $items = $order->items()->orderBy('id')->get();

        $items[1]->update([
            'status' => LaboratoryOrderItem::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $service->recordResult($items[0], $staff, ['result_value' => 'Negative']);

        $this->assertSame(LaboratoryOrder::STATUS_COMPLETED, $order->fresh()->status);
    }
}
