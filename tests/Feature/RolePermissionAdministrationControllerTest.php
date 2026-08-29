<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Facility;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionAdministrationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_super_administrator_can_open_and_update_role_permissions(): void
    {
        $superAdministrator = $this->staffWithRole('super-admin');
        $role = Role::query()->where('slug', 'records')->firstOrFail();
        $dashboard = Permission::query()->where('slug', 'dashboard.view')->firstOrFail();
        $patients = Permission::query()->where('slug', 'patients.view')->firstOrFail();

        $this->actingAs($superAdministrator)
            ->get(route('access.roles.index'))
            ->assertOk()
            ->assertSee('Role permissions')
            ->assertSee($role->name);

        $this->actingAs($superAdministrator)
            ->put(route('access.roles.update', $role), [
                'role_id' => $role->id,
                'permissions' => [$dashboard->id, $patients->id],
            ])
            ->assertRedirect(route('access.roles.index').'#role-'.$role->id);

        $this->assertEqualsCanonicalizing(
            [$dashboard->id, $patients->id],
            $role->permissions()->pluck('permissions.id')->all(),
        );
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'access.role-permissions.updated',
            'auditable_id' => $role->id,
        ]);
    }

    public function test_operational_administrator_cannot_open_access_control(): void
    {
        $administrator = $this->staffWithRole('administrator');

        $this->actingAs($administrator)
            ->get(route('access.roles.index'))
            ->assertForbidden();
    }

    public function test_protected_role_and_route_role_mismatch_are_rejected(): void
    {
        $superAdministrator = $this->staffWithRole('super-admin');
        $superRole = Role::query()->where('slug', 'super-admin')->firstOrFail();
        $recordsRole = Role::query()->where('slug', 'records')->firstOrFail();
        $dashboard = Permission::query()->where('slug', 'dashboard.view')->firstOrFail();

        $this->actingAs($superAdministrator)
            ->from(route('access.roles.index'))
            ->put(route('access.roles.update', $superRole), [
                'role_id' => $superRole->id,
                'permissions' => [$dashboard->id],
            ])
            ->assertSessionHasErrors('permissions');

        $this->actingAs($superAdministrator)
            ->from(route('access.roles.index'))
            ->put(route('access.roles.update', $recordsRole), [
                'role_id' => $superRole->id,
                'permissions' => [$dashboard->id],
            ])
            ->assertSessionHasErrors('role_id');
    }

    private function staffWithRole(string $roleSlug): User
    {
        $facility = Facility::query()->firstOrFail();
        $department = Department::query()->where('facility_id', $facility->id)->firstOrFail();
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->sync([Role::query()->where('slug', $roleSlug)->valueOrFail('id')]);
        StaffProfile::create([
            'user_id' => $staff->id,
            'department_id' => $department->id,
            'employee_number' => 'ACCESS-'.$staff->id,
            'employment_status' => 'active',
        ]);

        return $staff;
    }
}
