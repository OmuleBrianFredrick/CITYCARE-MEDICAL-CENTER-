<?php

namespace App\Services;

use App\Models\Department;
use App\Models\EmployeeInvitation;
use App\Models\Facility;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffAdministrationService
{
    public function __construct(
        private readonly FacilityAccessService $facilities,
        private readonly AuditLogService $audit,
        private readonly EmployeeInvitationService $invitations,
    ) {}

    public function facilityFor(User $actor, ?int $requestedFacilityId = null): Facility
    {
        $this->assertStaffManager($actor);

        if ($actor->hasRole('super-admin')) {
            $query = Facility::query()->where('is_active', true);

            return $requestedFacilityId
                ? $query->findOrFail($requestedFacilityId)
                : $query->orderBy('name')->orderBy('id')->firstOrFail();
        }

        $facility = $this->facilities->currentFacility($actor);

        if ($requestedFacilityId !== null && $facility->id !== $requestedFacilityId) {
            abort(403, 'You may only administer staff in your assigned facility.');
        }

        return $facility;
    }

    public function staffQuery(User $actor, Facility $facility): Builder
    {
        $this->assertFacilityAllowed($actor, $facility);

        return User::query()
            ->where('user_type', 'staff')
            ->whereHas('staffProfile.department', fn (Builder $query) => $query->where('facility_id', $facility->id))
            ->with([
                'roles' => fn ($query) => $query->orderBy('name'),
                'staffProfile.department.facility',
                'staffProfile.servicePoint',
            ]);
    }

    /**
     * Return only facilities the actor is allowed to administer.
     *
     * @return Collection<int, Facility>
     */
    public function availableFacilities(User $actor): Collection
    {
        $this->assertStaffManager($actor);

        if ($actor->hasRole('super-admin')) {
            return Facility::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->orderBy('id')
                ->get();
        }

        return collect([$this->facilities->currentFacility($actor)]);
    }

    /**
     * Resolve and authorize the facility context for an existing staff account.
     */
    public function facilityForTarget(User $actor, User $staff): Facility
    {
        $facilityId = $this->assertCanManageTarget($actor, $staff);

        return Facility::query()->where('is_active', true)->findOrFail($facilityId);
    }

    public function create(User $actor, array $attributes): User
    {
        $this->assertStaffManager($actor);
        $department = $this->departmentForWrite($actor, (int) $attributes['department_id']);
        $this->assertSubmittedFacilityMatches($attributes, $department->facility_id);
        $servicePoint = $this->servicePointForDepartment($attributes['service_point_id'] ?? null, $department);
        $roles = $this->rolesForAssignment($actor, $attributes['roles']);

        return DB::transaction(function () use ($actor, $attributes, $department, $servicePoint, $roles): User {
            $user = User::query()->create([
                'name' => trim($attributes['name']),
                'email' => strtolower(trim($attributes['email'])),
                'email_verified_at' => now(),
                'password' => $attributes['password'],
                'user_type' => 'staff',
                'is_active' => (bool) ($attributes['is_active'] ?? true),
            ]);

            $user->staffProfile()->create([
                'department_id' => $department->id,
                'service_point_id' => $servicePoint?->id,
                'employee_number' => $attributes['employee_number'] ?? null,
                'job_title' => $attributes['job_title'] ?? null,
                'employment_status' => $user->isActive() ? 'active' : 'inactive',
                'phone' => $attributes['phone'] ?? null,
                'joined_at' => $attributes['joined_at'] ?? null,
            ]);
            $user->roles()->sync($roles->modelKeys());
            $user->load('roles', 'staffProfile.department.facility', 'staffProfile.servicePoint');

            $this->audit->record(
                $actor,
                'staff.account.created',
                'created',
                User::class,
                $user->id,
                $department->facility_id,
                null,
                $this->snapshot($user),
            );

            return $user;
        });
    }

    /**
     * @return array{0: User, 1: EmployeeInvitation, 2: string}
     */
    public function invite(User $actor, array $attributes): array
    {
        $this->assertStaffManager($actor);
        $department = $this->departmentForWrite($actor, (int) $attributes['department_id']);
        $this->assertSubmittedFacilityMatches($attributes, $department->facility_id);
        $servicePoint = $this->servicePointForDepartment($attributes['service_point_id'] ?? null, $department);
        $roles = $this->rolesForAssignment($actor, $attributes['roles']);

        return DB::transaction(function () use ($actor, $attributes, $department, $servicePoint, $roles): array {
            [$invitation, $plainToken] = $this->invitations->create(
                $actor,
                trim($attributes['name']),
                strtolower(trim($attributes['email'])),
                $roles->first()->slug,
            );
            $staff = $invitation->user;
            $staff->roles()->sync($roles->modelKeys());
            $staff->staffProfile()->update([
                'department_id' => $department->id,
                'service_point_id' => $servicePoint?->id,
                'employee_number' => $attributes['employee_number'] ?? null,
                'job_title' => $attributes['job_title'] ?? null,
                'employment_status' => 'pending',
                'phone' => $attributes['phone'] ?? null,
                'joined_at' => $attributes['joined_at'] ?? null,
            ]);
            $staff->load('roles', 'staffProfile.department.facility', 'staffProfile.servicePoint');

            $this->audit->record(
                $actor,
                'staff.account.invited',
                'invited',
                User::class,
                $staff->id,
                $department->facility_id,
                null,
                $this->snapshot($staff),
                ['invitation_id' => $invitation->id],
            );

            return [$staff, $invitation->fresh(), $plainToken];
        });
    }

    public function update(User $actor, User $staff, array $attributes): User
    {
        $facilityId = $this->assertCanManageTarget($actor, $staff);
        $department = $this->departmentForWrite($actor, (int) $attributes['department_id']);

        if ($department->facility_id !== $facilityId) {
            abort(403, 'Staff cannot be moved outside the facility you administer.');
        }

        $this->assertSubmittedFacilityMatches($attributes, $department->facility_id);
        $servicePoint = $this->servicePointForDepartment($attributes['service_point_id'] ?? null, $department);

        return DB::transaction(function () use ($actor, $staff, $attributes, $department, $servicePoint, $facilityId): User {
            $before = $this->snapshot($staff);
            $userAttributes = [
                'name' => trim($attributes['name']),
                'email' => strtolower(trim($attributes['email'])),
            ];

            $staff->update($userAttributes);
            $staff->staffProfile()->update([
                'department_id' => $department->id,
                'service_point_id' => $servicePoint?->id,
                'employee_number' => $attributes['employee_number'] ?? null,
                'job_title' => $attributes['job_title'] ?? null,
                'phone' => $attributes['phone'] ?? null,
                'joined_at' => $attributes['joined_at'] ?? null,
            ]);
            $staff->load('roles', 'staffProfile.department.facility', 'staffProfile.servicePoint');

            $this->audit->record(
                $actor,
                'staff.account.updated',
                'updated',
                User::class,
                $staff->id,
                $facilityId,
                $before,
                $this->snapshot($staff),
            );

            return $staff;
        });
    }

    public function syncRoles(User $actor, User $staff, array $roleIds): User
    {
        $facilityId = $this->assertCanManageTarget($actor, $staff);
        $roles = $this->rolesForAssignment($actor, $roleIds);

        if ($staff->hasRole('super-admin') && ! $roles->contains('slug', 'super-admin')) {
            throw ValidationException::withMessages([
                'roles' => 'The Super Administrator role cannot be removed through staff administration.',
            ]);
        }

        return DB::transaction(function () use ($actor, $staff, $roles, $facilityId): User {
            $before = $this->snapshot($staff);
            $staff->roles()->sync($roles->modelKeys());
            $staff->load('roles', 'staffProfile.department.facility', 'staffProfile.servicePoint');

            $this->audit->record(
                $actor,
                'staff.roles.updated',
                'roles-updated',
                User::class,
                $staff->id,
                $facilityId,
                $before,
                $this->snapshot($staff),
            );

            return $staff;
        });
    }

    public function deactivate(User $actor, User $staff): User
    {
        $facilityId = $this->assertCanManageTarget($actor, $staff);

        if ($actor->is($staff)) {
            throw ValidationException::withMessages([
                'staff' => 'You cannot deactivate your own account.',
            ]);
        }

        if ($staff->hasRole('super-admin')) {
            $activeSuperAdministrators = User::query()
                ->where('user_type', 'staff')
                ->where('is_active', true)
                ->whereHas('roles', fn (Builder $query) => $query->where('slug', 'super-admin'))
                ->count();

            if ($activeSuperAdministrators <= 1) {
                throw ValidationException::withMessages([
                    'staff' => 'The last active Super Administrator cannot be deactivated.',
                ]);
            }
        }

        return $this->setActiveState($actor, $staff, false, $facilityId);
    }

    public function reactivate(User $actor, User $staff): User
    {
        $facilityId = $this->assertCanManageTarget($actor, $staff);

        if (blank($staff->password)) {
            throw ValidationException::withMessages([
                'staff' => 'This account has not completed setup. Issue a new setup link instead.',
            ]);
        }

        return $this->setActiveState($actor, $staff, true, $facilityId);
    }

    public function latestInvitation(User $actor, User $staff): ?EmployeeInvitation
    {
        $this->assertCanManageTarget($actor, $staff);

        return $staff->invitationsReceived()->latest('id')->first();
    }

    /**
     * @return array{0: EmployeeInvitation, 1: string}
     */
    public function reissueInvitation(User $actor, User $staff): array
    {
        $this->assertCanManageTarget($actor, $staff);

        if ($staff->isActive()) {
            throw ValidationException::withMessages([
                'invitation' => 'Setup links can only be issued for inactive staff accounts.',
            ]);
        }

        $liveInvitation = $staff->invitationsReceived()
            ->where('status', EmployeeInvitation::STATUS_PENDING)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($liveInvitation) {
            $this->invitations->revoke($actor, $liveInvitation);
        }

        $staff->loadMissing('roles.permissions');
        $roles = $this->rolesForAssignment($actor, $staff->roles->modelKeys());
        [$invitation, $plainToken] = $this->invitations->create(
            $actor,
            $staff->name,
            $staff->email,
            $roles->first()->slug,
        );

        return [$invitation, $plainToken];
    }

    public function revokeInvitation(User $actor, User $staff, EmployeeInvitation $invitation): EmployeeInvitation
    {
        $this->assertCanManageTarget($actor, $staff);

        if ($invitation->user_id !== $staff->id) {
            abort(404);
        }

        return $this->invitations->revoke($actor, $invitation);
    }

    public function assignableRoles(User $actor): Collection
    {
        $this->assertStaffManager($actor);

        return Role::query()
            ->where('slug', '!=', 'patient')
            ->when(
                ! $actor->hasRole('super-admin'),
                fn (Builder $query) => $query
                    ->where('slug', '!=', 'super-admin')
                    ->whereDoesntHave('permissions', fn (Builder $permissions) => $permissions->where('slug', 'access.manage')),
            )
            ->orderBy('name')
            ->get();
    }

    public function departments(Facility $facility)
    {
        return Department::query()
            ->where('facility_id', $facility->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function servicePoints(Facility $facility)
    {
        return ServicePoint::query()
            ->with('department:id,name')
            ->where('is_active', true)
            ->whereHas('department', fn (Builder $query) => $query->where('facility_id', $facility->id)->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function assertStaffManager(User $actor): void
    {
        abort_unless(
            $actor->isStaff() && $actor->isActive() && $actor->hasPermissionTo('staff.manage'),
            403,
            'You are not authorized to manage staff accounts.',
        );
    }

    private function assertFacilityAllowed(User $actor, Facility $facility): void
    {
        $this->assertStaffManager($actor);
        $this->facilities->assertFacilityAccessible($actor, $facility->id);
    }

    private function assertCanManageTarget(User $actor, User $staff): int
    {
        $this->assertStaffManager($actor);

        if (! $staff->isStaff()) {
            abort(404);
        }

        $staff->loadMissing('roles.permissions', 'staffProfile.department.facility', 'staffProfile.servicePoint');
        $facilityId = $staff->staffProfile?->department?->facility_id;

        if ($facilityId === null) {
            abort(403, 'This staff account has no facility assignment and cannot be managed here.');
        }

        $this->facilities->assertFacilityAccessible($actor, (int) $facilityId);

        if ($staff->hasRole('super-admin') && ! $actor->hasRole('super-admin')) {
            abort(403, 'Only a Super Administrator can manage another Super Administrator.');
        }

        $targetHasAccessAuthority = $staff->roles->contains(
            fn (Role $role): bool => $role->permissions->contains('slug', 'access.manage'),
        );

        if ($targetHasAccessAuthority && ! $actor->hasRole('super-admin')) {
            abort(403, 'Only a Super Administrator can manage staff with access-administration authority.');
        }

        return (int) $facilityId;
    }

    private function departmentForWrite(User $actor, int $departmentId): Department
    {
        $department = Department::query()
            ->with('facility')
            ->where('is_active', true)
            ->findOrFail($departmentId);

        abort_unless($department->facility?->is_active, 422, 'Select a department in an active facility.');
        $this->facilities->assertFacilityAccessible($actor, $department->facility_id);

        return $department;
    }

    private function servicePointForDepartment(mixed $servicePointId, Department $department): ?ServicePoint
    {
        if (blank($servicePointId)) {
            return null;
        }

        $servicePoint = ServicePoint::query()->where('is_active', true)->findOrFail((int) $servicePointId);

        if ($servicePoint->department_id !== $department->id) {
            throw ValidationException::withMessages([
                'service_point_id' => 'The selected service point must belong to the selected department.',
            ]);
        }

        return $servicePoint;
    }

    private function rolesForAssignment(User $actor, array $roleIds)
    {
        $ids = collect($roleIds)->map(fn ($id) => (int) $id)->unique()->values();
        $roles = Role::query()->with('permissions')->whereKey($ids)->get();

        if ($ids->isEmpty() || $roles->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'roles' => 'Select at least one valid staff role.',
            ]);
        }

        if ($roles->contains('slug', 'patient')) {
            throw ValidationException::withMessages([
                'roles' => 'The patient role cannot be assigned to a staff account.',
            ]);
        }

        $includesPrivilegedRole = $roles->contains('slug', 'super-admin')
            || $roles->contains(fn (Role $role) => $role->permissions->contains('slug', 'access.manage'));

        if ($includesPrivilegedRole && ! $actor->hasRole('super-admin')) {
            throw ValidationException::withMessages([
                'roles' => 'Only a Super Administrator can assign access-administration authority.',
            ]);
        }

        return $roles;
    }

    private function assertSubmittedFacilityMatches(array $attributes, int $facilityId): void
    {
        if (isset($attributes['facility_id']) && (int) $attributes['facility_id'] !== $facilityId) {
            throw ValidationException::withMessages([
                'department_id' => 'The selected department does not belong to the selected facility.',
            ]);
        }
    }

    private function setActiveState(User $actor, User $staff, bool $active, int $facilityId): User
    {
        return DB::transaction(function () use ($actor, $staff, $active, $facilityId): User {
            $before = $this->snapshot($staff);
            $staff->update(['is_active' => $active]);
            $staff->staffProfile()->update(['employment_status' => $active ? 'active' : 'inactive']);
            $staff->load('roles', 'staffProfile.department.facility', 'staffProfile.servicePoint');

            $this->audit->record(
                $actor,
                $active ? 'staff.account.reactivated' : 'staff.account.deactivated',
                $active ? 'reactivated' : 'deactivated',
                User::class,
                $staff->id,
                $facilityId,
                $before,
                $this->snapshot($staff),
            );

            return $staff;
        });
    }

    private function snapshot(User $staff): array
    {
        $staff->loadMissing('roles', 'staffProfile.department', 'staffProfile.servicePoint');

        return [
            'name' => $staff->name,
            'email' => $staff->email,
            'is_active' => $staff->isActive(),
            'department_id' => $staff->staffProfile?->department_id,
            'service_point_id' => $staff->staffProfile?->service_point_id,
            'employee_number' => $staff->staffProfile?->employee_number,
            'job_title' => $staff->staffProfile?->job_title,
            'employment_status' => $staff->staffProfile?->employment_status,
            'phone' => $staff->staffProfile?->phone,
            'joined_at' => $staff->staffProfile?->joined_at?->toDateString(),
            'roles' => $staff->roles->pluck('slug')->sort()->values()->all(),
        ];
    }
}
