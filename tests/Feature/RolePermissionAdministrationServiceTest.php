<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\RolePermissionAdministrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RolePermissionAdministrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private RolePermissionAdministrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->service = app(RolePermissionAdministrationService::class);
    }

    public function test_only_super_administrator_with_access_permission_can_open_role_administration(): void
    {
        $administrator = $this->staffWithRole('administrator');

        $this->expectException(HttpException::class);
        $this->service->roles($administrator);
    }

    public function test_access_permission_alone_does_not_grant_global_role_administration(): void
    {
        $role = Role::create(['name' => 'Security Operator', 'slug' => 'security-operator', 'is_system' => false]);
        $role->permissions()->sync([
            Permission::where('slug', 'access.manage')->valueOrFail('id'),
            Permission::where('slug', 'staff.manage')->valueOrFail('id'),
        ]);
        $operator = $this->staffWithRole($role->slug);

        $this->expectException(HttpException::class);
        $this->service->permissionsByGroup($operator);
    }

    public function test_super_administrator_can_sync_role_permissions_with_audit_before_and_after(): void
    {
        $superAdministrator = $this->staffWithRole('super-admin');
        $role = Role::where('slug', 'records')->firstOrFail();
        $dashboard = Permission::where('slug', 'dashboard.view')->firstOrFail();
        $patients = Permission::where('slug', 'patients.view')->firstOrFail();

        $updated = $this->service->sync($superAdministrator, $role, [$dashboard->id, $patients->id]);

        $this->assertEqualsCanonicalizing(
            ['dashboard.view', 'patients.view'],
            $updated->permissions->pluck('slug')->all(),
        );
        $event = AuditEvent::where('event_type', 'access.role-permissions.updated')->firstOrFail();
        $this->assertSame($superAdministrator->id, $event->actor_id);
        $this->assertSame($role->id, $event->auditable_id);
        $this->assertArrayHasKey('permissions', $event->before_values);
        $this->assertSame(['dashboard.view', 'patients.view'], $event->after_values['permissions']);
    }

    public function test_super_administrator_and_patient_permission_sets_are_protected(): void
    {
        $superAdministrator = $this->staffWithRole('super-admin');
        $permission = Permission::where('slug', 'dashboard.view')->firstOrFail();

        foreach (['super-admin', 'patient'] as $slug) {
            try {
                $this->service->sync(
                    $superAdministrator,
                    Role::where('slug', $slug)->firstOrFail(),
                    [$permission->id],
                );
                $this->fail("Expected {$slug} permission set to be protected.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('permissions', $exception->errors());
            }
        }
    }

    public function test_invalid_permission_identifiers_are_rejected_atomically(): void
    {
        $superAdministrator = $this->staffWithRole('super-admin');
        $role = Role::where('slug', 'records')->firstOrFail();
        $before = $role->permissions()->pluck('permissions.id')->all();

        try {
            $this->service->sync($superAdministrator, $role, [999999]);
            $this->fail('Expected invalid permission identifier to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('permissions', $exception->errors());
        }

        $this->assertEqualsCanonicalizing($before, $role->permissions()->pluck('permissions.id')->all());
        $this->assertDatabaseCount('audit_events', 0);
    }

    private function staffWithRole(string $roleSlug): User
    {
        $facility = Facility::query()->firstOrFail();
        $department = Department::query()->where('facility_id', $facility->id)->firstOrFail();
        $user = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $user->staffProfile()->create([
            'department_id' => $department->id,
            'employee_number' => 'EMP-'.$user->id,
            'employment_status' => 'active',
        ]);
        $user->roles()->attach(Role::where('slug', $roleSlug)->valueOrFail('id'));

        return $user;
    }
}
