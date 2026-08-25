<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\InventoryItem;
use App\Models\InventoryStockBalance;
use App\Models\InventoryStore;
use App\Models\Medication;
use App\Models\MedicationFormulation;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\PharmacyService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyWorkspaceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_pharmacy_staff_can_issue_a_pending_prescription_from_the_facility_queue(): void
    {
        [$facility, $department, $servicePoint] = $this->facilityContext();
        $pharmacy = $this->staffFor('pharmacy', $department, $servicePoint);
        [$prescription, $store, $balance] = $this->pendingPrescription($facility, $department, $servicePoint);
        $item = $prescription->items->firstOrFail();

        $this->actingAs($pharmacy)
            ->get(route('pharmacy.index'))
            ->assertOk()
            ->assertSee('Prescription queue')
            ->assertSee($prescription->prescription_number)
            ->assertSee($prescription->patient->medical_record_number)
            ->assertSee('Issue medication');

        $this->actingAs($pharmacy)
            ->post(route('encounters.prescriptions.dispense', $prescription), [
                'store_id' => $store->id,
                'notes' => 'Issued after identity check.',
                'items' => [[
                    'prescription_item_id' => $item->id,
                    'quantity_dispensed' => 2,
                    'batch_number' => 'BATCH-2026-01',
                    'expiry_date' => now()->addYear()->toDateString(),
                ]],
            ])
            ->assertRedirect();

        $this->assertSame(3.0, (float) $balance->fresh()->quantity_available);
        $this->assertDatabaseHas('medication_dispensing_items', [
            'prescription_item_id' => $item->id,
            'quantity_dispensed' => 2,
            'batch_number' => 'BATCH-2026-01',
        ]);
        $this->assertSame(Prescription::STATUS_PARTIALLY_DISPENSED, $prescription->fresh()->status);

        $this->actingAs($pharmacy)
            ->get(route('pharmacy.index'))
            ->assertOk()
            ->assertSee('3 remaining');
    }

    public function test_pharmacy_queue_is_facility_scoped_and_cross_facility_dispensing_is_denied(): void
    {
        [$facilityA, $departmentA, $servicePointA] = $this->facilityContext();
        $pharmacy = $this->staffFor('pharmacy', $departmentA, $servicePointA);
        [$ownPrescription] = $this->pendingPrescription($facilityA, $departmentA, $servicePointA);

        [$facilityB, $departmentB, $servicePointB] = $this->facilityContext();
        [$otherPrescription, $otherStore] = $this->pendingPrescription($facilityB, $departmentB, $servicePointB);
        $otherItem = $otherPrescription->items->firstOrFail();

        $this->actingAs($pharmacy)
            ->get(route('pharmacy.index'))
            ->assertOk()
            ->assertSee($ownPrescription->prescription_number)
            ->assertDontSee($otherPrescription->prescription_number);

        $this->actingAs($pharmacy)
            ->post(route('encounters.prescriptions.dispense', $otherPrescription), [
                'store_id' => $otherStore->id,
                'items' => [[
                    'prescription_item_id' => $otherItem->id,
                    'quantity_dispensed' => 1,
                ]],
            ])
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

    /** @return array{0: Prescription, 1: InventoryStore, 2: InventoryStockBalance} */
    private function pendingPrescription(Facility $facility, Department $department, ServicePoint $servicePoint): array
    {
        $doctor = $this->staffFor('doctor', $department, $servicePoint);
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $encounter = ClinicalEncounter::factory()->create([
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id,
            'clinician_id' => $doctor->id,
            'status' => ClinicalEncounter::STATUS_OPEN,
        ]);
        $inventoryItem = InventoryItem::factory()->create([
            'facility_id' => $facility->id,
            'name' => 'Paracetamol 500mg',
            'code' => 'PCM500',
            'unit' => 'tablet',
            'is_active' => true,
        ]);
        $store = InventoryStore::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);
        $balance = InventoryStockBalance::factory()->create([
            'store_id' => $store->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'quantity_available' => 5,
            'status' => 'active',
        ]);
        $medication = Medication::factory()->create([
            'facility_id' => $facility->id,
            'name' => 'Paracetamol 500mg',
            'generic_name' => 'Paracetamol',
            'code' => 'PCM500',
            'dosage_form' => 'tablet',
            'is_active' => true,
        ]);
        $formulation = MedicationFormulation::factory()->create(['medication_id' => $medication->id, 'is_active' => true]);
        $prescription = app(PharmacyService::class)->prescribe($encounter, $doctor, [
            'items' => [[
                'medication_id' => $medication->id,
                'medication_formulation_id' => $formulation->id,
                'quantity' => 5,
                'dose' => '1 tablet',
                'frequency' => 'Three times daily',
            ]],
        ]);

        return [$prescription->fresh(['patient', 'items.medication', 'items.formulation']), $store, $balance];
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
