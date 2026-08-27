<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Facility;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_facility_administrator_only_sees_events_from_their_assigned_facility(): void
    {
        $facilityA = Facility::factory()->create(['name' => 'CityCare East']);
        $facilityB = Facility::factory()->create(['name' => 'CityCare West']);
        $administrator = $this->staffWithRoleAtFacility('administrator', $facilityA);
        $audit = app(AuditLogService::class);

        $audit->record(
            $administrator,
            'patient.east_record',
            'updated',
            'App\\Models\\Patient',
            101,
            $facilityA->id,
            ['private_note' => 'before-secret-value'],
            ['private_note' => 'after-secret-value'],
            ['reference' => 'context-secret-value'],
            '10.20.30.40',
            'Sensitive Browser Signature',
        );
        $audit->record($administrator, 'patient.west_record', 'updated', 'App\\Models\\Patient', 202, $facilityB->id);

        $this->actingAs($administrator)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee('patient.east_record')
            ->assertSee('CityCare East')
            ->assertDontSee('patient.west_record')
            ->assertDontSee('CityCare West')
            ->assertDontSee('before-secret-value')
            ->assertDontSee('after-secret-value')
            ->assertDontSee('context-secret-value')
            ->assertDontSee('10.20.30.40')
            ->assertDontSee('Sensitive Browser Signature');
    }

    public function test_facility_administrator_cannot_select_another_facility(): void
    {
        $facilityA = Facility::factory()->create();
        $facilityB = Facility::factory()->create();
        $administrator = $this->staffWithRoleAtFacility('administrator', $facilityA);

        $this->actingAs($administrator)
            ->get(route('audit.index', ['facility_id' => $facilityB->id]))
            ->assertForbidden();
    }

    public function test_super_administrator_can_review_and_filter_active_facilities(): void
    {
        $facilityA = Facility::factory()->create(['name' => 'CityCare Central']);
        $facilityB = Facility::factory()->create(['name' => 'CityCare North']);
        $superAdministrator = $this->staffWithRole('super-admin');
        $audit = app(AuditLogService::class);

        $audit->record($superAdministrator, 'facility.central_event', 'created', 'App\\Models\\Facility', $facilityA->id, $facilityA->id);
        $audit->record($superAdministrator, 'facility.north_event', 'created', 'App\\Models\\Facility', $facilityB->id, $facilityB->id);

        $this->actingAs($superAdministrator)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee('Organization-wide access')
            ->assertSee('facility.central_event')
            ->assertSee('facility.north_event');

        $this->actingAs($superAdministrator)
            ->get(route('audit.index', ['facility_id' => $facilityA->id]))
            ->assertOk()
            ->assertSee('facility.central_event')
            ->assertDontSee('facility.north_event');
    }

    public function test_staff_without_audit_permission_is_denied(): void
    {
        $doctor = $this->staffWithRole('doctor');

        $this->actingAs($doctor)
            ->get(route('audit.index'))
            ->assertForbidden();
    }

    private function staffWithRoleAtFacility(string $roleSlug, Facility $facility): User
    {
        $staff = $this->staffWithRole($roleSlug);
        $department = Department::factory()->create(['facility_id' => $facility->id]);

        StaffProfile::create([
            'user_id' => $staff->id,
            'department_id' => $department->id,
            'employee_number' => 'AUDIT-'.$staff->id,
            'employment_status' => 'active',
        ]);

        $staff->unsetRelation('staffProfile');

        return $staff;
    }

    private function staffWithRole(string $roleSlug): User
    {
        $staff = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => true,
        ]);

        $staff->roles()->attach(Role::query()->where('slug', $roleSlug)->firstOrFail());

        return $staff;
    }
}
