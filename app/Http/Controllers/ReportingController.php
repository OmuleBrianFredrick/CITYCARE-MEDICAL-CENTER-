<?php

namespace App\Http\Controllers;

use App\Http\Requests\RunReportRequest;
use App\Models\ReportDefinition;
use App\Services\ReportingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReportingController extends Controller
{
    public function index(): View
    {
        $definitions = ReportDefinition::query()
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return view('reports.index', compact('definitions'));
    }

    public function run(RunReportRequest $request, ReportDefinition $reportDefinition, ReportingService $service): RedirectResponse
    {
        $filters = $request->validated()['filters'] ?? [];

        $run = $service->run(
            $request->user(),
            $reportDefinition,
            $filters,
            isset($filters['facility_id']) ? (int) $filters['facility_id'] : null,
        );

        return redirect()
            ->route('reports.show', $run)
            ->with('status', 'Report generated successfully.');
    }

    public function show(int $reportRun): View
    {
        $run = \App\Models\ReportRun::query()
            ->with(['definition', 'requester', 'facility'])
            ->findOrFail($reportRun);

        return view('reports.show', compact('run'));
    }
}
