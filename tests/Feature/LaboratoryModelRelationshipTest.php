<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryResult;
use App\Models\LaboratoryTest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaboratoryModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_laboratory_test_order_item_and_result_relationships_are_connected(): void
    {
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $encounter = ClinicalEncounter::factory()->create();
        $test = LaboratoryTest::factory()->create([
            'facility_id' => $encounter->facility_id,
            'is_active' => true,
        ]);
        $order = LaboratoryOrder::factory()->create([
            'facility_id' => $encounter->facility_id,
            'patient_id' => $encounter->patient_id,
            'encounter_id' => $encounter->id,
            'ordered_by' => $staff->id,
        ]);
        $item = $order->items()->create([
            'laboratory_test_id' => $test->id,
            'status' => 'ordered',
        ]);
        $result = LaboratoryResult::create([
            'laboratory_order_item_id' => $item->id,
            'recorded_by' => $staff->id,
            'result_value' => 'Negative',
            'recorded_at' => now(),
        ]);

        $this->assertSame($order->id, $item->order->id);
        $this->assertSame($test->id, $item->laboratoryTest->id);
        $this->assertSame($item->id, $result->orderItem->id);
        $this->assertSame($encounter->id, $order->encounter->id);
        $this->assertSame($encounter->patient_id, $order->patient_id);
    }
}
