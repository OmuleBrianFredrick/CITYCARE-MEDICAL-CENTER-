<?php

namespace Tests\Feature;

use App\Models\InventoryStockBalance;
use App\Models\Invoice;
use App\Models\LaboratoryOrder;
use App\Models\MedicationDispensing;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PurchaseOrder;
use App\Models\User;
use Database\Seeders\CityCareDemoAccountSeeder;
use Database\Seeders\CityCareDemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CityCareDemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_PASSWORD = 'CityCareSeederTestBase2026';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('citycare.demo_password', self::BASE_PASSWORD);
    }

    public function test_it_creates_an_idempotent_cross_role_demo_environment(): void
    {
        $this->seed(CityCareDemoDataSeeder::class);

        $doctor = User::query()->where('email', 'doctor@citycare.test')->firstOrFail();
        $this->assertTrue(Hash::check(CityCareDemoAccountSeeder::passwordFor(self::BASE_PASSWORD, 'doctor'), $doctor->password));
        $this->assertTrue($doctor->hasRole('doctor'));
        $this->assertSame('OPD', $doctor->staffProfile->department->code);
        $this->assertSame('OPD-GENERAL', $doctor->staffProfile->servicePoint->code);

        $portalPatient = Patient::query()->where('medical_record_number', 'CC-DEMO-0001')->firstOrFail();
        $this->assertSame('patient@citycare.test', $portalPatient->user->email);
        $this->assertTrue($portalPatient->hasActivePortal());

        $this->assertDatabaseCount('users', 11);
        $this->assertDatabaseCount('staff_profiles', 10);
        $this->assertDatabaseCount('patients', 4);
        $this->assertDatabaseCount('appointments', 4);
        $this->assertDatabaseCount('clinical_encounters', 2);
        $this->assertDatabaseCount('laboratory_orders', 2);
        $this->assertDatabaseCount('prescriptions', 2);
        $this->assertDatabaseCount('invoices', 2);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('purchase_orders', 1);
        $this->assertDatabaseCount('goods_receipts', 1);
        $this->assertDatabaseCount('inventory_stock_movements', 4);

        $this->assertSame(1, LaboratoryOrder::query()->where('status', LaboratoryOrder::STATUS_ORDERED)->count());
        $this->assertSame(1, Prescription::query()->where('status', Prescription::STATUS_PRESCRIBED)->count());
        $this->assertSame(1, MedicationDispensing::query()->count());
        $this->assertSame(1, PurchaseOrder::query()->where('status', 'completed')->count());
        $this->assertSame(1, Invoice::query()->where('status', Invoice::STATUS_ISSUED)->count());
        $this->assertSame(1, Invoice::query()->where('status', Invoice::STATUS_PARTIALLY_PAID)->count());
        $this->assertSame(60.0, (float) InventoryStockBalance::query()
            ->whereHas('inventoryItem', fn ($query) => $query->where('code', 'PCM500'))
            ->value('quantity_available'));
        $this->assertSame(8.0, (float) InventoryStockBalance::query()
            ->whereHas('inventoryItem', fn ($query) => $query->where('code', 'AMOX500'))
            ->value('quantity_available'));

        $this->seed(CityCareDemoDataSeeder::class);

        $this->assertDatabaseCount('users', 11);
        $this->assertDatabaseCount('appointments', 4);
        $this->assertDatabaseCount('clinical_encounters', 2);
        $this->assertDatabaseCount('laboratory_orders', 2);
        $this->assertDatabaseCount('prescriptions', 2);
        $this->assertDatabaseCount('invoices', 2);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('purchase_orders', 1);
        $this->assertDatabaseCount('goods_receipts', 1);
        $this->assertDatabaseCount('inventory_stock_movements', 4);
    }
}
