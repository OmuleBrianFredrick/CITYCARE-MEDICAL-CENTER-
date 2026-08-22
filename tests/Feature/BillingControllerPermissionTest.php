<?php

namespace Tests\Feature;

use App\Models\BillableService;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Role;
use App\Models\ServicePrice;
use App\Models\User;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BillingControllerPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CityCareAccessSeeder::class);
    }

    public function test_cashier_can_view_billing(): void
    {
        [$patient] = $this->billingSetup();
        $cashier = $this->staffWithRole('cashier');

        $this->actingAs($cashier)->get(route('billing.show', $patient))->assertOk();
    }

    public function test_doctor_and_receptionist_can_view_billing_but_cannot_manage_financial_workflows(): void
    {
        [$patient] = $this->billingSetup();

        foreach (['doctor', 'receptionist'] as $role) {
            $user = $this->staffWithRole($role);
            $this->actingAs($user)->get(route('billing.show', $patient))->assertOk();
            $this->actingAs($user)->post(route('billing.charges.store', $patient), [
                'billable_service_id' => 1,
                'service_price_id' => 1,
                'quantity' => 1,
            ])->assertForbidden();
        }
    }

    public function test_cashier_can_enter_financial_workflows(): void
    {
        [$patient, $service, $price] = $this->billingSetup();
        $cashier = $this->staffWithRole('cashier');

        $this->actingAs($cashier)->post(route('billing.charges.store', $patient), [
            'billable_service_id' => $service->id,
            'service_price_id' => $price->id,
            'quantity' => 1,
        ])->assertRedirect();
    }

    public function test_pharmacy_and_laboratory_staff_have_no_billing_write_access(): void
    {
        [$patient, $service, $price] = $this->billingSetup();

        foreach (['pharmacy', 'laboratory'] as $role) {
            $user = $this->staffWithRole($role);
            $this->actingAs($user)->get(route('billing.show', $patient))->assertForbidden();
            $this->actingAs($user)->post(route('billing.charges.store', $patient), [
                'billable_service_id' => $service->id,
                'service_price_id' => $price->id,
                'quantity' => 1,
            ])->assertForbidden();
        }
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

        return [$patient, $service, $price];
    }

    private function staffWithRole(string $roleSlug): User
    {
        $user = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => true,
            'password' => Hash::make('Password123!'),
        ]);

        $user->roles()->attach(Role::where('slug', $roleSlug)->firstOrFail());

        return $user;
    }
}
