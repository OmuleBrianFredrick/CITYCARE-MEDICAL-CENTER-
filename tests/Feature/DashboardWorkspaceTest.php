<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Facility;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Role;
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
            ->assertSee('Organization');
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
