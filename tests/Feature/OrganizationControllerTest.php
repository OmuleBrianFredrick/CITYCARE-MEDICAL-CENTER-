<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Facility;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_administrator_can_view_organization_workspace(): void
    {
        $admin = $this->staffWithRole('administrator');

        $this->actingAs($admin)
            ->get(route('organization.index'))
            ->assertOk()
            ->assertSee('CityCare configuration');
    }

    public function test_receptionist_cannot_access_organization_workspace(): void
    {
        $receptionist = $this->staffWithRole('receptionist');

        $this->actingAs($receptionist)
            ->get(route('organization.index'))
            ->assertForbidden();
    }

    public function test_administrator_can_configure_facility_and_create_structure(): void
    {
        $admin = $this->staffWithRole('administrator');

        $this->actingAs($admin)
            ->put(route('organization.facility.update'), [
                'name' => 'CityCare Medical Center',
                'legal_name' => 'CityCare Medical Center Limited',
                'country' => 'Uganda',
                'timezone' => 'Africa/Kampala',
                'currency' => 'UGX',
                'primary_color' => '#2563EB',
                'secondary_color' => '#0F172A',
                'accent_color' => '#F4C430',
            ])
            ->assertRedirect();

        $facility = Facility::firstOrFail();

        $this->actingAs($admin)
            ->post(route('organization.departments.store'), [
                'name' => 'Outpatient Department',
                'code' => 'OPD',
            ])
            ->assertRedirect();

        $department = Department::where('code', 'OPD')->firstOrFail();
        $this->assertSame($facility->id, $department->facility_id);

        $this->actingAs($admin)
            ->post(route('organization.service-points.store'), [
                'department_id' => $department->id,
                'name' => 'General Consultation',
                'code' => 'OPD-GENERAL',
                'type' => 'clinic',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('service_points', [
            'department_id' => $department->id,
            'code' => 'OPD-GENERAL',
        ]);
    }

    public function test_organization_management_requires_manage_permission(): void
    {
        $receptionist = $this->staffWithRole('receptionist');

        $this->actingAs($receptionist)
            ->put(route('organization.facility.update'), [
                'name' => 'Unauthorized Change',
                'country' => 'Uganda',
                'timezone' => 'Africa/Kampala',
                'currency' => 'UGX',
                'primary_color' => '#2563EB',
                'secondary_color' => '#0F172A',
                'accent_color' => '#F4C430',
            ])
            ->assertForbidden();
    }

    private function staffWithRole(string $roleSlug): User
    {
        $user = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('slug', $roleSlug)->firstOrFail());

        return $user;
    }
}
