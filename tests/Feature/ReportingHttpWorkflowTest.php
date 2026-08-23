<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Facility;
use App\Models\ReportDefinition;
use App\Models\ReportRun;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingHttpWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_staff_can_open_reports_workspace_run_report_and_view_run(): void
    {
        $this->seed(CityCareAccessSeeder::class);

        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->sync([Role::where('slug', 'administrator')->value('id')]);
        $facility = Facility::factory()->create();
        $definition = ReportDefinition::factory()->create([
            'code' => 'clinical_activity',
            'category' => 'clinical',
            'supported_filters' => ['facility_id', 'date_from', 'date_to'],
            'is_active' => true,
        ]);

        $this->actingAs($staff)->get(route('reports.index'))->assertOk();

        $response = $this->actingAs($staff)->post(route('reports.run', $definition), [
            'facility_id' => $facility->id,
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $run = ReportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(ReportRun::STATUS_COMPLETED, $run->status);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $staff->id,
            'event_type' => 'report.run.completed',
            'auditable_id' => $run->id,
        ]);

        $this->actingAs($staff)->get(route('reports.show', $run))->assertOk();
    }

    public function test_user_without_reports_view_cannot_run_or_view_reports(): void
    {
        $this->seed(CityCareAccessSeeder::class);

        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->sync([Role::where('slug', 'receptionist')->value('id')]);
        $definition = ReportDefinition::factory()->create(['code' => 'clinical_activity']);

        $this->actingAs($staff)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('reports.run', $definition), [])->assertForbidden();
    }

    public function test_user_without_audit_view_cannot_open_audit_workspace(): void
    {
        $this->seed(CityCareAccessSeeder::class);

        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->sync([Role::where('slug', 'doctor')->value('id')]);

        AuditEvent::factory()->create();

        $this->actingAs($staff)->get(route('audit.index'))->assertForbidden();
    }

    public function test_invalid_report_filters_are_rejected_before_service_execution(): void
    {
        $this->seed(CityCareAccessSeeder::class);

        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->sync([Role::where('slug', 'administrator')->value('id')]);
        $definition = ReportDefinition::factory()->create([
            'code' => 'clinical_activity',
            'supported_filters' => ['facility_id'],
        ]);

        $this->actingAs($staff)
            ->from(route('reports.index'))
            ->post(route('reports.run', $definition), [
                'date_from' => 'not-a-date',
            ])
            ->assertSessionHasErrors('date_from');

        $this->assertDatabaseCount('report_runs', 0);
    }
}
