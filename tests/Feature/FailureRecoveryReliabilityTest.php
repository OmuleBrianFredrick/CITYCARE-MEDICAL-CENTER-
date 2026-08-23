<?php

namespace Tests\Feature;

use App\Models\BillableService;
use App\Models\Charge;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\ServicePrice;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FailureRecoveryReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_idempotent_charge_recovery_returns_existing_charge_without_duplicate(): void
    {
        [$staff, $patient, $service, $price] = $this->billingContext();
        $billing = app(BillingService::class);

        $first = $billing->addCharge($staff, $patient, $service, $price, [
            'quantity' => 1,
            'idempotency_key' => 'charge-retry-001',
        ]);

        $retry = $billing->addCharge($staff, $patient, $service, $price, [
            'quantity' => 1,
            'idempotency_key' => 'charge-retry-001',
        ]);

        $this->assertSame($first->id, $retry->id);
        $this->assertSame(1, Charge::query()->where('idempotency_key', 'charge-retry-001')->count());
    }

    public function test_failed_charge_creation_leaves_no_partial_charge(): void
    {
        [$staff, $patient, $service, $price] = $this->billingContext();
        $billing = app(BillingService::class);

        try {
            $billing->addCharge($staff, $patient, $service, $price, [
                'quantity' => 1,
                'discount_amount' => 999999,
                'idempotency_key' => 'charge-invalid-001',
            ]);
            $this->fail('Invalid charge input should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('discount_amount', $exception->errors());
        }

        $this->assertSame(0, Charge::query()->where('idempotency_key', 'charge-invalid-001')->count());
    }

    private function billingContext(): array
    {
        $facility = Facility::factory()->create();
        $staff = User::factory()->create([
            'facility_id' => $facility->id,
            'user_type' => 'staff',
            'is_active' => true,
        ]);
        $patient = Patient::factory()->create([
            'facility_id' => $facility->id,
            'is_active' => true,
        ]);
        $service = BillableService::factory()->create([
            'facility_id' => $facility->id,
            'is_active' => true,
        ]);
        $price = ServicePrice::factory()->create([
            'facility_id' => $facility->id,
            'billable_service_id' => $service->id,
            'is_active' => true,
            'amount' => 1000,
            'effective_from' => now()->subDay(),
            'effective_to' => null,
            'currency' => 'UGX',
        ]);

        return [$staff, $patient, $service, $price];
    }
}
