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

class LaboratoryOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_staff_can_order_multiple_tests_on_open_encounter(): void
    {
        $this->seed();
        $service = app(LaboratoryOrderService::class);
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $encounter = ClinicalEncounter::factory()->create();
        $tests = LaboratoryTest::factory()->count(2)->create(['facility_id' => $encounter->facility_id, 'is_active' => true]);

        $order = $service->create($encounter, $staff, ['test_ids' => $tests->pluck('id')->all(), 'notes' => 'Routine workup.']);

        $this->assertSame($encounter->id, $order->encounter_id);
        $this->assertCount(2, $order->items);
        $this->assertTrue($order->items->every(fn (LaboratoryOrderItem $item) => $item->status === LaboratoryOrderItem::STATUS_ORDERED));
    }

    public function test_closed_encounter_rejects_new_order(): void
    {
        $service = app(LaboratoryOrderService::class);
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $encounter = ClinicalEncounter::factory()->create(['status' => ClinicalEncounter::STATUS_CLOSED]);
        $test = LaboratoryTest::factory()->create(['facility_id' => $encounter->facility_id, 'is_active' => true]);

        $this->expectException(ValidationException::class);
        $service->create($encounter, $staff, ['test_ids' => [$test->id]]);
    }

    public function test_inactive_staff_cannot_order_diagnostics(): void
    {
        $service = app(LaboratoryOrderService::class);
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => false]);
        $encounter = ClinicalEncounter::factory()->create();
        $test = LaboratoryTest::factory()->create(['facility_id' => $encounter->facility_id, 'is_active' => true]);

        $this->expectException(ValidationException::class);
        $service->create($encounter, $staff, ['test_ids' => [$test->id]]);
    }

    public function test_result_completes_item_and_order_when_last_active_item_finishes(): void
    {
        $service = app(LaboratoryOrderService::class);
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $encounter = ClinicalEncounter::factory()->create();
        $tests = LaboratoryTest::factory()->count(2)->create(['facility_id' => $encounter->facility_id, 'is_active' => true]);
        $order = $service->create($encounter, $staff, ['test_ids' => $tests->pluck('id')->all()]);
        $first = $order->items()->oldest('id')->first();
        $second = $order->items()->latest('id')->first();

        $service->recordResult($first, $staff, ['result_value' => 'Negative']);
        $this->assertSame(LaboratoryOrder::STATUS_IN_PROGRESS, $order->fresh()->status);
        $service->recordResult($second, $staff, ['result_value' => '5.2', 'unit' => 'mmol/L']);

        $this->assertSame(LaboratoryOrderItem::STATUS_COMPLETED, $first->fresh()->status);
        $this->assertSame(LaboratoryOrderItem::STATUS_COMPLETED, $second->fresh()->status);
        $this->assertSame(LaboratoryOrder::STATUS_COMPLETED, $order->fresh()->status);
    }

    public function test_cancelled_order_rejects_new_result(): void
    {
        $service = app(LaboratoryOrderService::class);
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $encounter = ClinicalEncounter::factory()->create();
        $test = LaboratoryTest::factory()->create(['facility_id' => $encounter->facility_id, 'is_active' => true]);
        $order = $service->create($encounter, $staff, ['test_ids' => [$test->id]]);
        $item = $order->items()->first();
        $service->cancelOrder($order, $staff);

        $this->expectException(ValidationException::class);
        $service->recordResult($item, $staff, ['result_value' => 'Should fail']);
    }
}
