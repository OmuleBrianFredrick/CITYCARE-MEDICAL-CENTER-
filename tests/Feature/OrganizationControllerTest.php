<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Facility;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_administrator_sees_only_their_assigned_facility_workspace(): void
    {
        $assignedFacility = Facility::query()->firstOrFail();
        $foreignFacility = Facility::factory()->create(['name' => 'CityCare Foreign Campus']);
        Department::factory()->create([
            'facility_id' => $foreignFacility->id,
            'name' => 'Foreign-only Department',
            'code' => 'FOREIGN',
        ]);
        $administrator = $this->staffAt($assignedFacility, 'administrator');

        $this->actingAs($administrator)
            ->get(route('organization.index'))
            ->assertOk()
            ->assertSee('CityCare configuration')
            ->assertSee($assignedFacility->name)
            ->assertSee('Only a super administrator can change organization-wide settings.')
            ->assertDontSee($foreignFacility->name)
            ->assertDontSee('Foreign-only Department');
    }

    public function test_receptionist_cannot_access_organization_workspace(): void
    {
        $receptionist = $this->staffAt(Facility::query()->firstOrFail(), 'receptionist');

        $this->actingAs($receptionist)
            ->get(route('organization.index'))
            ->assertForbidden();
    }

    public function test_administrator_can_configure_their_facility_and_create_structure(): void
    {
        $facility = Facility::query()->firstOrFail();
        $administrator = $this->staffAt($facility, 'administrator');

        $this->actingAs($administrator)
            ->put(route('organization.facility.update'), [
                'facility_id' => $facility->id,
                'name' => 'CityCare Central Medical Center',
                'legal_name' => 'CityCare Medical Center Limited',
                'country' => 'Uganda',
                'timezone' => 'Africa/Kampala',
                'currency' => 'ugx',
                'primary_color' => '#2563EB',
                'secondary_color' => '#0F172A',
                'accent_color' => '#F4C430',
            ])
            ->assertRedirect(route('organization.index', ['facility_id' => $facility->id]));

        $this->assertDatabaseHas('facilities', [
            'id' => $facility->id,
            'name' => 'CityCare Central Medical Center',
            'currency' => 'UGX',
        ]);

        $this->actingAs($administrator)
            ->post(route('organization.departments.store'), [
                'facility_id' => $facility->id,
                'name' => 'Radiology Department',
                'code' => 'rad',
                'description' => 'Diagnostic imaging services.',
            ])
            ->assertRedirect(route('organization.index', ['facility_id' => $facility->id]));

        $department = Department::query()->where('facility_id', $facility->id)->where('code', 'RAD')->firstOrFail();

        $this->actingAs($administrator)
            ->post(route('organization.service-points.store'), [
                'facility_id' => $facility->id,
                'department_id' => $department->id,
                'name' => 'General Radiology',
                'code' => 'rad-general',
                'type' => 'Radiology',
                'location' => 'Ground Floor',
            ])
            ->assertRedirect(route('organization.index', ['facility_id' => $facility->id]));

        $this->assertDatabaseHas('service_points', [
            'department_id' => $department->id,
            'code' => 'RAD-GENERAL',
            'type' => 'radiology',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $administrator->id,
            'facility_id' => $facility->id,
            'event_type' => 'organization.facility',
            'action' => 'updated',
            'auditable_id' => $facility->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $administrator->id,
            'facility_id' => $facility->id,
            'event_type' => 'organization.department',
            'action' => 'created',
            'auditable_id' => $department->id,
        ]);
    }

    public function test_administrator_cannot_select_or_mutate_another_facility(): void
    {
        $assignedFacility = Facility::query()->firstOrFail();
        $foreignFacility = Facility::factory()->create(['name' => 'CityCare North']);
        $foreignDepartment = Department::factory()->create([
            'facility_id' => $foreignFacility->id,
            'code' => 'NORTH',
        ]);
        $administrator = $this->staffAt($assignedFacility, 'administrator');

        $this->actingAs($administrator)
            ->get(route('organization.index', ['facility_id' => $foreignFacility->id]))
            ->assertForbidden();

        $this->actingAs($administrator)
            ->put(route('organization.facility.update'), [
                ...$this->facilityPayload($foreignFacility),
                'name' => 'Unauthorized Facility Change',
            ])
            ->assertForbidden();

        $this->actingAs($administrator)
            ->post(route('organization.departments.store'), [
                'facility_id' => $foreignFacility->id,
                'name' => 'Unauthorized Department',
                'code' => 'UNAUTHORIZED',
            ])
            ->assertForbidden();

        $this->actingAs($administrator)
            ->post(route('organization.service-points.store'), [
                'facility_id' => $assignedFacility->id,
                'department_id' => $foreignDepartment->id,
                'name' => 'Cross-facility Point',
                'code' => 'CROSS-FACILITY',
                'type' => 'service',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('department_id');

        $this->assertDatabaseMissing('facilities', ['name' => 'Unauthorized Facility Change']);
        $this->assertDatabaseMissing('departments', ['code' => 'UNAUTHORIZED']);
        $this->assertDatabaseMissing('service_points', ['code' => 'CROSS-FACILITY']);
    }

    public function test_super_administrator_can_switch_to_and_manage_another_active_facility(): void
    {
        $facility = Facility::factory()->create(['name' => 'CityCare Eastern Campus']);
        Department::factory()->create([
            'facility_id' => $facility->id,
            'name' => 'Eastern Campus Diagnostics',
            'code' => 'EAST-DIAG',
        ]);
        $superAdministrator = $this->staffWithRole('super-admin');

        $this->actingAs($superAdministrator)
            ->get(route('organization.index', ['facility_id' => $facility->id]))
            ->assertOk()
            ->assertSee('CityCare Eastern Campus')
            ->assertSee('Eastern Campus Diagnostics');

        $this->actingAs($superAdministrator)
            ->put(route('organization.facility.update'), [
                ...$this->facilityPayload($facility),
                'name' => 'CityCare Eastern Medical Center',
            ])
            ->assertRedirect(route('organization.index', ['facility_id' => $facility->id]));

        $this->assertDatabaseHas('facilities', [
            'id' => $facility->id,
            'name' => 'CityCare Eastern Medical Center',
        ]);
    }

    public function test_only_super_administrator_can_update_global_setting_values_and_metadata_is_preserved(): void
    {
        $facility = Facility::query()->firstOrFail();
        $administrator = $this->staffAt($facility, 'administrator');
        $setting = SystemSetting::query()->where('key', 'appointments.reminders.enabled')->firstOrFail();
        $setting->update([
            'description' => 'Controls appointment reminder delivery.',
            'is_public' => true,
        ]);

        $this->actingAs($administrator)
            ->put(route('organization.settings.update', ['key' => $setting->key]), ['value' => '0'])
            ->assertForbidden();

        $this->assertTrue($setting->fresh()->typedValue());

        $superAdministrator = $this->staffWithRole('super-admin');

        $this->actingAs($superAdministrator)
            ->put(route('organization.settings.update', ['key' => $setting->key]), ['value' => 'false'])
            ->assertRedirect();

        $setting->refresh();
        $this->assertFalse($setting->typedValue());
        $this->assertSame('boolean', $setting->type);
        $this->assertSame('notifications', $setting->group);
        $this->assertSame('Controls appointment reminder delivery.', $setting->description);
        $this->assertTrue($setting->is_public);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $superAdministrator->id,
            'event_type' => 'organization.setting',
            'action' => 'updated',
            'auditable_id' => $setting->id,
        ]);
    }

    public function test_organization_management_requires_manage_permission(): void
    {
        $facility = Facility::query()->firstOrFail();
        $receptionist = $this->staffAt($facility, 'receptionist');

        $this->actingAs($receptionist)
            ->put(route('organization.facility.update'), $this->facilityPayload($facility))
            ->assertForbidden();
    }

    private function facilityPayload(Facility $facility): array
    {
        return [
            'facility_id' => $facility->id,
            'name' => $facility->name,
            'country' => $facility->country,
            'timezone' => $facility->timezone,
            'currency' => $facility->currency,
            'primary_color' => $facility->primary_color,
            'secondary_color' => $facility->secondary_color,
            'accent_color' => $facility->accent_color,
        ];
    }

    private function staffAt(Facility $facility, string $roleSlug): User
    {
        $department = Department::query()->where('facility_id', $facility->id)->first()
            ?? Department::factory()->create(['facility_id' => $facility->id]);
        $staff = $this->staffWithRole($roleSlug);

        StaffProfile::create([
            'user_id' => $staff->id,
            'department_id' => $department->id,
            'employee_number' => 'ORG-'.$staff->id,
            'employment_status' => 'active',
        ]);

        return $staff;
    }

    private function staffWithRole(string $roleSlug): User
    {
        $staff = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => true,
        ]);
        $staff->roles()->sync([Role::query()->where('slug', $roleSlug)->valueOrFail('id')]);

        return $staff;
    }
}
