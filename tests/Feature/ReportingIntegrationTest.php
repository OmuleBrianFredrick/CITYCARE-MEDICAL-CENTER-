<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\ReportDefinition;
use App\Models\ReportRun;
use App\Models\User;
use App\Services\ReportingService;
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

        DB::table('clinical_encounters')->insert([
            ['patient_id' => null, 'facility_id' => $facility->id, 'status' => 'open', 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)],
            ['patient_id' => null, 'facility_id' => $facility->id, 'status' => 'closed', 'created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)],
            ['patient_id' => null, 'facility_id' => $otherFacility->id, 'status' => 'open', 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)],
        ]);

        $definition = ReportDefinition::factory()->create([
            'code' => 'clinical_activity',
            'category' => 'clinical',
            'supported_filters' => ['facility_id', 'date_from', 'date_to'],
            'is_active' => true,
        ]);

        $run = app(ReportingService::class)->run($staff, $definition, [
            'facility_id' => $facility->id,
            'date_from' => Carbon::today()->subDays(7)->toDateString(),
            'date_to' => Carbon::today()->toDateString(),
        ], $facility->id);

        $result = $run->fresh()->result_metadata;
        $this->assertSame(1, $result['total_encounters']);
        $this->assertSame(['open' => 1], $result['by_status']);
    }

    public function test_laboratory_pharmacy_billing_and_inventory_reports_reflect_operational_records(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $facility = Facility::factory()->create();

        DB::table('laboratory_orders')->insert([
            ['patient_id' => null, 'facility_id' => $facility->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ['patient_id' => null, 'facility_id' => $facility->id, 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('prescriptions')->insert([
            ['patient_id' => null, 'encounter_id' => null, 'facility_id' => $facility->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('invoices')->insert([
            ['patient_id' => 1, 'facility_id' => $facility->id, 'status' => 'issued', 'subtotal' => 100, 'total' => 100, 'paid_amount' => 40, 'outstanding_balance' => 60, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $storeId = DB::table('inventory_stores')->insertGetId([
            'facility_id' => $facility->id, 'name' => 'Reporting Store', 'code' => 'RPT-'.uniqid(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $itemId = DB::table('inventory_items')->insertGetId([
            'facility_id' => $facility->id, 'name' => 'Reporting Item', 'code' => 'RPT-I-'.uniqid(), 'sku' => 'RPT-S-'.uniqid(), 'unit' => 'unit', 'reorder_level' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_stock_balances')->insert([
            'store_id' => $storeId, 'inventory_item_id' => $itemId, 'quantity_on_hand' => 12, 'quantity_available' => 10, 'quantity_reserved' => 2, 'created_at' => now(), 'updated_at' => now(),
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
            $run = app(ReportingService::class)->run($staff, $definition, ['facility_id' => $facility->id], $facility->id);
            $this->assertSame(ReportRun::STATUS_COMPLETED, $run->fresh()->status);
        }

        $billing = app(ReportingService::class)->run($staff, ReportDefinition::where('code', 'billing_summary')->latest('id')->first(), ['facility_id' => $facility->id], $facility->id);
        $this->assertSame(1, $billing->result_metadata['invoice_count']);
        $this->assertSame(100.0, $billing->result_metadata['total']);
        $this->assertSame(40.0, $billing->result_metadata['paid']);
        $this->assertSame(60.0, $billing->result_metadata['outstanding']);

        $inventory = app(ReportingService::class)->run($staff, ReportDefinition::where('code', 'inventory_summary')->latest('id')->first(), ['facility_id' => $facility->id], $facility->id);
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

        app(ReportingService::class)->run($staff, $definition);

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
