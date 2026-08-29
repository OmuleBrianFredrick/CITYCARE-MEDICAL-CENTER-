<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClinicalDiagnosisRequest;
use App\Models\ClinicalEncounter;
use App\Services\ClinicalDiagnosisService;
use App\Services\FacilityAccessService;
use Illuminate\Http\RedirectResponse;

class ClinicalDiagnosisController extends Controller
{
    public function __construct(
        private readonly ClinicalDiagnosisService $diagnoses,
        private readonly FacilityAccessService $facilityAccess,
    ) {}

    public function store(StoreClinicalDiagnosisRequest $request, ClinicalEncounter $encounter): RedirectResponse
    {
        $this->facilityAccess->assertEncounterAccessible($request->user(), $encounter);
        $diagnosis = $this->diagnoses->record(
            $encounter,
            $request->user(),
            $request->validated()
        );

        return back()->with(
            'status',
            "Diagnosis {$diagnosis->diagnosis} recorded successfully."
        );
    }
}
