<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\ReportDefinition;
use App\Models\ReportRun;
use App\Models\User;
use App\Services\ReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_executes_supported_report_and_completes_run(): void
    {
        $facility = Facility::factory()->create();
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);

        $definition = ReportDefinition::factory()->create([
            'code' => 'clinical_activity',
            'category' => 'clinical',
            'supported_filters' => ['facility_id', 'date_from', 'date_to'],
            'is_active' => true,
        ]);

        $service = app(ReportingService::class);

        $run = $service->run($definition, $staff, [
            'facility_id' => $facility->id,
            'date_from' => Carbon::today()->subDays(7)->toDateString(),
            'date_to' => Carbon::today()->toDateString(),
        ]);

        $this->assertSame(ReportRun::STATUS_COMPLETED, $run->fresh()->status);
        $this->assertSame($staff->id, $run->requested_by_id);
        $this->assertSame($facility->id, $run->facility_id);
        $this->assertIsArray($run->result);
        $this->assertSame('clinical_activity', $run->result['report']);
    }

    public function test_service_rejects_unsupported_filter_before_execution(): void
    {
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $definition = ReportDefinition::factory()->create([
            'code' => 'inventory_summary',
            'category' => 'inventory',
            'supported_filters' => ['facility_id'],
            'is_active' => true,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(ReportingService::class)->run($definition, $staff, [
            'unknown_filter' => 'x',
        ]);
    }

    public function test_service_rejects_inactive_definition(): void
    {
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $definition = ReportDefinition::factory()->create([
            'code' => 'billing_summary',
            'supported_filters' => [],
            'is_active' => false,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(ReportingService::class)->run($definition, $staff, []);
    }

    public function test_service_rejects_inactive_staff(): void
    {
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => false]);
        $definition = ReportDefinition::factory()->create([
            'code' => 'pharmacy_summary',
            'supported_filters' => [],
            'is_active' => true,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(ReportingService::class)->run($definition, $staff, []);
    }

    public function test_service_creates_failed_run_when_execution_throws(): void
    {
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $definition = ReportDefinition::factory()->create([
            'code' => 'unsupported_code',
            'supported_filters' => [],
            'is_active' => true,
        ]);

        try {
            app(ReportingService::class)->run($definition, $staff, []);
            $this->fail('Expected report execution to fail.');
        } catch (\Throwable $exception) {
            $this->assertDatabaseHas('report_runs', [
                'report_definition_id' => $definition->id,
                'requested_by_id' => $staff->id,
                'status' => ReportRun::STATUS_FAILED,
            ]);
        }
    }
}
