<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class PatientPortalService
{
    public function provision(Patient $patient): User
    {
        return DB::transaction(function () use ($patient): User {
            if ($patient->user_id !== null) {
                throw new RuntimeException('This patient already has a portal account.');
            }

            if (blank($patient->email)) {
                throw new RuntimeException('A patient email address is required before portal access can be provisioned.');
            }

            $existing = User::where('email', strtolower(trim($patient->email)))->first();

            if ($existing) {
                throw new RuntimeException('A user account already exists for this email address.');
            }

            $user = User::create([
                'name' => $patient->full_name,
                'email' => strtolower(trim($patient->email)),
                'password' => Hash::make(Str::random(48)),
                'user_type' => 'patient',
                'is_active' => false,
            ]);

            $patientRoleId = Role::query()->where('slug', 'patient')->value('id');

            if ($patientRoleId === null) {
                throw new RuntimeException('The patient portal role is not configured.');
            }

            $user->roles()->attach($patientRoleId);

            $patient->forceFill([
                'user_id' => $user->id,
                'portal_invited_at' => now(),
            ])->save();

            return $user;
        });
    }

    public function activate(Patient $patient): void
    {
        DB::transaction(function () use ($patient): void {
            $user = $patient->user;

            if (! $user) {
                throw new RuntimeException('The patient does not have a portal account.');
            }

            $user->forceFill(['is_active' => true])->save();
            $patient->forceFill([
                'portal_activated_at' => now(),
                'portal_disabled_at' => null,
            ])->save();
        });
    }

    public function disable(Patient $patient): void
    {
        DB::transaction(function () use ($patient): void {
            $user = $patient->user;

            if (! $user) {
                throw new RuntimeException('The patient does not have a portal account.');
            }

            $user->forceFill(['is_active' => false])->save();
            $patient->forceFill(['portal_disabled_at' => now()])->save();
        });
    }
}
