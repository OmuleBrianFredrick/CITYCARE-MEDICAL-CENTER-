<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClinicalReferralRequest;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalReferral;
use App\Services\ClinicalReferralService;
use App\Services\FacilityAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClinicalReferralController extends Controller
{
    public function __construct(
        private readonly ClinicalReferralService $referrals,
        private readonly FacilityAccessService $facilityAccess,
    ) {}

    public function store(StoreClinicalReferralRequest $request, ClinicalEncounter $encounter): RedirectResponse
    {
        $this->facilityAccess->assertEncounterAccessible($request->user(), $encounter);
        $referral = $this->referrals->create($encounter, $request->user(), $request->validated());

        return back()->with('status', "Referral #{$referral->id} created successfully.");
    }

    public function accept(Request $request, ClinicalReferral $referral): RedirectResponse
    {
        $referral->loadMissing('encounter');
        $this->facilityAccess->assertEncounterAccessible($request->user(), $referral->encounter);
        $this->referrals->accept($referral);

        return back()->with('status', 'Referral accepted successfully.');
    }

    public function complete(Request $request, ClinicalReferral $referral): RedirectResponse
    {
        $referral->loadMissing('encounter');
        $this->facilityAccess->assertEncounterAccessible($request->user(), $referral->encounter);
        $this->referrals->complete($referral);

        return back()->with('status', 'Referral completed successfully.');
    }

    public function cancel(Request $request, ClinicalReferral $referral): RedirectResponse
    {
        $referral->loadMissing('encounter');
        $this->facilityAccess->assertEncounterAccessible($request->user(), $referral->encounter);
        $this->referrals->cancel($referral);

        return back()->with('status', 'Referral cancelled successfully.');
    }
}
