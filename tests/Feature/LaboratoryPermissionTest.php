<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaboratoryPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_create_and_view_diagnostic_orders(): void
    {
        $this->seed();
        $doctor = $this->userWithRole('doctor');

        $this->assertTrue($doctor->hasPermissionTo('laboratory.view'));
        $this->assertTrue($doctor->hasPermissionTo('laboratory.orders.create'));
        $this->assertFalse($doctor->hasPermissionTo('laboratory.results.record'));
    }

    public function test_laboratory_staff_can_view_record_and_manage_diagnostic_work(): void
    {
        $this->seed();
        $laboratory = $this->userWithRole('laboratory');

        $this->assertTrue($laboratory->hasPermissionTo('laboratory.view'));
        $this->assertTrue($laboratory->hasPermissionTo('laboratory.results.record'));
        $this->assertTrue($laboratory->hasPermissionTo('laboratory.work.manage'));
        $this->assertFalse($laboratory->hasPermissionTo('laboratory.orders.create'));
    }

    public function test_nurse_has_diagnostic_visibility_without_diagnostic_write_access(): void
    {
        $this->seed();
        $nurse = $this->userWithRole('nurse');

        $this->assertTrue($nurse->hasPermissionTo('laboratory.view'));
        $this->assertFalse($nurse->hasPermissionTo('laboratory.orders.create'));
        $this->assertFalse($nurse->hasPermissionTo('laboratory.results.record'));
        $this->assertFalse($nurse->hasPermissionTo('laboratory.work.manage'));
    }

    private function userWithRole(string $roleSlug): User
    {
        $user = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', $roleSlug)->valueOrFail('id'));

        return $user;
    }
}
