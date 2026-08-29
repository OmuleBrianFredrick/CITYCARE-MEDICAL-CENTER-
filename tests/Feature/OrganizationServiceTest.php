<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Facility;
use App\Models\ServicePoint;
use App\Models\SystemSetting;
use App\Services\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrganizationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_facility_can_be_created_and_retrieved_as_the_citycare_configuration(): void
    {
        $service = app(OrganizationService::class);

        $facility = $service->saveFacility([
            'name' => 'CityCare Medical Center',
            'phone' => '+256700000000',
            'email' => 'info@citycare.test',
            'city' => 'Kampala',
            'district' => 'Kampala',
        ]);

        $this->assertInstanceOf(Facility::class, $facility);
        $this->assertSame('CityCare Medical Center', $service->facility()?->name);
        $this->assertSame('Africa/Kampala', $facility->timezone);
        $this->assertSame('UGX', $facility->currency);
    }

    public function test_department_requires_a_configured_facility(): void
    {
        $this->expectException(ValidationException::class);

        app(OrganizationService::class)->createDepartment([
            'name' => 'Outpatient',
            'code' => 'OPD',
        ]);
    }

    public function test_department_and_service_point_are_linked_to_organization(): void
    {
        $service = app(OrganizationService::class);
        $facility = $service->saveFacility(['name' => 'CityCare Medical Center']);

        $department = $service->createDepartment([
            'name' => 'Outpatient Department',
            'code' => 'opd',
            'description' => 'General outpatient services.',
        ], $facility);

        $servicePoint = $service->createServicePoint([
            'department_id' => $department->id,
            'name' => 'Reception Desk',
            'code' => 'opd-reception',
            'type' => 'Reception',
            'location' => 'Ground Floor',
        ], $facility);

        $this->assertSame($facility->id, $department->facility_id);
        $this->assertSame('OPD', $department->code);
        $this->assertTrue($department->servicePoints()->whereKey($servicePoint->id)->exists());
        $this->assertSame($department->id, $servicePoint->department_id);
        $this->assertSame('OPD-RECEPTION', $servicePoint->code);
        $this->assertSame('reception', $servicePoint->type);
    }

    public function test_department_codes_are_unique_within_a_facility_but_reusable_at_another_facility(): void
    {
        $service = app(OrganizationService::class);
        $firstFacility = Facility::factory()->create();
        $secondFacility = Facility::factory()->create();

        $service->createDepartment(['name' => 'First Laboratory', 'code' => 'lab'], $firstFacility);
        $secondDepartment = $service->createDepartment(['name' => 'Second Laboratory', 'code' => 'LAB'], $secondFacility);

        $this->assertSame($secondFacility->id, $secondDepartment->facility_id);

        try {
            $service->createDepartment(['name' => 'Duplicate Laboratory', 'code' => 'lab'], $firstFacility);
            $this->fail('A duplicate department code should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('code', $exception->errors());
        }
    }

    public function test_service_point_must_belong_to_an_active_department_at_the_selected_facility(): void
    {
        $service = app(OrganizationService::class);
        $selectedFacility = Facility::factory()->create();
        $foreignFacility = Facility::factory()->create();
        $foreignDepartment = Department::factory()->create(['facility_id' => $foreignFacility->id]);

        foreach ([null, $foreignDepartment->id] as $departmentId) {
            try {
                $service->createServicePoint([
                    'department_id' => $departmentId,
                    'name' => 'Invalid Service Point',
                    'code' => 'INVALID-'.($departmentId ?? 'ORPHAN'),
                ], $selectedFacility);
                $this->fail('An orphaned or cross-facility service point should have been rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('department_id', $exception->errors());
            }
        }

        $inactiveDepartment = Department::factory()->create([
            'facility_id' => $selectedFacility->id,
            'is_active' => false,
        ]);

        try {
            $service->createServicePoint([
                'department_id' => $inactiveDepartment->id,
                'name' => 'Inactive Department Point',
                'code' => 'INACTIVE-DEPARTMENT',
            ], $selectedFacility);
            $this->fail('A service point should not be created beneath an inactive department.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('department_id', $exception->errors());
        }

        $this->assertSame(0, ServicePoint::query()->where('name', 'Invalid Service Point')->count());
        $this->assertDatabaseMissing('service_points', ['code' => 'INACTIVE-DEPARTMENT']);
    }

    public function test_system_settings_preserve_types_and_can_be_updated(): void
    {
        $service = app(OrganizationService::class);

        $service->setSetting('appointments.reminders.enabled', true, 'notifications', 'boolean');
        $service->setSetting('appointments.default_duration', 30, 'appointments', 'integer');
        $service->setSetting('facility.services', ['outpatient', 'laboratory'], 'facility', 'json');

        $this->assertTrue($service->setting('appointments.reminders.enabled'));
        $this->assertSame(30, $service->setting('appointments.default_duration'));
        $this->assertSame(['outpatient', 'laboratory'], $service->setting('facility.services'));
        $this->assertSame(3, SystemSetting::count());

        $service->setSetting('appointments.default_duration', 45, 'appointments', 'integer');
        $this->assertSame(45, $service->setting('appointments.default_duration'));
    }

    public function test_setting_value_updates_preserve_metadata_and_validate_the_existing_type(): void
    {
        $service = app(OrganizationService::class);
        $setting = $service->setSetting(
            'facility.operating_days',
            ['monday', 'tuesday'],
            'facility',
            'json',
            'Days on which the facility operates.',
            true,
        );

        $service->updateSettingValue($setting, '["wednesday","thursday"]');
        $setting->refresh();

        $this->assertSame(['wednesday', 'thursday'], $setting->typedValue());
        $this->assertSame('facility', $setting->group);
        $this->assertSame('json', $setting->type);
        $this->assertSame('Days on which the facility operates.', $setting->description);
        $this->assertTrue($setting->is_public);

        try {
            $service->updateSettingValue($setting, '{invalid json');
            $this->fail('Malformed JSON should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('value', $exception->errors());
        }

        $integerSetting = $service->setSetting('appointments.slot_limit', 10, 'appointments', 'integer');

        try {
            $service->updateSettingValue($integerSetting, 'ten');
            $this->fail('A non-integer setting value should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('value', $exception->errors());
        }
    }

    public function test_active_facilities_and_explicit_facility_lookup_do_not_mix_organization_contexts(): void
    {
        $service = app(OrganizationService::class);
        $first = Facility::factory()->create(['name' => 'CityCare Active East', 'is_active' => true]);
        $second = Facility::factory()->create(['name' => 'CityCare Active West', 'is_active' => true]);
        Facility::factory()->create(['name' => 'CityCare Closed', 'is_active' => false]);

        $this->assertTrue($service->facility($second->id)?->is($second));
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $service->activeFacilities()->modelKeys(),
        );
    }

    public function test_organization_models_are_persisted_with_active_defaults(): void
    {
        $service = app(OrganizationService::class);
        $service->saveFacility(['name' => 'CityCare Medical Center']);
        $department = $service->createDepartment(['name' => 'Laboratory', 'code' => 'LAB']);
        $point = $service->createServicePoint([
            'department_id' => $department->id,
            'name' => 'Sample Collection',
            'code' => 'LAB-SAMPLE',
        ]);

        $this->assertTrue(Facility::firstOrFail()->is_active);
        $this->assertTrue(Department::firstOrFail()->is_active);
        $this->assertTrue(ServicePoint::firstOrFail()->is_active);
        $this->assertSame('service', $point->type);
    }
}
