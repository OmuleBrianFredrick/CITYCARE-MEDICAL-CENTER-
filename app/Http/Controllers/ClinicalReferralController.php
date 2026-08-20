<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClinicalReferralRequest;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalReferral;
use App\Services\ClinicalReferralService;
use Illuminate\Http\RedirectResponse;

class ClinicalReferralController extends Controller
{
    public function __construct(private readonly ClinicalReferralService $referrals)
    {
    }

    public function store(StoreClinicalReferralRequest $request, ClinicalEncounter $encounter): RedirectResponse
    {
        $referral = $this->referrals->create($encounter, $request->user(), $request->validated());

        return back()->with('status', "Referral #{$referral->id} created successfully.");
    }

    public function accept(ClinicalReferral $referral): RedirectResponse
    {
        $this->referrals->accept($referral);

        return back()->with('status', 'Referral accepted successfully.');
    }

    public function complete(ClinicalReferral $referral): RedirectResponse
    {
        $this->referrals->complete($referral);

        return back()->with('status', 'Referral completed successfully.');
    }

    public function cancel(ClinicalReferral $referral): RedirectResponse
    {
        $this->referrals->cancel($referral);

        return back()->with('status', 'Referral cancelled successfully.');
    }
}
