<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryTest;
use App\Models\Role;
use App\Models\User;
use App\Services\LaboratoryOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LaboratoryInvalidStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_inactive_laboratory_test_cannot_be_ordered(): void
    {
        $service = app(LaboratoryOrderService::class);
        $staff = $this->userWithRole('doctor');
        $encounter = ClinicalEncounter::factory()->create();
        $test = LaboratoryTest::factory()->create([
            'facility_id' => $encounter->facility_id,
            'is_active' => false,
        ]);

        $this->expectException(ValidationException::class);
        $service->create($encounter, $staff, ['test_ids' => [$test->id]]);
    }

    public function test_test_from_another_facility_cannot_be_ordered(): void
    {
        $service = app(LaboratoryOrderService::class);
        $staff = $this->userWithRole('doctor');
        $encounter = ClinicalEncounter::factory()->create();
        $otherFacility = \App\Models\Facility::factory()->create();
        $test = LaboratoryTest::factory()->create([
            'facility_id' => $otherFacility->id,
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        $service->create($encounter, $staff, ['test_ids' => [$test->id]]);
    }

    public function test_duplicate_result_is_rejected(): void
    {
        $service = app(LaboratoryOrderService::class);
        $staff = $this->userWithRole('laboratory');
        $encounter = ClinicalEncounter::factory()->create();
        $test = LaboratoryTest::factory()->create([
            'facility_id' => $encounter->facility_id,
            'is_active' => true,
        ]);
        $order = $service->create($encounter, $staff, ['test_ids' => [$test->id]]);
        $item = $order->items()->firstOrFail();

        $service->recordResult($item, $staff, ['result_value' => 'Negative']);

        $this->expectException(ValidationException::class);
        $service->recordResult($item->fresh(), $staff, ['result_value' => 'Positive']);
    }

    public function test_completed_order_cannot_be_cancelled(): void
    {
        $service = app(LaboratoryOrderService::class);
        $staff = $this->userWithRole('laboratory');
        $encounter = ClinicalEncounter::factory()->create();
        $test = LaboratoryTest::factory()->create([
            'facility_id' => $encounter->facility_id,
            'is_active' => true,
        ]);
        $order = $service->create($encounter, $staff, ['test_ids' => [$test->id]]);
        $item = $order->items()->firstOrFail();
        $service->recordResult($item, $staff, ['result_value' => 'Negative']);

        $this->expectException(ValidationException::class);
        $service->cancelOrder($order->fresh(), $staff);
    }

    private function userWithRole(string $roleSlug): User
    {
        $user = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', $roleSlug)->valueOrFail('id'));
        return $user;
    }
}
