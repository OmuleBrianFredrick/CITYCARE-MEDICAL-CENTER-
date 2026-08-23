<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\ReportDefinition;
use App\Models\ReportRun;
use App\Models\User;
use App\Services\ReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
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

        $run = app(ReportingService::class)->run($staff, $definition, [
            'facility_id' => $facility->id,
            'date_from' => Carbon::today()->subDays(7)->toDateString(),
            'date_to' => Carbon::today()->toDateString(),
        ], $facility->id);

        $persistedRun = $run->fresh();

        $this->assertSame(ReportRun::STATUS_COMPLETED, $persistedRun->status);
        $this->assertSame($staff->id, $persistedRun->requested_by_id);
        $this->assertSame($facility->id, $persistedRun->facility_id);
        $this->assertIsArray($persistedRun->result_metadata);
        $this->assertSame(0, $persistedRun->result_metadata['total_encounters']);
        $this->assertArrayHasKey('by_status', $persistedRun->result_metadata);
        $this->assertNotNull($persistedRun->period_start);
        $this->assertNotNull($persistedRun->period_end);
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

        $this->expectException(ValidationException::class);

        app(ReportingService::class)->run($staff, $definition, [
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

        $this->expectException(ValidationException::class);

        app(ReportingService::class)->run($staff, $definition, []);
    }

    public function test_service_rejects_inactive_staff(): void
    {
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => false]);
        $definition = ReportDefinition::factory()->create([
            'code' => 'pharmacy_summary',
            'supported_filters' => [],
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(ReportingService::class)->run($staff, $definition, []);
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
            app(ReportingService::class)->run($staff, $definition, []);
            $this->fail('Expected report execution to fail.');
        } catch (\Throwable $exception) {
            $failedRun = ReportRun::query()
                ->where('report_definition_id', $definition->id)
                ->where('requested_by_id', $staff->id)
                ->sole();

            $this->assertSame(ReportRun::STATUS_FAILED, $failedRun->status);
            $this->assertNotNull($failedRun->error_message);
            $this->assertNotNull($failedRun->completed_at);
            $this->assertTrue($failedRun->isTerminal());
        }
    }
}
