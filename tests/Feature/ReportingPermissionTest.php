<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reporting_and_audit_permissions_are_defined_without_duplicates(): void
    {
        $this->seed(CityCareAccessSeeder::class);

        $expected = [
            'reports.view',
            'audit.view',
        ];

        foreach ($expected as $slug) {
            $this->assertSame(1, Permission::where('slug', $slug)->count());
        }
    }

    public function test_required_roles_receive_only_existing_reporting_access(): void
    {
        $this->seed(CityCareAccessSeeder::class);

        $this->assertTrue(Role::where('slug', 'super-admin')->firstOrFail()->permissions()->where('slug', 'reports.view')->exists());
        $this->assertTrue(Role::where('slug', 'super-admin')->firstOrFail()->permissions()->where('slug', 'audit.view')->exists());

        foreach (['administrator', 'doctor', 'laboratory', 'pharmacy', 'cashier', 'records', 'inventory'] as $roleSlug) {
            $role = Role::where('slug', $roleSlug)->firstOrFail();
            $this->assertTrue($role->permissions()->where('slug', 'reports.view')->exists(), "{$roleSlug} should have reports.view");
            $this->assertFalse($role->permissions()->where('slug', 'audit.view')->exists(), "{$roleSlug} should not have audit.view");
        }

        foreach (['receptionist', 'nurse', 'patient'] as $roleSlug) {
            $role = Role::where('slug', $roleSlug)->firstOrFail();
            $this->assertFalse($role->permissions()->where('slug', 'reports.view')->exists(), "{$roleSlug} should not have reports.view");
            $this->assertFalse($role->permissions()->where('slug', 'audit.view')->exists(), "{$roleSlug} should not have audit.view");
        }

        $administrator = Role::where('slug', 'administrator')->firstOrFail();
        $this->assertFalse($administrator->permissions()->whereIn('slug', ['reports.run', 'reports.export', 'audit.manage'])->exists());
    }
}
