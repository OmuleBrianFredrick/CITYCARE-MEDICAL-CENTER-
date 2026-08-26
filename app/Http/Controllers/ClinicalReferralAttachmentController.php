<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClinicalReferralAttachmentRequest;
use App\Models\ClinicalReferral;
use App\Models\ClinicalReferralAttachment;
use App\Services\ClinicalReferralAttachmentService;
use App\Services\FacilityAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClinicalReferralAttachmentController extends Controller
{
    public function __construct(
        private readonly ClinicalReferralAttachmentService $attachments,
        private readonly FacilityAccessService $facilityAccess,
    ) {}

    public function store(StoreClinicalReferralAttachmentRequest $request, ClinicalReferral $referral): RedirectResponse
    {
        $referral->loadMissing('encounter');
        $this->facilityAccess->assertEncounterAccessible($request->user(), $referral->encounter);
        $attachment = $this->attachments->upload($referral, $request->user(), $request->file('file'));

        return back()->with('status', "Referral attachment #{$attachment->id} uploaded successfully.");
    }

    public function destroy(Request $request, ClinicalReferralAttachment $attachment): RedirectResponse
    {
        $attachment->loadMissing('referral.encounter');
        $this->facilityAccess->assertEncounterAccessible($request->user(), $attachment->referral->encounter);
        $this->attachments->delete($attachment);

        return back()->with('status', 'Referral attachment deleted successfully.');
    }
}
