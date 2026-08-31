<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\CityCareDemoDataSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductionBootstrapSafetyTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_EMAIL = 'bootstrap-admin@citycare.test';

    private const ADMIN_PASSWORD = 'BootstrapPassword2026!';

    public function test_clean_seed_can_create_a_working_super_administrator(): void
    {
        $this->configureBootstrapAdministrator();

        $this->seed(DatabaseSeeder::class);

        $administrator = User::query()->where('email', self::ADMIN_EMAIL)->firstOrFail();
        $this->assertTrue($administrator->isStaff());
        $this->assertTrue($administrator->isActive());
        $this->assertTrue($administrator->hasRole('super-admin'));
        $this->assertTrue(Hash::check(self::ADMIN_PASSWORD, $administrator->password));

        $this->post(route('login.store'), [
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_PASSWORD,
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($administrator);
    }

    public function test_repeated_base_seed_preserves_live_customization_and_existing_admin_state(): void
    {
        $this->configureBootstrapAdministrator();
        $this->seed(DatabaseSeeder::class);

        $facility = Facility::query()->firstOrFail();
        $facility->update(['name' => 'CityCare Customized Campus']);

        $department = Department::query()->where('code', 'ADMIN')->firstOrFail();
        $department->update(['name' => 'Customized Executive Office', 'is_active' => false]);

        $servicePoint = ServicePoint::query()->where('code', 'ADMIN-MAIN')->firstOrFail();
        $servicePoint->update(['name' => 'Customized Administration Desk']);

        $setting = SystemSetting::query()->where('key', 'appointments.default_duration')->firstOrFail();
        $setting->update(['value' => '75', 'description' => 'Customized appointment duration.']);

        $administratorRole = Role::query()->where('slug', 'administrator')->firstOrFail();
        $administratorRole->update(['name' => 'Customized Operations Manager']);
        $reportsPermission = Permission::query()->where('slug', 'reports.view')->firstOrFail();
        $administratorRole->permissions()->detach($reportsPermission);

        $patientRole = Role::query()->where('slug', 'patient')->firstOrFail();
        $patientPortalPermission = Permission::query()->where('slug', 'patient-portal.manage')->firstOrFail();
        $staffPermission = Permission::query()->where('slug', 'staff.manage')->firstOrFail();
        $patientRole->permissions()->detach($patientPortalPermission);
        $patientRole->permissions()->attach($staffPermission);

        $bootstrapAdministrator = User::query()->where('email', self::ADMIN_EMAIL)->firstOrFail();
        $retainedPassword = Hash::make('RetainedPassword2026!');
        $bootstrapAdministrator->update([
            'name' => 'Retained Administrator Name',
            'password' => $retainedPassword,
            'is_active' => false,
        ]);
        config()->set('citycare.bootstrap_admin.password', 'DifferentBootstrapPassword2026!');

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('facilities', 1);
        $this->assertSame('CityCare Customized Campus', $facility->fresh()->name);
        $this->assertSame('Customized Executive Office', $department->fresh()->name);
        $this->assertFalse($department->fresh()->is_active);
        $this->assertSame('Customized Administration Desk', $servicePoint->fresh()->name);
        $this->assertSame('75', $setting->fresh()->value);
        $this->assertSame('Customized appointment duration.', $setting->fresh()->description);
        $this->assertSame('Customized Operations Manager', $administratorRole->fresh()->name);
        $this->assertFalse($administratorRole->fresh()->permissions()->whereKey($reportsPermission->id)->exists());
        $this->assertTrue($patientRole->fresh()->permissions()->whereKey($patientPortalPermission->id)->exists());
        $this->assertFalse($patientRole->fresh()->permissions()->whereKey($staffPermission->id)->exists());

        $bootstrapAdministrator->refresh();
        $this->assertSame('Retained Administrator Name', $bootstrapAdministrator->name);
        $this->assertFalse($bootstrapAdministrator->is_active);
        $this->assertSame($retainedPassword, $bootstrapAdministrator->password);
        $this->assertTrue($bootstrapAdministrator->hasRole('super-admin'));
    }

    public function test_demo_data_seeder_refuses_to_run_in_production_before_writing_data(): void
    {
        $originalEnvironment = $this->app->environment();
        $this->app->detectEnvironment(fn (): string => 'production');
        config()->set('citycare.demo_password', 'DemoPassword2026!');

        try {
            app(CityCareDemoDataSeeder::class)->run();
            $this->fail('The demo data seeder must not run in production.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('environment', $exception->errors());
        } finally {
            $this->app->detectEnvironment(fn (): string => $originalEnvironment);
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('facilities', 0);
        $this->assertDatabaseCount('roles', 0);
    }

    public function test_department_migration_backfills_existing_encounters_before_enforcing_required_column(): void
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $servicePoint = ServicePoint::factory()->create(['department_id' => $department->id]);
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $clinician = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $encounter = ClinicalEncounter::factory()->create([
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id,
            'clinician_id' => $clinician->id,
        ]);
        $migration = require database_path('migrations/2026_08_19_211000_add_department_to_clinical_encounters.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('clinical_encounters', 'department_id'));

        $migration->up();

        $this->assertTrue(Schema::hasColumn('clinical_encounters', 'department_id'));
        $this->assertDatabaseHas('clinical_encounters', [
            'id' => $encounter->id,
            'department_id' => $department->id,
        ]);
    }

    public function test_pharmacy_foundation_migration_never_drops_an_existing_catalog(): void
    {
        $medication = Medication::factory()->create(['name' => 'Protected Existing Medication']);
        $migration = require database_path('migrations/2026_08_22_140000_create_pharmacy_tables.php');

        try {
            $migration->up();
            $this->fail('Reapplying a create migration to an existing schema should fail safely.');
        } catch (\LogicException) {
            $this->assertDatabaseHas('medications', [
                'id' => $medication->id,
                'name' => 'Protected Existing Medication',
            ]);
        }
    }

    public function test_department_migration_rejects_invalid_backfill_before_changing_schema_and_can_be_retried(): void
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $servicePoint = ServicePoint::factory()->create(['department_id' => $department->id]);
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $clinician = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $encounter = ClinicalEncounter::factory()->create([
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id,
            'clinician_id' => $clinician->id,
        ]);
        $migration = require database_path('migrations/2026_08_19_211000_add_department_to_clinical_encounters.php');

        $migration->down();
        $servicePoint->update(['department_id' => null]);

        try {
            $migration->up();
            $this->fail('The migration must reject an encounter whose service point has no department.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString((string) $encounter->id, $exception->getMessage());
        }

        $this->assertFalse(Schema::hasColumn('clinical_encounters', 'department_id'));

        $servicePoint->update(['department_id' => $department->id]);
        $migration->up();

        $this->assertTrue(Schema::hasColumn('clinical_encounters', 'department_id'));
        $this->assertDatabaseHas('clinical_encounters', [
            'id' => $encounter->id,
            'department_id' => $department->id,
        ]);
    }

    private function configureBootstrapAdministrator(): void
    {
        config()->set('citycare.bootstrap_admin.email', self::ADMIN_EMAIL);
        config()->set('citycare.bootstrap_admin.password', self::ADMIN_PASSWORD);
    }
}
