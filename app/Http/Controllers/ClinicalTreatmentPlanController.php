<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClinicalTreatmentPlanRequest;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalTreatmentPlan;
use App\Services\ClinicalTreatmentPlanService;
use App\Services\FacilityAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClinicalTreatmentPlanController extends Controller
{
    public function __construct(
        private readonly ClinicalTreatmentPlanService $plans,
        private readonly FacilityAccessService $facilityAccess,
    ) {}

    public function store(StoreClinicalTreatmentPlanRequest $request, ClinicalEncounter $encounter): RedirectResponse
    {
        $this->facilityAccess->assertEncounterAccessible($request->user(), $encounter);
        $plan = $this->plans->create($encounter, $request->user(), $request->validated());

        return back()->with('status', "Treatment plan #{$plan->id} created successfully.");
    }

    public function complete(Request $request, ClinicalTreatmentPlan $plan): RedirectResponse
    {
        $plan->loadMissing('encounter');
        $this->facilityAccess->assertEncounterAccessible($request->user(), $plan->encounter);
        $this->plans->complete($plan);

        return back()->with('status', 'Treatment plan completed successfully.');
    }

    public function cancel(Request $request, ClinicalTreatmentPlan $plan): RedirectResponse
    {
        $plan->loadMissing('encounter');
        $this->facilityAccess->assertEncounterAccessible($request->user(), $plan->encounter);
        $this->plans->cancel($plan);

        return back()->with('status', 'Treatment plan cancelled successfully.');
    }
}
