<?php

namespace Tests\Feature;

use App\Models\BillableService;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Role;
use App\Models\ServicePrice;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingCatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CityCareAccessSeeder::class);
    }

    public function test_finance_staff_can_manage_facility_services_and_effective_prices(): void
    {
        $facility = Facility::factory()->create(['is_active' => true, 'currency' => 'UGX']);
        $cashier = $this->cashierAt($facility);

        $this->actingAs($cashier)->post(route('billing.catalogue.services.store'), [
            'code' => 'CONS-001',
            'name' => 'General Consultation',
            'category' => 'Consultation',
            'unit' => 'visit',
            'is_active' => 1,
        ])->assertRedirect();

        $service = BillableService::query()->where('facility_id', $facility->id)->where('code', 'CONS-001')->firstOrFail();

        $this->actingAs($cashier)->post(route('billing.catalogue.prices.store', $service), [
            'amount' => 75000,
            'currency' => 'UGX',
            'effective_from' => today()->toDateString(),
            'is_active' => 1,
            'notes' => 'Approved consultation tariff.',
        ])->assertRedirect();

        $price = ServicePrice::query()->where('billable_service_id', $service->id)->firstOrFail();
        $this->assertSame(75000.0, (float) $price->amount);

        $this->actingAs($cashier)
            ->get(route('billing.catalogue.index'))
            ->assertOk()
            ->assertSee($service->name)
            ->assertSee('75,000.00');

        $this->actingAs($cashier)->put(route('billing.catalogue.prices.update', $price), [
            'amount' => 80000,
            'currency' => 'UGX',
            'effective_from' => today()->toDateString(),
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame(80000.0, (float) $price->fresh()->amount);
    }

    public function test_billing_catalogue_updates_cannot_cross_facilities(): void
    {
        $facility = Facility::factory()->create(['is_active' => true]);
        $otherFacility = Facility::factory()->create(['is_active' => true]);
        $cashier = $this->cashierAt($facility);
        $otherService = BillableService::factory()->create(['facility_id' => $otherFacility->id, 'name' => 'Other Facility Service']);

        $this->actingAs($cashier)->put(route('billing.catalogue.services.update', $otherService), [
            'code' => $otherService->code,
            'name' => 'Forged update',
            'unit' => 'item',
            'is_active' => 1,
        ])->assertForbidden();

        $this->assertSame('Other Facility Service', $otherService->fresh()->name);
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
