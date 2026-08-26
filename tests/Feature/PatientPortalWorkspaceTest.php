<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryOrderItem;
use App\Models\LaboratoryResult;
use App\Models\LaboratoryTest;
use App\Models\Medication;
use App\Models\MedicationFormulation;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientPortalWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_patient_portal_shows_only_the_authenticated_patients_records(): void
    {
        $facility = Facility::query()->where('is_active', true)->firstOrFail();
        $account = $this->patientAccount($facility, 'my.portal@citycare.test');
        $patient = $account->patientProfile;
        $otherPatient = Patient::factory()->create(['facility_id' => $facility->id]);
        $department = Department::factory()->create(['facility_id' => $facility->id, 'name' => 'Patient Care']);
        $servicePoint = ServicePoint::factory()->create(['department_id' => $department->id]);
        $clinician = User::factory()->create(['user_type' => 'staff', 'is_active' => true, 'name' => 'Dr Portal']);

        Appointment::factory()->create([
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id,
            'provider_id' => $clinician->id,
            'reason' => 'MY-PRIVATE-FOLLOW-UP',
            'scheduled_start' => now()->addDay(),
            'scheduled_end' => now()->addDay()->addMinutes(30),
        ]);
        Appointment::factory()->create([
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $otherPatient->id,
            'provider_id' => $clinician->id,
            'reason' => 'OTHER-PATIENT-APPOINTMENT',
            'scheduled_start' => now()->addDay(),
            'scheduled_end' => now()->addDay()->addMinutes(30),
        ]);

        $encounter = ClinicalEncounter::create([
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id,
            'appointment_id' => null,
            'clinician_id' => $clinician->id,
            'encounter_number' => 'ENC-PATIENT-PORTAL',
            'type' => ClinicalEncounter::TYPE_OUTPATIENT,
            'status' => ClinicalEncounter::STATUS_CLOSED,
            'started_at' => now()->subDays(2),
            'closed_at' => now()->subDays(2)->addHour(),
            'summary' => 'MY-FINALIZED-VISIT-SUMMARY',
        ]);

        $laboratoryTest = LaboratoryTest::factory()->create([
            'facility_id' => $facility->id,
            'name' => 'Portal Blood Test',
        ]);
        $laboratoryOrder = LaboratoryOrder::create([
            'facility_id' => $facility->id,
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'ordered_by' => $clinician->id,
            'order_number' => 'LAB-PATIENT-PORTAL',
            'status' => LaboratoryOrder::STATUS_COMPLETED,
            'ordered_at' => now()->subDay(),
            'completed_at' => now(),
        ]);
        $laboratoryItem = LaboratoryOrderItem::create([
            'laboratory_order_id' => $laboratoryOrder->id,
            'laboratory_test_id' => $laboratoryTest->id,
            'status' => LaboratoryOrderItem::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
        LaboratoryResult::create([
            'laboratory_order_item_id' => $laboratoryItem->id,
            'recorded_by' => $clinician->id,
            'result_value' => 'MY-RESULT-42',
            'recorded_at' => now(),
        ]);

        $medication = Medication::factory()->create(['facility_id' => $facility->id, 'name' => 'Portal Medicine']);
        $formulation = MedicationFormulation::factory()->create(['medication_id' => $medication->id, 'strength' => '250']);
        $prescription = Prescription::create([
            'facility_id' => $facility->id,
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'prescribed_by' => $clinician->id,
            'prescription_number' => 'RX-PATIENT-PORTAL',
            'status' => Prescription::STATUS_PRESCRIBED,
            'prescribed_at' => now(),
        ]);
        PrescriptionItem::create([
            'prescription_id' => $prescription->id,
            'medication_id' => $medication->id,
            'medication_formulation_id' => $formulation->id,
            'quantity' => 10,
            'dose' => '1 tablet',
            'frequency' => 'twice daily',
            'duration' => '5 days',
            'status' => 'prescribed',
        ]);

        $invoice = Invoice::factory()->issued()->create([
            'facility_id' => $facility->id,
            'patient_id' => $patient->id,
            'total' => 75000,
            'paid_amount' => 25000,
            'balance_due' => 50000,
        ]);
        InvoiceLineItem::factory()->create([
            'invoice_id' => $invoice->id,
            'description' => 'MY-PORTAL-INVOICE-LINE',
            'line_total' => 75000,
        ]);
        Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCT-PATIENT-PORTAL',
            'amount' => 25000,
        ]);

        $otherInvoice = Invoice::factory()->issued()->create([
            'facility_id' => $facility->id,
            'patient_id' => $otherPatient->id,
        ]);
        InvoiceLineItem::factory()->create([
            'invoice_id' => $otherInvoice->id,
            'description' => 'OTHER-PATIENT-INVOICE-LINE',
        ]);

        $this->actingAs($account)
            ->get(route('portal.index'))
            ->assertOk()
            ->assertSee('MY-PRIVATE-FOLLOW-UP')
            ->assertSee('MY-FINALIZED-VISIT-SUMMARY')
            ->assertSee('MY-RESULT-42')
            ->assertSee('Portal Medicine')
            ->assertSee('MY-PORTAL-INVOICE-LINE')
            ->assertSee('RCT-PATIENT-PORTAL')
            ->assertDontSee('OTHER-PATIENT-APPOINTMENT')
            ->assertDontSee('OTHER-PATIENT-INVOICE-LINE');
    }

    public function test_staff_and_disabled_patient_portals_are_rejected(): void
    {
        $facility = Facility::query()->where('is_active', true)->firstOrFail();
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->attach(Role::query()->where('slug', 'super-admin')->firstOrFail());

        $this->actingAs($staff)->get(route('portal.index'))->assertForbidden();

        $patientAccount = $this->patientAccount($facility, 'disabled.portal@citycare.test');
        $patientAccount->patientProfile->forceFill(['portal_disabled_at' => now()])->save();

        $this->actingAs($patientAccount)->get(route('portal.index'))->assertForbidden();
    }

    private function patientAccount(Facility $facility, string $email): User
    {
        $user = User::factory()->create([
            'user_type' => 'patient',
            'is_active' => true,
            'email' => $email,
        ]);
        $user->roles()->attach(Role::query()->where('slug', 'patient')->firstOrFail());
        Patient::factory()->create([
            'facility_id' => $facility->id,
            'user_id' => $user->id,
            'email' => $email,
            'portal_invited_at' => now()->subDay(),
            'portal_activated_at' => now(),
            'portal_disabled_at' => null,
        ]);

        return $user->load('patientProfile');
    }
}
