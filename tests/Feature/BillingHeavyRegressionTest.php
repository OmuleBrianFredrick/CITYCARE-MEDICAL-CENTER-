<?php

namespace Tests\Feature;

use App\Models\BillableService;
use App\Models\Charge;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\ServicePrice;
use App\Models\User;
use App\Services\BillingService;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BillingHeavyRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CityCareAccessSeeder::class);
    }

    public function test_multiple_charges_keep_pricing_discounts_and_adjustments_correct(): void
    {
        [$patient, $service, $price] = $this->billingSetup();
        $staff = $this->staffWithRole('cashier');
        $billing = app(BillingService::class);

        $first = $billing->addCharge($staff, $patient, $service, $price, [
            'quantity' => 2,
            'discount_amount' => 10000,
            'adjustment_amount' => 5000,
        ]);
        $second = $billing->addCharge($staff, $patient, $service, $price, [
            'quantity' => 3,
            'discount_amount' => 15000,
            'adjustment_amount' => 0,
        ]);

        $this->assertSame(200000.0, (float) $first->subtotal);
        $this->assertSame(195000.0, (float) $first->total);
        $this->assertSame(300000.0, (float) $second->subtotal);
        $this->assertSame(285000.0, (float) $second->total);
    }

    public function test_invalid_service_price_quantity_and_discount_states_are_rejected(): void
    {
        [$patient, $service, $price] = $this->billingSetup();
        $staff = $this->staffWithRole('cashier');
        $billing = app(BillingService::class);

        $inactiveService = BillableService::factory()->create([
            'facility_id' => $patient->facility_id,
            'is_active' => false,
        ]);
        $inactivePrice = ServicePrice::factory()->create([
            'facility_id' => $patient->facility_id,
            'billable_service_id' => $service->id,
            'amount' => 50000,
            'currency' => 'UGX',
            'effective_from' => now()->subDay()->toDateString(),
            'effective_to' => null,
            'is_active' => false,
        ]);

        $cases = [
            fn () => $billing->addCharge($staff, $patient, $inactiveService, $price),
            fn () => $billing->addCharge($staff, $patient, $service, $inactivePrice),
            fn () => $billing->addCharge($staff, $patient, $service, $price, ['quantity' => 0]),
            fn () => $billing->addCharge($staff, $patient, $service, $price, ['discount_amount' => -1]),
            fn () => $billing->addCharge($staff, $patient, $service, $price, ['discount_amount' => 100001]),
            fn () => $billing->addCharge($staff, $patient, $service, $price, ['quantity' => 1, 'adjustment_amount' => -100001]),
        ];

        foreach ($cases as $case) {
            try {
                $case();
                $this->fail('Expected billing validation exception was not thrown.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_invalid_price_quantity_and_discount_variants_are_rejected(): void
    {
        [$patient, $service, $price] = $this->billingSetup();
        $staff = $this->staffWithRole('cashier');
        $billing = app(BillingService::class);

        $expiredPrice = ServicePrice::factory()->create([
            'facility_id' => $patient->facility_id,
            'billable_service_id' => $service->id,
            'amount' => 50000,
            'currency' => 'UGX',
            'effective_from' => now()->subDays(10)->toDateString(),
            'effective_to' => now()->subDay()->toDateString(),
            'is_active' => true,
        ]);
        $futurePrice = ServicePrice::factory()->create([
            'facility_id' => $patient->facility_id,
            'billable_service_id' => $service->id,
            'amount' => 50000,
            'currency' => 'UGX',
            'effective_from' => now()->addDay()->toDateString(),
            'effective_to' => null,
            'is_active' => true,
        ]);

        foreach ([
            fn () => $billing->addCharge($staff, $patient, $service, $expiredPrice),
            fn () => $billing->addCharge($staff, $patient, $service, $futurePrice),
            fn () => $billing->addCharge($staff, $patient, $service, $price, ['quantity' => -1]),
            fn () => $billing->addCharge($staff, $patient, $service, $price, ['discount_amount' => 100001]),
        ] as $case) {
            try {
                $case();
                $this->fail('Expected billing validation exception was not thrown.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_duplicate_charge_idempotency_does_not_create_second_charge(): void
    {
        [$patient, $service, $price] = $this->billingSetup();
        $staff = $this->staffWithRole('cashier');
        $billing = app(BillingService::class);

        $first = $billing->addCharge($staff, $patient, $service, $price, [
            'idempotency_key' => 'heavy-regression-charge',
        ]);
        $second = $billing->addCharge($staff, $patient, $service, $price, [
            'idempotency_key' => 'heavy-regression-charge',
            'quantity' => 99,
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('charges', 1);
    }

    public function test_completed_and_cancelled_invoices_reject_mutating_workflows(): void
    {
        [$patient, $service, $price] = $this->billingSetup();
        $staff = $this->staffWithRole('cashier');
        $billing = app(BillingService::class);
        $charge = $billing->addCharge($staff, $patient, $service, $price);
        $paidInvoice = $billing->createInvoice($staff, $patient, [$charge]);
        $billing->recordPayment($staff, $paidInvoice, 100000, Payment::METHOD_CASH);

        try {
            $billing->cancelInvoice($staff, $paidInvoice, 'Cannot cancel a completed invoice.');
            $this->fail('Paid invoice cancellation should be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        try {
            $billing->recordPayment($staff, $paidInvoice, 1, Payment::METHOD_CASH);
            $this->fail('Payment against a completed invoice should be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $secondCharge = $billing->addCharge($staff, $patient, $service, $price);
        $cancelledInvoice = $billing->createInvoice($staff, $patient, [$secondCharge]);
        $billing->cancelInvoice($staff, $cancelledInvoice, 'Cancelled for regression test.');

        try {
            $billing->recordPayment($staff, $cancelledInvoice, 1, Payment::METHOD_CASH);
            $this->fail('Cancelled invoice payment should be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function test_inactive_staff_cannot_post_or_cancel_billing_work(): void
    {
        [$patient, $service, $price] = $this->billingSetup();
        $staff = $this->staffWithRole('cashier');
        $inactive = $this->staffWithRole('cashier', false);
        $billing = app(BillingService::class);

        $charge = $billing->addCharge($staff, $patient, $service, $price);
        $invoice = $billing->createInvoice($staff, $patient, [$charge]);

        try {
            $billing->recordPayment($inactive, $invoice, 1, Payment::METHOD_CASH);
            $this->fail('Inactive staff payment should be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        try {
            $billing->cancelInvoice($inactive, $invoice, 'Inactive staff test.');
            $this->fail('Inactive staff cancellation should be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function test_closed_encounter_and_cross_facility_records_are_rejected(): void
    {
        [$patient, $service, $price] = $this->billingSetup();
        $staff = $this->staffWithRole('cashier');
        $billing = app(BillingService::class);
        $encounter = $this->openEncounter($patient, $staff);
        $encounter->update(['status' => ClinicalEncounter::STATUS_CLOSED, 'closed_at' => now()]);

        try {
            $billing->addCharge($staff, $patient, $service, $price, ['encounter' => $encounter]);
            $this->fail('Closed encounter charge should be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $otherFacility = Facility::factory()->create();
        $otherService = BillableService::factory()->create(['facility_id' => $otherFacility->id, 'is_active' => true]);
        $otherPrice = ServicePrice::factory()->create([
            'facility_id' => $otherFacility->id,
            'billable_service_id' => $otherService->id,
            'amount' => 200000,
            'currency' => 'UGX',
            'effective_from' => now()->subDay()->toDateString(),
            'effective_to' => null,
            'is_active' => true,
        ]);

        try {
            $billing->addCharge($staff, $patient, $otherService, $otherPrice);
            $this->fail('Cross-facility service should be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function test_model_relationships_link_charge_invoice_line_item_payment_and_encounter(): void
    {
        [$patient, $service, $price] = $this->billingSetup();
        $staff = $this->staffWithRole('cashier');
        $billing = app(BillingService::class);
        $encounter = $this->openEncounter($patient, $staff);
        $charge = $billing->addCharge($staff, $patient, $service, $price, ['encounter' => $encounter]);
        $invoice = $billing->createInvoice($staff, $patient, [$charge], ['encounter_id' => $encounter->id]);
        $payment = $billing->recordPayment($staff, $invoice, 50000, Payment::METHOD_CASH);

        $this->assertTrue($charge->patient()->exists());
        $this->assertSame($encounter->id, $charge->encounter->id);
        $this->assertSame($invoice->id, $charge->invoiceLineItem->invoice_id);
        $this->assertSame($charge->id, $invoice->lineItems->first()->charge_id);
        $this->assertSame($invoice->id, $payment->invoice_id);
        $this->assertSame($patient->id, $invoice->patient_id);
        $this->assertSame($encounter->id, $invoice->encounter_id);
    }

    private function billingSetup(): array
    {
        $facility = Facility::factory()->create();
        $patient = Patient::factory()->create([
            'facility_id' => $facility->id,
            'status' => Patient::STATUS_ACTIVE,
        ]);
        $service = BillableService::factory()->create([
            'facility_id' => $facility->id,
            'is_active' => true,
        ]);
        $price = ServicePrice::factory()->create([
            'facility_id' => $facility->id,
            'billable_service_id' => $service->id,
            'amount' => 100000,
            'currency' => 'UGX',
            'effective_from' => now()->subDay()->toDateString(),
            'effective_to' => null,
            'is_active' => true,
        ]);

        return [$patient, $service, $price];
    }

    private function openEncounter(Patient $patient, User $clinician): ClinicalEncounter
    {
        $department = Department::factory()->create(['facility_id' => $patient->facility_id]);
        $servicePoint = ServicePoint::factory()->create(['department_id' => $department->id]);

        return ClinicalEncounter::create([
            'facility_id' => $patient->facility_id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id,
            'clinician_id' => $clinician->id,
            'encounter_number' => 'ENC-HEAVY-'.uniqid(),
            'type' => ClinicalEncounter::TYPE_OUTPATIENT,
            'status' => ClinicalEncounter::STATUS_OPEN,
            'started_at' => now()->subHour(),
            'closed_at' => null,
        ]);
    }

    private function staffWithRole(string $roleSlug, bool $active = true): User
    {
        $user = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => $active,
        ]);
        $user->roles()->attach(Role::where('slug', $roleSlug)->firstOrFail());

        return $user;
    }
}
