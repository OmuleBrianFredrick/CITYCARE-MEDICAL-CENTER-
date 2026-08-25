<?php

namespace Tests\Feature;

use App\Models\BillableService;
use App\Models\Charge;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Role;
use App\Models\ServicePrice;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\BillingService;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingWorkspaceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CityCareAccessSeeder::class);
    }

    public function test_billing_queue_and_actions_are_isolated_to_the_staff_facility(): void
    {
        $facility = Facility::factory()->create(['is_active' => true]);
        $otherFacility = Facility::factory()->create(['is_active' => true]);
        $cashier = $this->cashierAt($facility);
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $otherPatient = Patient::factory()->create(['facility_id' => $otherFacility->id]);

        $invoice = Invoice::factory()->issued()->create([
            'facility_id' => $facility->id,
            'patient_id' => $patient->id,
            'invoice_number' => 'INV-OWN-001',
            'subtotal' => 100000,
            'total' => 100000,
            'balance_due' => 100000,
        ]);
        $otherInvoice = Invoice::factory()->issued()->create([
            'facility_id' => $otherFacility->id,
            'patient_id' => $otherPatient->id,
            'invoice_number' => 'INV-OTHER-001',
            'subtotal' => 200000,
            'total' => 200000,
            'balance_due' => 200000,
        ]);

        $this->actingAs($cashier)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertDontSee($otherInvoice->invoice_number);

        $this->actingAs($cashier)->get(route('billing.show', $otherPatient))->assertForbidden();
        $this->actingAs($cashier)
            ->post(route('billing.payments.store', $otherInvoice), ['amount' => 1000, 'method' => Payment::METHOD_CASH])
            ->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cashier_can_invoice_pending_charge_and_record_partial_payment_from_rendered_workflow(): void
    {
        $facility = Facility::factory()->create(['is_active' => true]);
        $cashier = $this->cashierAt($facility);
        $patient = Patient::factory()->create(['facility_id' => $facility->id, 'status' => Patient::STATUS_ACTIVE]);
        $service = BillableService::factory()->create(['facility_id' => $facility->id, 'name' => 'Consultation', 'is_active' => true]);
        $price = ServicePrice::factory()->create([
            'facility_id' => $facility->id,
            'billable_service_id' => $service->id,
            'amount' => 100000,
            'currency' => 'UGX',
            'effective_from' => today(),
            'effective_to' => null,
            'is_active' => true,
        ]);
        $charge = app(BillingService::class)->addCharge($cashier, $patient, $service, $price);

        $this->actingAs($cashier)
            ->get(route('billing.show', $patient))
            ->assertOk()
            ->assertSee('Create charge')
            ->assertSee('Create invoice')
            ->assertSee($service->name);

        $this->actingAs($cashier)
            ->post(route('billing.invoices.store', $patient), ['charges' => [$charge->id], 'notes' => 'Counter invoice'])
            ->assertRedirect();

        $invoice = Invoice::query()->where('patient_id', $patient->id)->firstOrFail();
        $this->assertSame(Charge::STATUS_INVOICED, $charge->refresh()->status);

        $this->actingAs($cashier)
            ->post(route('billing.payments.store', $invoice), [
                'amount' => 40000,
                'method' => Payment::METHOD_MOBILE_MONEY,
                'transaction_reference' => 'MM-COUNTER-001',
            ])
            ->assertRedirect();

        $invoice->refresh();
        $payment = Payment::query()->where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $invoice->status);
        $this->assertSame(60000.0, (float) $invoice->balance_due);

        $this->actingAs($cashier)
            ->get(route('billing.show', $patient))
            ->assertOk()
            ->assertSee($payment->receipt_number)
            ->assertSee('MM-COUNTER-001');
    }

    public function test_cashier_can_void_pending_charge_and_refund_completed_payment_with_audit_reason(): void
    {
        $facility = Facility::factory()->create(['is_active' => true]);
        $cashier = $this->cashierAt($facility);
        $patient = Patient::factory()->create(['facility_id' => $facility->id, 'status' => Patient::STATUS_ACTIVE]);
        $service = BillableService::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);
        $price = ServicePrice::factory()->create([
            'facility_id' => $facility->id,
            'billable_service_id' => $service->id,
            'amount' => 50000,
            'currency' => 'UGX',
            'effective_from' => today(),
            'effective_to' => null,
            'is_active' => true,
        ]);
        $billing = app(BillingService::class);
        $voidedCharge = $billing->addCharge($cashier, $patient, $service, $price);

        $this->actingAs($cashier)->post(route('billing.charges.void', $voidedCharge), [
            'reason' => 'Charge entered against the wrong service.',
        ])->assertRedirect();

        $this->assertSame(Charge::STATUS_VOIDED, $voidedCharge->fresh()->status);

        $charge = $billing->addCharge($cashier, $patient, $service, $price);
        $invoice = $billing->createInvoice($cashier, $patient, [$charge]);
        $payment = $billing->recordPayment($cashier, $invoice, 50000, Payment::METHOD_CASH);

        $this->actingAs($cashier)->post(route('billing.payments.reverse', $payment), [
            'action' => 'refund',
            'reason' => 'Patient refund approved by finance.',
        ])->assertRedirect();

        $this->assertSame(Payment::STATUS_REFUNDED, $payment->fresh()->status);
        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->fresh()->status);
        $this->assertSame(0.0, (float) $invoice->fresh()->paid_amount);
        $this->assertSame(50000.0, (float) $invoice->fresh()->balance_due);

        $this->actingAs($cashier)
            ->get(route('billing.show', $patient))
            ->assertOk()
            ->assertSee('Patient refund approved by finance.');
    }

    private function cashierAt(Facility $facility): User
    {
        $cashier = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $cashier->roles()->attach(Role::query()->where('slug', 'cashier')->valueOrFail('id'));
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        StaffProfile::create(['user_id' => $cashier->id, 'department_id' => $department->id]);

        return $cashier;
    }
}
