<?php

namespace Tests\Feature;

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

        $doctor = Role::where('slug', 'doctor')->with('permissions')->firstOrFail();
        $nurse = Role::where('slug', 'nurse')->with('permissions')->firstOrFail();
        $pharmacy = Role::where('slug', 'pharmacy')->with('permissions')->firstOrFail();
        $administrator = Role::where('slug', 'administrator')->with('permissions')->firstOrFail();

        $doctorPermissions = $doctor->permissions->pluck('slug')->all();
        $nursePermissions = $nurse->permissions->pluck('slug')->all();
        $pharmacyPermissions = $pharmacy->permissions->pluck('slug')->all();
        $administratorPermissions = $administrator->permissions->pluck('slug')->all();

        $this->assertContains('pharmacy.view', $doctorPermissions);
        $this->assertContains('pharmacy.prescriptions.create', $doctorPermissions);
        $this->assertNotContains('pharmacy.dispensing.manage', $doctorPermissions);
        $this->assertNotContains('pharmacy.work.manage', $doctorPermissions);

        $this->assertContains('pharmacy.view', $nursePermissions);
        $this->assertNotContains('pharmacy.prescriptions.create', $nursePermissions);

        $this->assertContains('pharmacy.view', $pharmacyPermissions);
        $this->assertContains('pharmacy.dispensing.manage', $pharmacyPermissions);
        $this->assertContains('pharmacy.work.manage', $pharmacyPermissions);
        $this->assertContains('pharmacy.manage', $pharmacyPermissions);

        foreach ($expectedPermissions as $slug) {
            $this->assertContains($slug, $administratorPermissions);
        }
    }
}
