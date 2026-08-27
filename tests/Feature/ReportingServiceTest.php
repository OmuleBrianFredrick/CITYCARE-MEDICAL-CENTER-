<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Facility;
use App\Models\ReportDefinition;
use App\Models\ReportRun;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\ReportingService;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ReportingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CityCareAccessSeeder::class);
    }

    public function test_service_executes_supported_report_and_completes_facility_scoped_run(): void
    {
        $facility = Facility::factory()->create();
        $staff = $this->staffAt($facility);
        $definition = $this->definition('clinical_activity', 'clinical', ['facility_id', 'date_from', 'date_to']);

        $run = app(ReportingService::class)->run($staff, $definition, [
            'facility_id' => $facility->id,
            'date_from' => Carbon::today()->subDays(7)->toDateString(),
            'date_to' => Carbon::today()->toDateString(),
        ], $facility->id);

        $persistedRun = $run->fresh();

        $this->assertSame(ReportRun::STATUS_COMPLETED, $persistedRun->status);
        $this->assertSame($staff->id, $persistedRun->requested_by_id);
        $this->assertSame($facility->id, $persistedRun->facility_id);
        $this->assertSame($facility->id, $persistedRun->filters['facility_id']);
        $this->assertSame(0, $persistedRun->result_metadata['total_encounters']);
        $this->assertArrayHasKey('by_status', $persistedRun->result_metadata);
        $this->assertNotNull($persistedRun->period_start);
        $this->assertNotNull($persistedRun->period_end);
    }

    public function test_service_rejects_unsupported_filter_before_creating_a_run(): void
    {
        $facility = Facility::factory()->create();
        $staff = $this->staffAt($facility, 'inventory');
        $definition = $this->definition('inventory_summary', 'inventory', ['facility_id']);

        try {
            app(ReportingService::class)->run($staff, $definition, ['unknown_filter' => 'x']);
            $this->fail('Expected the unsupported filter to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('filters.unknown_filter', $exception->errors());
        }

        $this->assertDatabaseCount('report_runs', 0);
    }

    public function test_service_rejects_inactive_definition(): void
    {
        $facility = Facility::factory()->create();
        $staff = $this->staffAt($facility);
        $definition = $this->definition('billing_summary', 'billing', [], false);

        $this->expectException(ValidationException::class);

        app(ReportingService::class)->run($staff, $definition);
    }

    public function test_service_rejects_inactive_staff(): void
    {
        $facility = Facility::factory()->create();
        $staff = $this->staffAt($facility, 'administrator', false);
        $definition = $this->definition('pharmacy_activity', 'pharmacy', []);

        $this->expectException(ValidationException::class);

        app(ReportingService::class)->run($staff, $definition);
    }

    public function test_service_rejects_report_without_underlying_operational_permission(): void
    {
        $facility = Facility::factory()->create();
        $recordsOfficer = $this->staffAt($facility, 'records');
        $definition = $this->definition('inventory_summary', 'inventory', ['facility_id']);

        $this->expectException(HttpException::class);

        app(ReportingService::class)->run($recordsOfficer, $definition);
    }

    public function test_service_rejects_another_facility_scope(): void
    {
        $assignedFacility = Facility::factory()->create();
        $otherFacility = Facility::factory()->create();
        $staff = $this->staffAt($assignedFacility);
        $definition = $this->definition('clinical_activity', 'clinical', ['facility_id']);

        $this->expectException(HttpException::class);

        app(ReportingService::class)->run(
            $staff,
            $definition,
            ['facility_id' => $otherFacility->id],
            $otherFacility->id,
        );
    }

    public function test_execution_failures_persist_only_a_safe_message(): void
    {
        $facility = Facility::factory()->create();
        $staff = $this->staffAt($facility);
        $definition = $this->definition('unsupported_code', 'clinical', []);

        try {
            app(ReportingService::class)->run($staff, $definition);
            $this->fail('Expected report execution to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(ReportingService::FAILURE_MESSAGE, $exception->errors()['report'][0]);
        }

        $failedRun = ReportRun::query()
            ->where('report_definition_id', $definition->id)
            ->where('requested_by_id', $staff->id)
            ->sole();

        $this->assertSame(ReportRun::STATUS_FAILED, $failedRun->status);
        $this->assertSame(ReportingService::FAILURE_MESSAGE, $failedRun->error_message);
        $this->assertNull($failedRun->result_metadata);
        $this->assertStringNotContainsString('Unsupported report definition', $failedRun->error_message);
        $this->assertNotNull($failedRun->completed_at);
        $this->assertTrue($failedRun->isTerminal());
    }

    private function definition(string $code, string $category, array $filters, bool $active = true): ReportDefinition
    {
        return ReportDefinition::factory()->create([
            'code' => $code,
            'category' => $category,
            'supported_filters' => $filters,
            'is_active' => $active,
        ]);
    }

    private function staffAt(Facility $facility, string $roleSlug = 'administrator', bool $active = true): User
    {
        $staff = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => $active,
        ]);
        $staff->roles()->sync([Role::query()->where('slug', $roleSlug)->valueOrFail('id')]);
        $department = Department::factory()->create(['facility_id' => $facility->id]);

        StaffProfile::create([
            'user_id' => $staff->id,
            'department_id' => $department->id,
            'employee_number' => 'REPORT-'.$staff->id,
            'employment_status' => $active ? 'active' : 'inactive',
        ]);

        return $staff;
    }
}
