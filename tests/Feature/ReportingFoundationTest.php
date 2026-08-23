<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Facility;
use App\Models\ReportDefinition;
use App\Models\ReportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reporting_foundation_persists_and_links_report_definition_run_and_audit_event(): void
    {
        $facility = Facility::factory()->create();
        $user = User::factory()->create();
        $definition = ReportDefinition::factory()->create();

        $run = ReportRun::factory()->create([
            'report_definition_id' => $definition->id,
            'requested_by_id' => $user->id,
            'facility_id' => $facility->id,
            'status' => ReportRun::STATUS_RUNNING,
            'filters' => ['facility' => $facility->id, 'date_range' => 'today'],
        ]);

        $event = AuditEvent::factory()->create([
            'actor_id' => $user->id,
            'facility_id' => $facility->id,
            'auditable_type' => ReportRun::class,
            'auditable_id' => $run->id,
            'before_values' => ['status' => ReportRun::STATUS_QUEUED],
            'after_values' => ['status' => ReportRun::STATUS_RUNNING],
        ]);

        $this->assertTrue($definition->runs()->whereKey($run->id)->exists());
        $this->assertTrue($run->requester()->whereKey($user->id)->exists());
        $this->assertTrue($run->facility()->whereKey($facility->id)->exists());
        $this->assertTrue($event->actor()->whereKey($user->id)->exists());
        $this->assertTrue($event->facility()->whereKey($facility->id)->exists());
        $this->assertSame(['status' => ReportRun::STATUS_QUEUED], $event->before_values);
        $this->assertSame(['status' => ReportRun::STATUS_RUNNING], $event->after_values);
    }

    public function test_report_run_terminal_state_is_explicit(): void
    {
        $completed = ReportRun::factory()->create(['status' => ReportRun::STATUS_COMPLETED]);
        $failed = ReportRun::factory()->create(['status' => ReportRun::STATUS_FAILED]);
        $queued = ReportRun::factory()->create(['status' => ReportRun::STATUS_QUEUED]);
        $running = ReportRun::factory()->create(['status' => ReportRun::STATUS_RUNNING]);

        $this->assertTrue($completed->isTerminal());
        $this->assertTrue($failed->isTerminal());
        $this->assertFalse($queued->isTerminal());
        $this->assertFalse($running->isTerminal());
    }

    public function test_report_definition_code_is_unique(): void
    {
        $definition = ReportDefinition::factory()->create(['code' => 'clinical-summary']);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        ReportDefinition::factory()->create(['code' => $definition->code]);
    }
}
