<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryTest;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\LaboratoryOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaboratoryOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The HTTP workflow tests intentionally bypass only CSRF protection so that
        // authentication, active-account, permission, validation and controller
        // behavior are exercised directly without browser-token coupling.
        $this->withoutMiddleware('Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken');
    }

    public function test_doctor_can_create_laboratory_order_through_http_workflow(): void
    {
        $this->seed();
        $doctor = $this->userWithRole('doctor');
        $encounter = ClinicalEncounter::factory()->create();
        $this->assignToEncounter($doctor, $encounter);
        $tests = LaboratoryTest::factory()->count(2)->create([
            'facility_id' => $encounter->facility_id,
            'is_active' => true,
        ]);

        $response = $this->from(route('encounters.show', $encounter))
            ->actingAs($doctor)
            ->post(route('encounters.laboratory-orders.store', $encounter), [
                'test_ids' => $tests->pluck('id')->all(),
                'notes' => 'Initial diagnostic workup.',
            ]);

        $response->assertRedirect(route('encounters.show', $encounter));
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

        $this->actingAs($nurse)
            ->post(route('encounters.laboratory-orders.store', $encounter), [
                'test_ids' => [$test->id],
            ])
            ->assertForbidden();
    }

    public function test_laboratory_staff_can_record_result_through_http_workflow(): void
    {
        $this->seed();
        $laboratory = $this->userWithRole('laboratory');
        $encounter = ClinicalEncounter::factory()->create();
        $this->assignToEncounter($laboratory, $encounter);
        $test = LaboratoryTest::factory()->create([
            'facility_id' => $encounter->facility_id,
            'is_active' => true,
        ]);
        $order = app(LaboratoryOrderService::class)->create($encounter, $laboratory, [
            'test_ids' => [$test->id],
        ]);
        $item = $order->items()->firstOrFail();

        $response = $this->from(route('encounters.show', $encounter))
            ->actingAs($laboratory)
            ->post(route('encounters.laboratory-order-items.result.store', $item), [
                'result_value' => 'Negative',
                'comments' => 'No abnormal findings.',
            ]);

        $response->assertRedirect(route('encounters.show', $encounter));
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
        $this->assignToEncounter($doctor, $encounter);
        $test = LaboratoryTest::factory()->create([
            'facility_id' => $encounter->facility_id,
            'is_active' => true,
        ]);
        $order = app(LaboratoryOrderService::class)->create($encounter, $doctor, [
            'test_ids' => [$test->id],
        ]);
        $item = $order->items()->firstOrFail();

        $this->actingAs($doctor)
            ->post(route('encounters.laboratory-order-items.result.store', $item), [
                'result_value' => 'Should be forbidden',
            ])
            ->assertForbidden();
    }

    public function test_authorized_staff_can_cancel_laboratory_order(): void
    {
        $this->seed();
        $laboratory = $this->userWithRole('laboratory');
        $encounter = ClinicalEncounter::factory()->create();
        $this->assignToEncounter($laboratory, $encounter);
        $test = LaboratoryTest::factory()->create([
            'facility_id' => $encounter->facility_id,
            'is_active' => true,
        ]);
        $order = app(LaboratoryOrderService::class)->create($encounter, $laboratory, [
            'test_ids' => [$test->id],
        ]);

        $response = $this->from(route('encounters.show', $encounter))
            ->actingAs($laboratory)
            ->post(route('encounters.laboratory-orders.cancel', $order));

        $response->assertRedirect(route('encounters.show', $encounter));
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
        $this->assignToEncounter($doctor, $encounter);
        $test = LaboratoryTest::factory()->create([
            'facility_id' => $encounter->facility_id,
            'is_active' => true,
        ]);
        $order = app(LaboratoryOrderService::class)->create($encounter, $doctor, [
            'test_ids' => [$test->id],
        ]);

        $this->actingAs($doctor)
            ->post(route('encounters.laboratory-orders.cancel', $order))
            ->assertForbidden();
    }

    private function assignToEncounter(User $user, ClinicalEncounter $encounter): void
    {
        $department = Department::factory()->create(['facility_id' => $encounter->facility_id]);

        StaffProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['department_id' => $department->id]
        );
    }

    private function userWithRole(string $roleSlug): User
    {
        $user = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', $roleSlug)->valueOrFail('id'));

        return $user;
    }
}
