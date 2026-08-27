<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportReportRequest;
use App\Http\Requests\RunReportRequest;
use App\Models\ReportDefinition;
use App\Models\ReportRun;
use App\Services\ReportExportService;
use App\Services\ReportingAccessService;
use App\Services\ReportingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportingController extends Controller
{
    public function index(Request $request, ReportingAccessService $access): View
    {
        $staff = $request->user();
        $definitions = $access->definitionsFor($staff);
        $facilities = $access->facilitiesFor($staff);
        $runs = $access->scopeRuns(ReportRun::query(), $staff)
            ->with(['definition', 'requester', 'facility'])
            ->whereIn('report_definition_id', $access->definitionIdsForHistory($staff))
            ->latest('id')
            ->limit(20)
            ->get();

        return view('reports.index', [
            'definitions' => $definitions,
            'facilities' => $facilities,
            'runs' => $runs,
            'canRunOrganizationWide' => $staff->hasRole('super-admin'),
        ]);
    }

    public function run(
        RunReportRequest $request,
        ReportDefinition $reportDefinition,
        ReportingService $service,
        ReportingAccessService $access,
    ): RedirectResponse {
        $staff = $request->user();
        $access->assertDefinitionAccessible($staff, $reportDefinition);

        $filters = $request->validated()['filters'] ?? [];
        $requestedFacilityId = isset($filters['facility_id']) ? (int) $filters['facility_id'] : null;
        $facility = $access->resolveFacility($staff, $requestedFacilityId);
        unset($filters['facility_id']);

        $run = $service->run(
            $staff,
            $reportDefinition,
            $filters,
            $facility?->id,
        );

        return redirect()
            ->route('reports.show', $run)
            ->with('status', 'Report generated successfully.');
    }

    public function show(Request $request, int $reportRun, ReportingAccessService $access): View
    {
        $run = $access->scopeRuns(ReportRun::query(), $request->user())
            ->with(['definition', 'requester', 'facility'])
            ->findOrFail($reportRun);
        $access->assertRunAccessible($request->user(), $run);

        return view('reports.show', compact('run'));
    }

    public function export(
        ExportReportRequest $request,
        ReportExportService $exportService,
        ReportingAccessService $access,
    ): StreamedResponse {
        $run = $access->scopeRuns(ReportRun::query(), $request->user())
            ->with(['definition', 'requester', 'facility'])
            ->findOrFail($request->reportRunId());
        $access->assertRunAccessible($request->user(), $run);

        return $exportService->csv($request->user(), $run);
    }
}
