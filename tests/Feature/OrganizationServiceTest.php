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
            'code' => 'OPD',
            'description' => 'General outpatient services.',
        ]);

        $servicePoint = $service->createServicePoint([
            'department_id' => $department->id,
            'name' => 'Reception Desk',
            'code' => 'OPD-RECEPTION',
            'type' => 'reception',
            'location' => 'Ground Floor',
        ]);

        $this->assertSame($facility->id, $department->facility_id);
        $this->assertTrue($department->servicePoints()->whereKey($servicePoint->id)->exists());
        $this->assertSame($department->id, $servicePoint->department_id);
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
