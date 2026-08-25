<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\LaboratoryTest;
use App\Models\Patient;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\LaboratoryOrderService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaboratoryWorkspaceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_laboratory_staff_can_process_a_pending_order_from_the_facility_queue(): void
    {
        [$facility, $department, $servicePoint] = $this->facilityContext();
        $laboratory = $this->staffFor('laboratory', $department, $servicePoint);
        [$encounter, $test] = $this->openEncounterWithTest($facility, $department, $servicePoint);
        $order = app(LaboratoryOrderService::class)->create($encounter, $laboratory, ['test_ids' => [$test->id]]);
        $item = $order->items()->firstOrFail();

        $this->actingAs($laboratory)
            ->get(route('laboratory.index'))
            ->assertOk()
            ->assertSee('Laboratory work queue')
            ->assertSee($order->order_number)
            ->assertSee($encounter->patient->medical_record_number)
            ->assertSee('Record result');

        $this->actingAs($laboratory)
            ->post(route('encounters.laboratory-order-items.result.store', $item), [
                'result_value' => 'Negative',
                'unit' => 'qualitative',
                'reference_range' => 'Negative',
                'comments' => 'Verified in laboratory queue.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('laboratory_results', [
            'laboratory_order_item_id' => $item->id,
            'recorded_by' => $laboratory->id,
            'result_value' => 'Negative',
        ]);
        $this->assertSame('completed', $order->fresh()->status);

        $this->actingAs($laboratory)
            ->get(route('laboratory.index', ['status' => 'completed']))
            ->assertOk()
            ->assertSee('Verified in laboratory queue.')
            ->assertSee('Completed');
    }

    public function test_laboratory_queue_is_facility_scoped_and_cross_facility_result_entry_is_denied(): void
    {
        [$facilityA, $departmentA, $servicePointA] = $this->facilityContext();
        $laboratory = $this->staffFor('laboratory', $departmentA, $servicePointA);
        [$ownEncounter, $ownTest] = $this->openEncounterWithTest($facilityA, $departmentA, $servicePointA);
        $ownOrder = app(LaboratoryOrderService::class)->create($ownEncounter, $laboratory, ['test_ids' => [$ownTest->id]]);

        [$facilityB, $departmentB, $servicePointB] = $this->facilityContext();
        [$otherEncounter, $otherTest] = $this->openEncounterWithTest($facilityB, $departmentB, $servicePointB);
        $otherAuthor = $this->staffFor('doctor', $departmentB, $servicePointB);
        $otherOrder = app(LaboratoryOrderService::class)->create($otherEncounter, $otherAuthor, ['test_ids' => [$otherTest->id]]);
        $otherItem = $otherOrder->items()->firstOrFail();

        $this->actingAs($laboratory)
            ->get(route('laboratory.index'))
            ->assertOk()
            ->assertSee($ownOrder->order_number)
            ->assertDontSee($otherOrder->order_number);

        $this->actingAs($laboratory)
            ->post(route('encounters.laboratory-order-items.result.store', $otherItem), ['result_value' => 'Blocked'])
            ->assertForbidden();
    }

    /** @return array{0: Facility, 1: Department, 2: ServicePoint} */
    private function facilityContext(): array
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $servicePoint = ServicePoint::factory()->create(['department_id' => $department->id]);

        return [$facility, $department, $servicePoint];
    }

    /** @return array{0: ClinicalEncounter, 1: LaboratoryTest} */
    private function openEncounterWithTest(Facility $facility, Department $department, ServicePoint $servicePoint): array
    {
        $clinician = $this->staffFor('doctor', $department, $servicePoint);
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $encounter = ClinicalEncounter::factory()->create([
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id,
            'clinician_id' => $clinician->id,
            'status' => ClinicalEncounter::STATUS_OPEN,
        ])->load('patient');
        $test = LaboratoryTest::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);

        return [$encounter, $test];
    }

    private function staffFor(string $roleSlug, Department $department, ServicePoint $servicePoint): User
    {
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->attach(Role::where('slug', $roleSlug)->valueOrFail('id'));
        StaffProfile::query()->create([
            'user_id' => $staff->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
        ]);

        return $staff;
    }
}
