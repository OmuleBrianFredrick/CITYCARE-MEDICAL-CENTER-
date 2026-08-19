<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_citycare_system_roles_and_permissions_are_seeded(): void
    {
        $this->seed();

        $this->assertDatabaseHas('roles', ['slug' => 'super-admin']);
        $this->assertDatabaseHas('roles', ['slug' => 'doctor']);
        $this->assertDatabaseHas('roles', ['slug' => 'patient']);
        $this->assertDatabaseHas('permissions', ['slug' => 'patients.create']);
        $this->assertDatabaseHas('permissions', ['slug' => 'billing.manage']);
    }

    public function test_user_can_resolve_role_and_permission(): void
    {
        $this->seed();

        $doctor = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => true,
        ]);

        $doctor->roles()->attach(Role::where('slug', 'doctor')->firstOrFail());

        $this->assertTrue($doctor->isStaff());
        $this->assertTrue($doctor->hasRole('doctor'));
        $this->assertTrue($doctor->hasPermissionTo('clinical.encounters.create'));
        $this->assertFalse($doctor->hasPermissionTo('access.manage'));
    }

    public function test_patient_and_staff_accounts_are_distinguished(): void
    {
        $this->seed();

        $patient = User::factory()->create(['user_type' => 'patient']);
        $staff = User::factory()->create(['user_type' => 'staff']);

        $this->assertTrue($patient->isPatient());
        $this->assertFalse($patient->isStaff());
        $this->assertTrue($staff->isStaff());
        $this->assertFalse($staff->isPatient());
    }
}
