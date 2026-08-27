<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RolePermissionAdministrationService
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function assertSuperAdministrator(User $actor): void
    {
        abort_unless(
            $actor->isStaff()
                && $actor->isActive()
                && $actor->hasRole('super-admin')
                && $actor->hasPermissionTo('access.manage'),
            403,
            'Only a Super Administrator can manage role permissions.',
        );
    }

    /**
     * @return Collection<int, Role>
     */
    public function roles(User $actor): Collection
    {
        $this->assertSuperAdministrator($actor);

        return Role::query()
            ->with(['permissions' => fn ($query) => $query->orderBy('group')->orderBy('name')])
            ->withCount('users')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<string, Collection<int, Permission>>
     */
    public function permissionsByGroup(User $actor): Collection
    {
        $this->assertSuperAdministrator($actor);

        return Permission::query()
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->groupBy('group');
    }

    public function sync(User $actor, Role $role, array $permissionIds): Role
    {
        $this->assertSuperAdministrator($actor);

        if (in_array($role->slug, ['super-admin', 'patient'], true)) {
            throw ValidationException::withMessages([
                'permissions' => $role->slug === 'super-admin'
                    ? 'The Super Administrator permission set is protected.'
                    : 'The patient permission set is protected from internal-access changes.',
            ]);
        }

        $ids = collect($permissionIds)->map(fn ($id) => (int) $id)->unique()->values();
        $permissions = Permission::query()->whereKey($ids)->get();

        if ($permissions->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'permissions' => 'One or more selected permissions are invalid.',
            ]);
        }

        return DB::transaction(function () use ($actor, $role, $permissions): Role {
            $before = $role->permissions()->orderBy('slug')->pluck('slug')->all();
            $role->permissions()->sync($permissions->modelKeys());
            $role->load('permissions');
            $actor->loadMissing('staffProfile.department');

            $this->audit->record(
                $actor,
                'access.role-permissions.updated',
                'permissions-updated',
                Role::class,
                $role->id,
                $actor->staffProfile?->department?->facility_id,
                ['permissions' => $before],
                ['permissions' => $role->permissions->pluck('slug')->sort()->values()->all()],
            );

            return $role;
        });
    }
}
