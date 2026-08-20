<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryTest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaboratoryOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_create_laboratory_order_through_http_workflow(): void
    {
        $this->seed();
        $doctor = $this->userWithRole('doctor');
        $encounter = ClinicalEncounter::factory()->create();
        $tests = LaboratoryTest::factory()->count(2)->create([
            'facility_id' => $encounter->facility_id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($doctor)->withSession()->post(
            route('encounters.laboratory-orders.store', $encounter),
            ['test_ids' => $tests->pluck('id')->all(), 'notes' => 'Initial diagnostic workup.']
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('laboratory_orders', [
            'encounter_id' => $encounter->id,
            'ordered_by' => $doctor->id,
            'status' => LaboratoryOrder::STATUS_ORDERED,
        ]);
    }

    public function test_user_without_order_creation_permission_is_denied(): void
    {
        $this->seed();
        $nurse = $this->userWithRole('nurse');
        $encounter = ClinicalEncounter::factory()->create();
        $test = LaboratoryTest::factory()->create([
            'facility_id' => $encounter->facility_id,
            'is_active' => true,
        ]);

        $this->actingAs($nurse)->withSession()->post(route('encounters.laboratory-orders.store', $encounter), [
            'test_ids' => [$test->id],
        ])->assertForbidden();
    }

    public function test_laboratory_staff_can_record_result_through_http_workflow(): void
    {
        $this->seed();
        $laboratory = $this->userWithRole('laboratory');
        $encounter = ClinicalEncounter::factory()->create();
        $test = LaboratoryTest::factory()->create([
            'facility_id' => $encounter->facility_id,
            'is_active' => true,
        ]);
        $order = app(\App\Services\LaboratoryOrderService::class)->create($encounter, $laboratory, [
            'test_ids' => [$test->id],
        ]);
        $item = $order->items()->firstOrFail();

        $response = $this->actingAs($laboratory)->withSession()->post(
            route('encounters.laboratory-order-items.result', $item),
            ['result_value' => 'Negative', 'comments' => 'No abnormal findings.']
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('laboratory_results', [
            'laboratory_order_item_id' => $item->id,
            'recorded_by' => $laboratory->id,
            'result_value' => 'Negative',
        ]);
    }

    public function test_doctor_without_result_permission_is_denied(): void
    {
        $this->seed();
        $doctor = $this->userWithRole('doctor');
        $encounter = ClinicalEncounter::factory()->create();
        $test = LaboratoryTest::factory()->create([
            'facility_id' => $encounter->facility_id,
            'is_active' => true,
        ]);
        $order = app(\App\Services\LaboratoryOrderService::class)->create($encounter, $doctor, [
            'test_ids' => [$test->id],
        ]);
        $item = $order->items()->firstOrFail();

        $this->actingAs($doctor)->withSession()->post(route('encounters.laboratory-order-items.result', $item), [
            'result_value' => 'Should be forbidden',
        ])->assertForbidden();
    }

    public function test_authorized_staff_can_cancel_laboratory_order(): void
    {
        $this->seed();
        $laboratory = $this->userWithRole('laboratory');
        $encounter = ClinicalEncounter::factory()->create();
        $test = LaboratoryTest::factory()->create([
            'facility_id' => $encounter->facility_id,
            'is_active' => true,
        ]);
        $order = app(\App\Services\LaboratoryOrderService::class)->create($encounter, $laboratory, [
            'test_ids' => [$test->id],
        ]);

        $response = $this->actingAs($laboratory)->withSession()->post(
            route('encounters.laboratory-orders.cancel', $order)
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('laboratory_orders', [
            'id' => $order->id,
            'status' => LaboratoryOrder::STATUS_CANCELLED,
        ]);
    }

    public function test_user_without_work_management_permission_cannot_cancel_order(): void
    {
        $this->seed();
        $doctor = $this->userWithRole('doctor');
        $encounter = ClinicalEncounter::factory()->create();
        $test = LaboratoryTest::factory()->create([
            'facility_id' => $encounter->facility_id,
            'is_active' => true,
        ]);
        $order = app(\App\Services\LaboratoryOrderService::class)->create($encounter, $doctor, [
            'test_ids' => [$test->id],
        ]);

        $this->actingAs($doctor)->withSession()->post(route('encounters.laboratory-orders.cancel', $order))->assertForbidden();
    }

    private function userWithRole(string $roleSlug): User
    {
        $user = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', $roleSlug)->valueOrFail('id'));

        return $user;
    }
}
