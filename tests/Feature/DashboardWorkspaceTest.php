<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_super_admin_sees_live_permitted_metrics_and_actions(): void
    {
        $facility = Facility::query()->where('is_active', true)->firstOrFail();
        $user = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $user->roles()->attach(Role::query()->where('slug', 'super-admin')->value('id'));

        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        Appointment::factory()->create([
            'facility_id' => $facility->id,
            'patient_id' => $patient->id,
            'scheduled_start' => now()->setTime(10, 0),
            'scheduled_end' => now()->setTime(10, 30),
        ]);
        Invoice::factory()->issued()->create([
            'facility_id' => $facility->id,
            'patient_id' => $patient->id,
            'total' => 125000,
            'balance_due' => 125000,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Active patients')
            ->assertSee('Appointments today')
            ->assertSee('Outstanding balance')
            ->assertSee('UGX 125,000.00')
            ->assertSee('Register patient')
            ->assertSee('Configure organization')
            ->assertSee('Invite staff member')
            ->assertSee('Manage role permissions')
            ->assertSee(route('staff.index'), false)
            ->assertSee(route('access.roles.index'), false)
            ->assertSee('Organization');
    }

    public function test_operational_administrator_shell_uses_assigned_facility_and_hides_super_access_control(): void
    {
        $assignedFacility = Facility::factory()->create(['name' => 'Assigned Care Facility']);
        $department = Department::factory()->create(['facility_id' => $assignedFacility->id]);
        $administrator = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $administrator->roles()->sync([Role::query()->where('slug', 'administrator')->valueOrFail('id')]);
        StaffProfile::create([
            'user_id' => $administrator->id,
            'department_id' => $department->id,
            'employee_number' => 'DASH-ADMIN-'.$administrator->id,
            'employment_status' => 'active',
        ]);

        Patient::factory()->count(2)->create([
            'facility_id' => $assignedFacility->id,
            'status' => Patient::STATUS_ACTIVE,
        ]);

        $this->actingAs($administrator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Assigned Care Facility')
            ->assertSee(route('staff.index'), false)
            ->assertDontSee(route('access.roles.index'), false)
            ->assertSee('Active patients');
    }

    public function test_patient_dashboard_does_not_expose_staff_navigation_or_metrics(): void
    {
        $user = User::factory()->create(['user_type' => 'patient', 'is_active' => true]);
        $user->roles()->attach(Role::query()->where('slug', 'patient')->value('id'));
        $facility = Facility::query()->where('is_active', true)->firstOrFail();
        Patient::factory()->create([
            'facility_id' => $facility->id,
            'user_id' => $user->id,
            'portal_activated_at' => now(),
            'portal_disabled_at' => null,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('portal.index'));

        $this->actingAs($user)
            ->get(route('portal.index'))
            ->assertOk()
            ->assertDontSee('Active patients')
            ->assertDontSee('Appointments today')
            ->assertDontSee('Organization')
            ->assertSee('My health')
            ->assertSee('My profile');
    }
}
