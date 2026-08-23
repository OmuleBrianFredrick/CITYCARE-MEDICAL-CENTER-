<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\Facility;
use App\Models\Invoice;
use App\Models\InventoryItem;
use App\Models\InventoryStockBalance;
use App\Models\InventoryStore;
use App\Models\LaboratoryOrder;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\ReportDefinition;
use App\Models\ReportRun;
use App\Models\User;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinical_report_is_facility_and_date_scoped(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);

        $facility = Facility::factory()->create();
        $otherFacility = Facility::factory()->create();

        $includedPatient = Patient::factory()->create(['facility_id' => $facility->id]);
        $oldPatient = Patient::factory()->create(['facility_id' => $facility->id]);
        $otherPatient = Patient::factory()->create(['facility_id' => $otherFacility->id]);

        $includedEncounter = ClinicalEncounter::factory()->create([
            'facility_id' => $facility->id,
            'patient_id' => $includedPatient->id,
            'started_at' => now()->subDays(2),
            'status' => ClinicalEncounter::STATUS_OPEN,
        ]);
        $oldEncounter = ClinicalEncounter::factory()->create([
            'facility_id' => $facility->id,
            'patient_id' => $oldPatient->id,
            'started_at' => now()->subDays(10),
            'status' => ClinicalEncounter::STATUS_CLOSED,
        ]);
        $otherEncounter = ClinicalEncounter::factory()->create([
            'facility_id' => $otherFacility->id,
            'patient_id' => $otherPatient->id,
            'started_at' => now()->subDays(2),
            'status' => ClinicalEncounter::STATUS_OPEN,
        ]);

        $includedEncounter->update(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]);
        $oldEncounter->update(['created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)]);
        $otherEncounter->update(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]);

        $definition = ReportDefinition::factory()->create([
            'code' => 'clinical_activity',
            'category' => 'clinical',
            'supported_filters' => ['facility_id', 'date_from', 'date_to'],
            'is_active' => true,
        ]);

        $run = app(\App\Services\ReportingService::class)->run($staff, $definition, [
            'facility_id' => $facility->id,
            'date_from' => Carbon::today()->subDays(7)->toDateString(),
            'date_to' => Carbon::today()->toDateString(),
        ], $facility->id);

        $result = $run->fresh()->result_metadata;
        $this->assertSame(2, $result['total_encounters']);
        $this->assertSame(['closed' => 1, 'open' => 1], $result['by_status']);
    }

    public function test_laboratory_pharmacy_billing_and_inventory_reports_reflect_operational_records(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $facility = Facility::factory()->create();

        $labPatient = Patient::factory()->create(['facility_id' => $facility->id]);
        $labPatient2 = Patient::factory()->create(['facility_id' => $facility->id]);
        $labOrder1 = LaboratoryOrder::factory()->create(['facility_id' => $facility->id, 'patient_id' => $labPatient->id]);
        $labOrder2 = LaboratoryOrder::factory()->create(['facility_id' => $facility->id, 'patient_id' => $labPatient2->id]);
        $labOrder1->update(['status' => LaboratoryOrder::STATUS_ORDERED]);
        $labOrder2->update(['status' => LaboratoryOrder::STATUS_COMPLETED]);

        Prescription::factory()->create(['facility_id' => $facility->id, 'patient_id' => $labPatient->id]);

        Invoice::factory()->create([
            'facility_id' => $facility->id,
            'patient_id' => $labPatient->id,
            'status' => Invoice::STATUS_ISSUED,
            'subtotal' => 100,
            'total' => 100,
            'paid_amount' => 40,
            'balance_due' => 60,
        ]);

        $store = InventoryStore::factory()->create(['facility_id' => $facility->id, 'name' => 'Reporting Store']);
        $item = InventoryItem::factory()->create(['facility_id' => $facility->id, 'name' => 'Reporting Item']);
        InventoryStockBalance::factory()->create([
            'store_id' => $store->id,
            'inventory_item_id' => $item->id,
            'quantity_on_hand' => 12,
            'quantity_available' => 10,
            'quantity_reserved' => 2,
        ]);

        foreach ([
            'laboratory_activity' => ['laboratory', ['facility_id']],
            'pharmacy_activity' => ['pharmacy', ['facility_id']],
            'billing_summary' => ['billing', ['facility_id']],
            'inventory_summary' => ['inventory', ['facility_id']],
        ] as $code => [$category, $filters]) {
            $definition = ReportDefinition::factory()->create([
                'code' => $code,
                'category' => $category,
                'supported_filters' => $filters,
                'is_active' => true,
            ]);
            $run = app(\App\Services\ReportingService::class)->run($staff, $definition, ['facility_id' => $facility->id], $facility->id);
            $this->assertSame(ReportRun::STATUS_COMPLETED, $run->fresh()->status);
        }

        $billingDefinition = ReportDefinition::where('code', 'billing_summary')->latest('id')->firstOrFail();
        $billing = app(\App\Services\ReportingService::class)->run($staff, $billingDefinition, ['facility_id' => $facility->id], $facility->id);
        $this->assertSame(1, $billing->result_metadata['invoice_count']);
        $this->assertSame(100.0, (float) $billing->result_metadata['total']);
        $this->assertSame(40.0, (float) $billing->result_metadata['paid']);
        $this->assertSame(60.0, (float) $billing->result_metadata['outstanding']);

        $inventoryDefinition = ReportDefinition::where('code', 'inventory_summary')->latest('id')->firstOrFail();
        $inventory = app(\App\Services\ReportingService::class)->run($staff, $inventoryDefinition, ['facility_id' => $facility->id], $facility->id);
        $this->assertSame(1, $inventory->result_metadata['stock_line_count']);
        $this->assertSame(12.0, $inventory->result_metadata['quantity_on_hand']);
        $this->assertSame(10.0, $inventory->result_metadata['quantity_available']);
        $this->assertSame(2.0, $inventory->result_metadata['quantity_reserved']);
    }

    public function test_reporting_does_not_mutate_operational_data(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $definition = ReportDefinition::factory()->create([
            'code' => 'inventory_summary',
            'category' => 'inventory',
            'supported_filters' => [],
            'is_active' => true,
        ]);

        $before = [
            'invoices' => DB::table('invoices')->count(),
            'clinical_encounters' => DB::table('clinical_encounters')->count(),
            'laboratory_orders' => DB::table('laboratory_orders')->count(),
            'prescriptions' => DB::table('prescriptions')->count(),
            'inventory_stock_balances' => DB::table('inventory_stock_balances')->count(),
        ];

        app(\App\Services\ReportingService::class)->run($staff, $definition);

        $after = [
            'invoices' => DB::table('invoices')->count(),
            'clinical_encounters' => DB::table('clinical_encounters')->count(),
            'laboratory_orders' => DB::table('laboratory_orders')->count(),
            'prescriptions' => DB::table('prescriptions')->count(),
            'inventory_stock_balances' => DB::table('inventory_stock_balances')->count(),
        ];

        $this->assertSame($before, $after);
    }
}
