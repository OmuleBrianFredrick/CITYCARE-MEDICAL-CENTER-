<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClinicalDiagnosisRequest;
use App\Models\ClinicalEncounter;
use App\Services\ClinicalDiagnosisService;
use Illuminate\Http\RedirectResponse;

class ClinicalDiagnosisController extends Controller
{
    public function __construct(private readonly ClinicalDiagnosisService $diagnoses)
    {
    }

    public function store(StoreClinicalDiagnosisRequest $request, ClinicalEncounter $encounter): RedirectResponse
    {
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
