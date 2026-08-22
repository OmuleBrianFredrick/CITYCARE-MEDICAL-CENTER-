<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pharmacy_permission_matrix_is_seeded_correctly(): void
    {
        $this->seed(\Database\Seeders\CityCareAccessSeeder::class);

        $expectedPermissions = [
            'pharmacy.view',
            'pharmacy.prescriptions.create',
            'pharmacy.dispensing.manage',
            'pharmacy.work.manage',
            'pharmacy.manage',
        ];

        foreach ($expectedPermissions as $slug) {
            $this->assertDatabaseHas('permissions', ['slug' => $slug]);
        }

        $doctor = Role::where('slug', 'doctor')->firstOrFail();
        $nurse = Role::where('slug', 'nurse')->firstOrFail();
        $pharmacy = Role::where('slug', 'pharmacy')->firstOrFail();
        $administrator = Role::where('slug', 'administrator')->firstOrFail();

        $this->assertTrue($doctor->hasPermissionTo('pharmacy.view'));
        $this->assertTrue($doctor->hasPermissionTo('pharmacy.prescriptions.create'));
        $this->assertFalse($doctor->hasPermissionTo('pharmacy.dispensing.manage'));
        $this->assertFalse($doctor->hasPermissionTo('pharmacy.work.manage'));

        $this->assertTrue($nurse->hasPermissionTo('pharmacy.view'));
        $this->assertFalse($nurse->hasPermissionTo('pharmacy.prescriptions.create'));

        $this->assertTrue($pharmacy->hasPermissionTo('pharmacy.view'));
        $this->assertTrue($pharmacy->hasPermissionTo('pharmacy.dispensing.manage'));
        $this->assertTrue($pharmacy->hasPermissionTo('pharmacy.work.manage'));
        $this->assertTrue($pharmacy->hasPermissionTo('pharmacy.manage'));

        foreach ($expectedPermissions as $slug) {
            $this->assertTrue($administrator->hasPermissionTo($slug));
        }
    }
}
