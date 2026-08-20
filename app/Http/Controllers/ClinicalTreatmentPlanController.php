<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClinicalTreatmentPlanRequest;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalTreatmentPlan;
use App\Services\ClinicalTreatmentPlanService;
use Illuminate\Http\RedirectResponse;

class ClinicalTreatmentPlanController extends Controller
{
    public function __construct(private readonly ClinicalTreatmentPlanService $plans)
    {
    }

    public function store(StoreClinicalTreatmentPlanRequest $request, ClinicalEncounter $encounter): RedirectResponse
    {
        $plan = $this->plans->create($encounter, $request->user(), $request->validated());

        return back()->with('status', "Treatment plan #{$plan->id} created successfully.");
    }

    public function complete(ClinicalTreatmentPlan $plan): RedirectResponse
    {
        $this->plans->complete($plan);

        return back()->with('status', 'Treatment plan completed successfully.');
    }

    public function cancel(ClinicalTreatmentPlan $plan): RedirectResponse
    {
        $this->plans->cancel($plan);

        return back()->with('status', 'Treatment plan cancelled successfully.');
    }
}
