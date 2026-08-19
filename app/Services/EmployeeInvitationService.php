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
    public function create(User $inviter, string $name, string $email, string $roleSlug): array
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

        $role = Role::where('slug', $roleSlug)->firstOrFail();

        if ($role->slug === 'super-admin' && ! $inviter->hasRole('super-admin')) {
            throw ValidationException::withMessages([
                'role' => 'Only a Super Administrator can invite another Super Administrator.',
            ]);
        }

        $email = strtolower(trim($email));
        $existingUser = User::where('email', $email)->first();

        if ($existingUser && $existingUser->isActive()) {
            throw ValidationException::withMessages([
                'email' => 'An active CityCare account already exists for this email address.',
            ]);
        }

        $pending = EmployeeInvitation::query()
            ->where('email', $email)
            ->where('status', EmployeeInvitation::STATUS_PENDING)
            ->get()
            ->filter(fn (EmployeeInvitation $invitation) => ! $invitation->isExpired())
            ->first();

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

            return [$invitation, $plainToken];
        });

        return $result;
    }
}
