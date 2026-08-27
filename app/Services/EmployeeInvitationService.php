<?php

namespace App\Services;

use App\Models\EmployeeInvitation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeInvitationService
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly FacilityAccessService $facilities,
    ) {}

    public function create(User $inviter, string $name, string $email, string $roleSlug): array
    {
        $this->assertInviter($inviter);

        $role = Role::query()->with('permissions')->where('slug', $roleSlug)->firstOrFail();

        if ($role->slug === 'patient') {
            throw ValidationException::withMessages([
                'role' => 'The patient role cannot be assigned through staff invitations.',
            ]);
        }

        $isPrivileged = $role->slug === 'super-admin'
            || $role->permissions->contains('slug', 'access.manage');

        if ($isPrivileged && ! $inviter->hasRole('super-admin')) {
            throw ValidationException::withMessages([
                'role' => 'Only a Super Administrator can invite staff with access-administration authority.',
            ]);
        }

        $email = strtolower(trim($email));
        $existingUser = User::where('email', $email)->first();

        if ($existingUser && $existingUser->isActive()) {
            throw ValidationException::withMessages([
                'email' => 'An active CityCare account already exists for this email address.',
            ]);
        }

        if ($existingUser && ! $existingUser->isStaff()) {
            throw ValidationException::withMessages([
                'email' => 'This email address belongs to a non-staff CityCare account.',
            ]);
        }

        $pending = EmployeeInvitation::query()
            ->where('email', $email)
            ->where('status', EmployeeInvitation::STATUS_PENDING)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages([
                'email' => 'A pending invitation already exists for this email address.',
            ]);
        }

        $plainToken = Str::random(64);

        $result = DB::transaction(function () use ($name, $email, $role, $inviter, $plainToken, $existingUser): array {
            $user = $existingUser;

            if (! $user) {
                $user = User::create([
                    'name' => trim($name),
                    'email' => $email,
                    'password' => null,
                    'user_type' => 'staff',
                    'is_active' => false,
                ]);
            } else {
                $user->forceFill([
                    'name' => trim($name),
                    'user_type' => 'staff',
                    'is_active' => false,
                ])->save();
            }

            $user->roles()->syncWithoutDetaching([$role->id]);

            if (! $user->staffProfile()->exists()) {
                $user->staffProfile()->create([
                    'job_title' => $role->name,
                    'employment_status' => 'pending',
                ]);
            }

            $invitation = EmployeeInvitation::create([
                'user_id' => $user->id,
                'invited_by' => $inviter->id,
                'email' => $email,
                'name' => trim($name),
                'role_slug' => $role->slug,
                'token_hash' => hash('sha256', $plainToken),
                'status' => EmployeeInvitation::STATUS_PENDING,
                'expires_at' => now()->addHours(48),
            ]);

            $user->load('staffProfile.department');

            $this->audit->record(
                $inviter,
                'staff.invitation.created',
                'created',
                EmployeeInvitation::class,
                $invitation->id,
                $user->staffProfile?->department?->facility_id,
                null,
                [
                    'user_id' => $user->id,
                    'email' => $invitation->email,
                    'role_slug' => $invitation->role_slug,
                    'status' => $invitation->status,
                    'expires_at' => $invitation->expires_at?->toDateTimeString(),
                ],
            );

            return [$invitation->load('user'), $plainToken];
        });

        return $result;
    }

    public function findPending(string $plainToken): ?EmployeeInvitation
    {
        if (strlen($plainToken) !== 64) {
            return null;
        }

        return EmployeeInvitation::query()
            ->with('user.staffProfile.department.facility')
            ->where('token_hash', hash('sha256', $plainToken))
            ->where('status', EmployeeInvitation::STATUS_PENDING)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function accept(string $plainToken, string $email, string $password): User
    {
        return DB::transaction(function () use ($plainToken, $email, $password): User {
            $invitation = EmployeeInvitation::query()
                ->with('user.staffProfile.department.facility')
                ->where('token_hash', hash('sha256', $plainToken))
                ->lockForUpdate()
                ->first();
            $normalizedEmail = strtolower(trim($email));

            if (
                ! $invitation
                || ! $invitation->isPending()
                || ! hash_equals(strtolower($invitation->email), $normalizedEmail)
            ) {
                throw ValidationException::withMessages([
                    'email' => 'This staff setup link is invalid or has expired.',
                ]);
            }

            $user = $invitation->user;
            $profile = $user?->staffProfile;
            $department = $profile?->department;
            $facility = $department?->facility;

            if (! $user?->isStaff() || $user->isActive() || ! $profile || ! $department?->is_active || ! $facility?->is_active) {
                throw ValidationException::withMessages([
                    'email' => 'This staff setup link cannot be activated. Contact a CityCare administrator.',
                ]);
            }

            $user->forceFill([
                'password' => $password,
                'email_verified_at' => $user->email_verified_at ?? now(),
                'is_active' => true,
            ])->setRememberToken(Str::random(60));
            $user->save();
            $profile->update(['employment_status' => 'active']);
            $invitation->update([
                'status' => EmployeeInvitation::STATUS_ACCEPTED,
                'accepted_at' => now(),
            ]);

            EmployeeInvitation::query()
                ->where('user_id', $user->id)
                ->whereKeyNot($invitation->id)
                ->where('status', EmployeeInvitation::STATUS_PENDING)
                ->update([
                    'status' => EmployeeInvitation::STATUS_REVOKED,
                    'revoked_at' => now(),
                ]);

            $this->audit->record(
                $user,
                'staff.invitation.accepted',
                'accepted',
                EmployeeInvitation::class,
                $invitation->id,
                $facility->id,
                ['status' => EmployeeInvitation::STATUS_PENDING, 'account_active' => false],
                ['status' => EmployeeInvitation::STATUS_ACCEPTED, 'account_active' => true],
            );

            return $user->fresh(['roles', 'staffProfile.department.facility']);
        });
    }

    public function revoke(User $actor, EmployeeInvitation $invitation): EmployeeInvitation
    {
        $this->assertInviter($actor);
        $invitation->loadMissing('user.staffProfile.department');
        $facilityId = $invitation->user?->staffProfile?->department?->facility_id;

        if ($facilityId === null) {
            abort(403, 'This invitation has no authorized facility assignment.');
        }

        $this->facilities->assertFacilityAccessible($actor, (int) $facilityId);

        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'invitation' => 'Only a live pending invitation can be revoked.',
            ]);
        }

        return DB::transaction(function () use ($actor, $invitation, $facilityId): EmployeeInvitation {
            $invitation->update([
                'status' => EmployeeInvitation::STATUS_REVOKED,
                'revoked_at' => now(),
            ]);

            $this->audit->record(
                $actor,
                'staff.invitation.revoked',
                'revoked',
                EmployeeInvitation::class,
                $invitation->id,
                (int) $facilityId,
                ['status' => EmployeeInvitation::STATUS_PENDING],
                ['status' => EmployeeInvitation::STATUS_REVOKED],
            );

            return $invitation->fresh();
        });
    }

    private function assertInviter(User $inviter): void
    {
        if (! $inviter->isStaff() || ! $inviter->isActive()) {
            throw ValidationException::withMessages([
                'email' => 'Only active staff accounts can issue employee invitations.',
            ]);
        }

        if (! $inviter->hasPermissionTo('staff.manage')) {
            throw ValidationException::withMessages([
                'email' => 'You are not authorized to manage staff accounts.',
            ]);
        }
    }
}
