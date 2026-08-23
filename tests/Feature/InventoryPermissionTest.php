<?php

namespace Tests\Feature;

use App\Models\Role;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_permission_matrix_is_seeded_correctly(): void
    {
        $this->seed(CityCareAccessSeeder::class);

        $inventory = Role::where('slug', 'inventory')->firstOrFail();
        $pharmacy = Role::where('slug', 'pharmacy')->firstOrFail();
        $administrator = Role::where('slug', 'administrator')->firstOrFail();
        $doctor = Role::where('slug', 'doctor')->firstOrFail();
        $cashier = Role::where('slug', 'cashier')->firstOrFail();

        $this->assertTrue($inventory->permissions()->where('slug', 'inventory.view')->exists());
        $this->assertTrue($inventory->permissions()->where('slug', 'inventory.manage')->exists());
        $this->assertTrue($administrator->permissions()->where('slug', 'inventory.view')->exists());
        $this->assertTrue($administrator->permissions()->where('slug', 'inventory.manage')->exists());
        $this->assertTrue($pharmacy->permissions()->where('slug', 'inventory.view')->exists());
        $this->assertFalse($pharmacy->permissions()->where('slug', 'inventory.manage')->exists());
        $this->assertFalse($doctor->permissions()->where('slug', 'inventory.manage')->exists());
        $this->assertFalse($cashier->permissions()->where('slug', 'inventory.manage')->exists());
    }
}
