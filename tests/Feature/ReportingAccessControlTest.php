<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Facility;
use App\Models\ReportDefinition;
use App\Models\ReportRun;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CityCareAccessSeeder::class);
    }

    public function test_workspace_only_lists_definitions_backed_by_operational_permissions(): void
    {
        $facility = Facility::factory()->create();
        $laboratoryStaff = $this->staffAt($facility, 'laboratory');

        $this->definition('clinical_activity', 'Clinical access restricted');
        $this->definition('laboratory_activity', 'Laboratory report allowed');
        $this->definition('pharmacy_activity', 'Pharmacy access restricted');
        $this->definition('billing_summary', 'Billing access restricted');
        $this->definition('inventory_summary', 'Inventory access restricted');

        $this->actingAs($laboratoryStaff)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Laboratory report allowed')
            ->assertDontSee('Clinical access restricted')
            ->assertDontSee('Pharmacy access restricted')
            ->assertDontSee('Billing access restricted')
            ->assertDontSee('Inventory access restricted');
    }

    public function test_non_super_administrator_is_forced_to_assigned_facility(): void
    {
        $assignedFacility = Facility::factory()->create();
        $otherFacility = Facility::factory()->create();
        $administrator = $this->staffAt($assignedFacility, 'administrator');
        $definition = $this->definition('clinical_activity', 'Clinical facility report', ['facility_id']);

        $this->actingAs($administrator)
            ->post(route('reports.run', $definition))
            ->assertRedirect();

        $this->assertDatabaseHas('report_runs', [
            'requested_by_id' => $administrator->id,
            'facility_id' => $assignedFacility->id,
            'status' => ReportRun::STATUS_COMPLETED,
        ]);

        $this->actingAs($administrator)
            ->post(route('reports.run', $definition), ['facility_id' => $otherFacility->id])
            ->assertForbidden();

        $this->assertDatabaseCount('report_runs', 1);
    }

    public function test_foreign_facility_run_is_not_discoverable_through_show_or_export(): void
    {
        $assignedFacility = Facility::factory()->create();
        $otherFacility = Facility::factory()->create();
        $administrator = $this->staffAt($assignedFacility, 'administrator');
        $definition = $this->definition('clinical_activity', 'Clinical report');
        $run = $this->completedRun($definition, $administrator, $otherFacility);

        $this->actingAs($administrator)
            ->get(route('reports.show', $run))
            ->assertNotFound();

        $this->actingAs($administrator)
            ->post(route('reports.export'), ['format' => 'csv', 'report_run' => $run->id])
            ->assertNotFound();
    }

    public function test_same_facility_run_still_requires_the_underlying_report_permission(): void
    {
        $facility = Facility::factory()->create();
        $recordsOfficer = $this->staffAt($facility, 'records');
        $definition = $this->definition('inventory_summary', 'Inventory report');
        $run = $this->completedRun($definition, $recordsOfficer, $facility);

        $this->actingAs($recordsOfficer)
            ->get(route('reports.show', $run))
            ->assertForbidden();

        $this->actingAs($recordsOfficer)
            ->post(route('reports.export'), ['format' => 'csv', 'report_run' => $run->id])
            ->assertForbidden();
    }

    public function test_super_administrator_can_generate_organization_wide_run(): void
    {
        Facility::factory()->count(2)->create();
        $superAdministrator = $this->staffWithRole('super-admin');
        $definition = $this->definition('clinical_activity', 'Organization clinical report', ['facility_id']);

        $this->actingAs($superAdministrator)
            ->post(route('reports.run', $definition))
            ->assertRedirect();

        $run = ReportRun::query()->sole();

        $this->assertNull($run->facility_id);
        $this->assertSame(ReportRun::STATUS_COMPLETED, $run->status);

        $this->actingAs($superAdministrator)
            ->get(route('reports.show', $run))
            ->assertOk()
            ->assertSee('All facilities');
    }

    public function test_recent_runs_retain_accessible_history_after_definition_is_disabled(): void
    {
        $facility = Facility::factory()->create();
        $administrator = $this->staffAt($facility, 'administrator');
        $definition = $this->definition('clinical_activity', 'Archived clinical report');
        $this->completedRun($definition, $administrator, $facility);
        $definition->update(['is_active' => false]);

        $this->actingAs($administrator)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Archived clinical report');
    }

    private function completedRun(ReportDefinition $definition, User $requester, Facility $facility): ReportRun
    {
        return ReportRun::factory()->create([
            'report_definition_id' => $definition->id,
            'requested_by_id' => $requester->id,
            'facility_id' => $facility->id,
            'status' => ReportRun::STATUS_COMPLETED,
            'result_metadata' => ['report' => $definition->code, 'total' => 1],
        ]);
    }

    private function definition(string $code, string $name, array $filters = ['facility_id']): ReportDefinition
    {
        return ReportDefinition::factory()->create([
            'code' => $code,
            'name' => $name,
            'category' => match ($code) {
                'clinical_activity' => 'clinical',
                'laboratory_activity' => 'laboratory',
                'pharmacy_activity' => 'pharmacy',
                'billing_summary' => 'billing',
                'inventory_summary' => 'inventory',
                default => 'operational',
            },
            'supported_filters' => $filters,
            'is_active' => true,
        ]);
    }

    private function staffAt(Facility $facility, string $roleSlug): User
    {
        $staff = $this->staffWithRole($roleSlug);
        $department = Department::factory()->create(['facility_id' => $facility->id]);

        StaffProfile::create([
            'user_id' => $staff->id,
            'department_id' => $department->id,
            'employee_number' => 'REPORT-ACCESS-'.$staff->id,
            'employment_status' => 'active',
        ]);

        return $staff;
    }

    private function staffWithRole(string $roleSlug): User
    {
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->sync([Role::query()->where('slug', $roleSlug)->valueOrFail('id')]);

        return $staff;
    }
}
