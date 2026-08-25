<?php

namespace Tests\Feature;

use App\Models\BillableService;
use App\Models\Charge;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\ServicePrice;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\BillingService;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BillingControllerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CityCareAccessSeeder::class);
    }

    public function test_cashier_can_complete_charge_invoice_and_payment_http_workflow(): void
    {
        [$patient, $service, $price, $encounter] = $this->billingSetup();
        $cashier = $this->staffWithRole('cashier', $patient->facility);

        $this->actingAs($cashier)
            ->post(route('billing.charges.store', $patient), [
                'billable_service_id' => $service->id,
                'service_price_id' => $price->id,
                'quantity' => 2,
                'encounter_id' => $encounter->id,
                'description' => 'Consultation',
                'idempotency_key' => 'http-charge-001',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Billing charge created successfully.');

        $charge = Charge::query()->where('idempotency_key', 'http-charge-001')->firstOrFail();
        $this->assertSame($encounter->id, $charge->encounter_id);
        $this->assertSame(Charge::STATUS_PENDING, $charge->status);

        $this->actingAs($cashier)
            ->post(route('billing.invoices.store', $patient), [
                'charges' => [$charge->id],
                'encounter_id' => $encounter->id,
                'notes' => 'HTTP workflow invoice',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Invoice created successfully.');

        $invoice = Invoice::query()->where('patient_id', $patient->id)->latest('id')->firstOrFail();
        $this->assertSame($encounter->id, $invoice->encounter_id);
        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->status);
        $this->assertSame(Charge::STATUS_INVOICED, $charge->refresh()->status);

        $this->actingAs($cashier)
            ->post(route('billing.payments.store', $invoice), [
                'amount' => 100000,
                'method' => 'cash',
                'transaction_reference' => 'CASH-001',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Payment recorded successfully.');

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $invoice->status);
        $this->assertSame(100000.0, (float) $invoice->paid_amount);

        $this->actingAs($cashier)
            ->post(route('billing.payments.store', $invoice), [
                'amount' => 100000,
                'method' => 'mobile_money',
                'transaction_reference' => 'MM-001',
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertSame(0.0, (float) $invoice->balance_due);
    }

    public function test_charge_request_rejects_invalid_input_before_service_execution(): void
    {
        [$patient, $service, $price] = $this->billingSetup();
        $cashier = $this->staffWithRole('cashier', $patient->facility);

        $this->actingAs($cashier)
            ->post(route('billing.charges.store', $patient), [
                'billable_service_id' => $service->id,
                'service_price_id' => $price->id,
                'quantity' => 0,
                'discount_amount' => -1,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['quantity', 'discount_amount']);

        $this->assertDatabaseCount('charges', 0);
    }

    public function test_invoice_request_rejects_duplicate_charge_ids(): void
    {
        [$patient, $service, $price] = $this->billingSetup();
        $cashier = $this->staffWithRole('cashier', $patient->facility);
        $charge = app(BillingService::class)->addCharge($cashier, $patient, $service, $price);

        $this->actingAs($cashier)
            ->post(route('billing.invoices.store', $patient), [
                'charges' => [$charge->id, $charge->id],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('charges.1');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_invoice_workflow_rejects_closed_encounter_linkage(): void
    {
        [$patient, $service, $price, $encounter] = $this->billingSetup();
        $cashier = $this->staffWithRole('cashier', $patient->facility);
        $charge = app(BillingService::class)->addCharge($cashier, $patient, $service, $price, ['encounter' => $encounter]);

        $encounter->update(['status' => ClinicalEncounter::STATUS_CLOSED, 'closed_at' => now()]);

        $this->actingAs($cashier)
            ->post(route('billing.invoices.store', $patient), [
                'charges' => [$charge->id],
                'encounter_id' => $encounter->id,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('encounter_id');

        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame(Charge::STATUS_PENDING, $charge->refresh()->status);
    }

    public function test_payment_workflow_rejects_overpayment_and_completed_invoice_payment(): void
    {
        [$patient, $service, $price] = $this->billingSetup();
        $cashier = $this->staffWithRole('cashier', $patient->facility);
        $billing = app(BillingService::class);
        $charge = $billing->addCharge($cashier, $patient, $service, $price);
        $invoice = $billing->createInvoice($cashier, $patient, [$charge]);

        $this->actingAs($cashier)
            ->post(route('billing.payments.store', $invoice), [
                'amount' => 100001,
                'method' => 'cash',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payments', 0);

        $this->actingAs($cashier)
            ->post(route('billing.payments.store', $invoice), [
                'amount' => 100000,
                'method' => 'cash',
            ])
            ->assertRedirect();

        $this->actingAs($cashier)
            ->post(route('billing.payments.store', $invoice), [
                'amount' => 1,
                'method' => 'cash',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('invoice');
    }

    public function test_unpaid_invoice_can_be_cancelled_and_charge_is_released(): void
    {
        [$patient, $service, $price] = $this->billingSetup();
        $cashier = $this->staffWithRole('cashier', $patient->facility);
        $billing = app(BillingService::class);
        $charge = $billing->addCharge($cashier, $patient, $service, $price);
        $invoice = $billing->createInvoice($cashier, $patient, [$charge]);

        $this->actingAs($cashier)
            ->post(route('billing.invoices.cancel', $invoice), ['reason' => 'Created in error'])
            ->assertRedirect()
            ->assertSessionHas('status', 'Invoice cancelled successfully.');

        $this->assertSame(Invoice::STATUS_CANCELLED, $invoice->refresh()->status);
        $this->assertSame(Charge::STATUS_PENDING, $charge->refresh()->status);
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

        $staff = $this->staffWithRole('cashier', $facility);
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $servicePoint = ServicePoint::factory()->create(['department_id' => $department->id]);
        $encounter = ClinicalEncounter::create([
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id,
            'clinician_id' => $staff->id,
            'encounter_number' => 'ENC-HTTP-'.uniqid(),
            'type' => ClinicalEncounter::TYPE_OUTPATIENT,
            'status' => ClinicalEncounter::STATUS_OPEN,
            'started_at' => now()->subHour(),
            'closed_at' => null,
        ]);

        return [$patient, $service, $price, $encounter];
    }

    private function staffWithRole(string $roleSlug, ?Facility $facility = null): User
    {
        $user = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => true,
            'password' => Hash::make('Password123!'),
        ]);

        $user->roles()->attach(Role::where('slug', $roleSlug)->firstOrFail());

        if ($facility) {
            $department = Department::factory()->create(['facility_id' => $facility->id]);
            StaffProfile::create(['user_id' => $user->id, 'department_id' => $department->id]);
        }

        return $user;
    }
}
