<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\LaboratoryTest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaboratoryRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutMiddleware('Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken');
    }

    public function test_order_request_requires_at_least_one_test(): void
    {
        $doctor = $this->userWithRole('doctor');
        $encounter = ClinicalEncounter::factory()->create();

        $this->actingAs($doctor)
            ->post(route('encounters.laboratory-orders.store', $encounter), [])
            ->assertSessionHasErrors('test_ids');
    }

    public function test_order_request_rejects_duplicate_test_ids(): void
    {
        $doctor = $this->userWithRole('doctor');
        $encounter = ClinicalEncounter::factory()->create();
        $test = LaboratoryTest::factory()->create([
            'facility_id' => $encounter->facility_id,
            'is_active' => true,
        ]);

        $this->actingAs($doctor)
            ->post(route('encounters.laboratory-orders.store', $encounter), [
                'test_ids' => [$test->id, $test->id],
            ])
            ->assertSessionHasErrors('test_ids.1');
    }

    public function test_result_request_requires_result_value(): void
    {
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

        $this->actingAs($laboratory)
            ->post(route('encounters.laboratory-order-items.result.store', $item), [])
            ->assertSessionHasErrors('result_value');
    }

    public function test_result_request_rejects_an_overlong_result_value(): void
    {
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

        $this->actingAs($laboratory)
            ->post(route('encounters.laboratory-order-items.result.store', $item), [
                'result_value' => str_repeat('x', 2001),
            ])
            ->assertSessionHasErrors('result_value');
    }

    private function userWithRole(string $roleSlug): User
    {
        $user = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', $roleSlug)->valueOrFail('id'));

        return $user;
    }
}
