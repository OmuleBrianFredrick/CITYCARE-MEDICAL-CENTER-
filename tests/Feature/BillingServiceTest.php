<?php

namespace Tests\Feature;

use App\Models\BillableService;
use App\Models\Charge;
use App\Models\ClinicalEncounter;
use App\Models\Facility;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\ServicePrice;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BillingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
    }

    private function billingSetup(): array
    {
        $facility = Facility::factory()->create();
        $patient = Patient::factory()->create(['facility_id' => $facility->id, 'status' => Patient::STATUS_ACTIVE]);
        $service = BillableService::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);
        $price = ServicePrice::factory()->create([
            'facility_id' => $facility->id,
            'billable_service_id' => $service->id,
            'amount' => 100000,
            'currency' => 'UGX',
            'effective_from' => now()->subDay()->toDateString(),
            'effective_to' => null,
            'is_active' => true,
        ]);

        return [$facility, $patient, $service, $price];
    }

    public function test_it_creates_a_valid_charge_with_correct_totals(): void
    {
        [$facility, $patient, $service, $price] = $this->billingSetup();
        $charge = app(BillingService::class)->addCharge($this->staff(), $patient, $service, $price, [
            'quantity' => 2,
            'discount_amount' => 10000,
            'adjustment_amount' => 5000,
            'idempotency_key' => 'clinical-activity-001',
        ]);

        $this->assertSame(200000.0, (float) $charge->subtotal);
        $this->assertSame(195000.0, (float) $charge->total);
        $this->assertSame(Charge::STATUS_PENDING, $charge->status);
        $this->assertDatabaseHas('charges', ['id' => $charge->id, 'facility_id' => $facility->id]);
    }

    public function test_it_rejects_inactive_staff_and_patients(): void
    {
        [, $patient, $service, $price] = $this->billingSetup();
        $inactiveStaff = User::factory()->create(['user_type' => 'staff', 'is_active' => false]);
        $this->expectException(ValidationException::class);
        app(BillingService::class)->addCharge($inactiveStaff, $patient, $service, $price);
    }

    public function test_it_rejects_closed_encounters(): void
    {
        [, $patient, $service, $price] = $this->billingSetup();
        $staff = $this->staff();
        $encounter = ClinicalEncounter::create([
            'facility_id' => $patient->facility_id,
            'patient_id' => $patient->id,
            'clinician_id' => $staff->id,
            'encounter_number' => 'ENC-TEST-001',
            'type' => ClinicalEncounter::TYPE_OUTPATIENT,
            'status' => ClinicalEncounter::STATUS_CLOSED,
            'started_at' => now()->subHour(),
            'closed_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        app(BillingService::class)->addCharge($staff, $patient, $service, $price, ['encounter' => $encounter]);
    }

    public function test_idempotency_returns_the_existing_charge(): void
    {
        [, $patient, $service, $price] = $this->billingSetup();
        $staff = $this->staff();
        $serviceLayer = app(BillingService::class);
        $first = $serviceLayer->addCharge($staff, $patient, $service, $price, ['idempotency_key' => 'same-activity']);
        $second = $serviceLayer->addCharge($staff, $patient, $service, $price, ['idempotency_key' => 'same-activity', 'quantity' => 99]);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('charges', 1);
    }

    public function test_it_creates_an_invoice_and_locks_charges_as_invoiced(): void
    {
        [, $patient, $service, $price] = $this->billingSetup();
        $staff = $this->staff();
        $billing = app(BillingService::class);
        $first = $billing->addCharge($staff, $patient, $service, $price, ['quantity' => 1]);
        $second = $billing->addCharge($staff, $patient, $service, $price, ['quantity' => 2, 'description' => 'Repeat service']);
        $invoice = $billing->createInvoice($staff, $patient, [$first, $second]);

        $this->assertSame(300000.0, (float) $invoice->total);
        $this->assertSame(300000.0, (float) $invoice->balance_due);
        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->status);
        $this->assertDatabaseHas('charges', ['id' => $first->id, 'status' => Charge::STATUS_INVOICED]);
        $this->assertCount(2, $invoice->lineItems);
    }

    public function test_it_rejects_duplicate_invoicing_of_a_charge(): void
    {
        [, $patient, $service, $price] = $this->billingSetup();
        $staff = $this->staff();
        $billing = app(BillingService::class);
        $charge = $billing->addCharge($staff, $patient, $service, $price);
        $billing->createInvoice($staff, $patient, [$charge]);

        $this->expectException(ValidationException::class);
        $billing->createInvoice($staff, $patient, [$charge]);
    }

    public function test_it_supports_partial_and_full_payment_but_rejects_overpayment(): void
    {
        [, $patient, $service, $price] = $this->billingSetup();
        $staff = $this->staff();
        $billing = app(BillingService::class);
        $charge = $billing->addCharge($staff, $patient, $service, $price);
        $invoice = $billing->createInvoice($staff, $patient, [$charge]);

        $partial = $billing->recordPayment($staff, $invoice, 40000, Payment::METHOD_CASH);
        $invoice->refresh();
        $this->assertSame(Payment::STATUS_COMPLETED, $partial->status);
        $this->assertSame(40000.0, (float) $invoice->paid_amount);
        $this->assertSame(60000.0, (float) $invoice->balance_due);
        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $invoice->status);

        $billing->recordPayment($staff, $invoice, 60000, Payment::METHOD_MOBILE_MONEY);
        $invoice->refresh();
        $this->assertSame(100000.0, (float) $invoice->paid_amount);
        $this->assertSame(0.0, (float) $invoice->balance_due);
        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);

        $this->expectException(ValidationException::class);
        $billing->recordPayment($staff, $invoice, 1, Payment::METHOD_CASH);
    }

    public function test_it_cancels_an_unpaid_invoice_and_releases_its_charges(): void
    {
        [, $patient, $service, $price] = $this->billingSetup();
        $staff = $this->staff();
        $billing = app(BillingService::class);
        $charge = $billing->addCharge($staff, $patient, $service, $price);
        $invoice = $billing->createInvoice($staff, $patient, [$charge]);
        $billing->cancelInvoice($staff, $invoice, 'Duplicate invoice created in error.');

        $invoice->refresh();
        $charge->refresh();
        $this->assertSame(Invoice::STATUS_CANCELLED, $invoice->status);
        $this->assertSame(Charge::STATUS_PENDING, $charge->status);
    }
}
