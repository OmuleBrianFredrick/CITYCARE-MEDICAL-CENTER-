<?php

namespace Tests\Feature;

use App\Models\BillableService;
use App\Models\ClinicalEncounter;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Role;
use App\Models\ServicePrice;
use App\Models\User;
use App\Services\BillingService;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingClinicalWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_view_encounter_billing_summary_without_payment_management(): void
    {
        $this->seed(CityCareAccessSeeder::class);

        [$doctor, $encounter, $service, $price] = $this->workspaceContext('doctor');
        $admin = $this->userWithRole('administrator');
        $charge = app(BillingService::class)->addCharge($admin, $encounter->patient, $service, $price, [
            'encounter' => $encounter,
            'quantity' => 1,
            'description' => 'Consultation service',
        ]);
        $invoice = app(BillingService::class)->createInvoice($admin, $encounter->patient, [$charge], [
            'encounter_id' => $encounter->id,
        ]);
        app(BillingService::class)->recordPayment($admin, $invoice, 50000, 'cash');

        $this->actingAs($doctor)
            ->get(route('encounters.show', $encounter))
            ->assertOk()
            ->assertSee('Encounter billing', false)
            ->assertSee($service->name)
            ->assertSee($invoice->invoice_number)
            ->assertSee('Invoice total')
            ->assertSee('Amount paid')
            ->assertSee('Outstanding balance')
            ->assertDontSee('Create linked charge', false)
            ->assertDontSee('Record payment', false);
    }

    public function test_staff_without_billing_view_do_not_receive_billing_panel(): void
    {
        $this->seed(CityCareAccessSeeder::class);

        [, $encounter] = $this->workspaceContext('doctor');
        $nurse = $this->userWithRole('nurse');

        $this->actingAs($nurse)
            ->get(route('encounters.show', $encounter))
            ->assertOk()
            ->assertDontSee('Encounter billing', false)
            ->assertDontSee('Create linked charge', false);
    }

    public function test_authorized_financial_staff_with_clinical_visibility_can_link_charge_to_open_encounter(): void
    {
        $this->seed(CityCareAccessSeeder::class);

        [$doctor, $encounter, $service, $price] = $this->workspaceContext('doctor');
        $admin = $this->userWithRole('administrator');

        $this->actingAs($admin)
            ->get(route('encounters.show', $encounter))
            ->assertOk()
            ->assertSee('Create linked charge', false)
            ->assertSee($service->name);

        $this->actingAs($admin)
            ->post(route('billing.charges.store', $encounter->patient), [
                'billable_service_id' => $service->id,
                'service_price_id' => $price->id,
                'quantity' => 1,
                'encounter_id' => $encounter->id,
                'description' => 'Encounter-linked clinical service',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('charges', [
            'patient_id' => $encounter->patient_id,
            'encounter_id' => $encounter->id,
            'billable_service_id' => $service->id,
            'service_price_id' => $price->id,
        ]);
    }

    private function workspaceContext(string $roleSlug): array
    {
        $doctor = $this->userWithRole($roleSlug);
        $facility = Facility::factory()->create();
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $encounter = ClinicalEncounter::factory()->create([
            'facility_id' => $facility->id,
            'patient_id' => $patient->id,
            'clinician_id' => $doctor->id,
            'status' => ClinicalEncounter::STATUS_OPEN,
        ]);
        $service = BillableService::factory()->create([
            'facility_id' => $facility->id,
            'is_active' => true,
        ]);
        $price = ServicePrice::factory()->create([
            'facility_id' => $facility->id,
            'billable_service_id' => $service->id,
            'is_active' => true,
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'amount' => 100000,
            'currency' => 'UGX',
        ]);

        return [$doctor, $encounter, $service, $price];
    }

    private function userWithRole(string $roleSlug): User
    {
        $user = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $roleId = Role::where('slug', $roleSlug)->valueOrFail('id');
        $user->roles()->attach($roleId);
        return $user;
    }
}
