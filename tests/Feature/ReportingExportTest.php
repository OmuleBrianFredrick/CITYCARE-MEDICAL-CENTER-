<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\ReportDefinition;
use App\Models\ReportRun;
use App\Models\Role;
use App\Models\User;
use App\Services\ReportExportService;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportingExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_export_completed_report_as_csv(): void
    {
        $this->seed(CityCareAccessSeeder::class);

        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->sync([Role::where('slug', 'administrator')->value('id')]);
        $facility = Facility::factory()->create();
        $definition = ReportDefinition::factory()->create(['code' => 'clinical_activity']);
        $run = ReportRun::factory()->create([
            'report_definition_id' => $definition->id,
            'requested_by_id' => $staff->id,
            'facility_id' => $facility->id,
            'status' => ReportRun::STATUS_COMPLETED,
            'result_metadata' => [
                'report' => 'clinical_activity',
                'total_encounters' => 4,
                'by_status' => ['closed' => 1, 'open' => 3],
            ],
        ]);

        $response = app(ReportExportService::class)->csv($run);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename=report-'.$run->id.'.csv', $response->headers->get('Content-Disposition'));

        $content = $response->getCallback();
        ob_start();
        $content();
        $csv = ob_get_clean();

        $this->assertStringContainsString('Report,Clinical Activity', $csv);
        $this->assertStringContainsString('Total Encounters,4', $csv);
        $this->assertStringContainsString('By Status,"{', $csv);
        $this->assertStringContainsString('closed', $csv);
        $this->assertStringContainsString('open', $csv);
    }

    public function test_http_export_route_requires_reports_view_and_validates_format(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->sync([Role::where('slug', 'receptionist')->value('id')]);
        $run = ReportRun::factory()->create();

        $this->actingAs($staff)
            ->post(route('reports.export'), ['format' => 'csv', 'report_run' => $run->id])
            ->assertForbidden();
    }

    public function test_export_rejects_unknown_format_before_service_execution(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->sync([Role::where('slug', 'administrator')->value('id')]);
        $run = ReportRun::factory()->create();

        $this->actingAs($staff)
            ->from(route('reports.index'))
            ->post(route('reports.export'), ['format' => 'pdf', 'report_run' => $run->id])
            ->assertSessionHasErrors('format');
    }
}
