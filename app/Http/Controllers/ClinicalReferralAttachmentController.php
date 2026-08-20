<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClinicalReferralAttachmentRequest;
use App\Models\ClinicalReferral;
use App\Models\ClinicalReferralAttachment;
use App\Services\ClinicalReferralAttachmentService;
use Illuminate\Http\RedirectResponse;

class ClinicalReferralAttachmentController extends Controller
{
    public function __construct(private readonly ClinicalReferralAttachmentService $attachments)
    {
    }

    public function store(StoreClinicalReferralAttachmentRequest $request, ClinicalReferral $referral): RedirectResponse
    {
        $attachment = $this->attachments->upload($referral, $request->user(), $request->file('file'));

        return back()->with('status', "Referral attachment #{$attachment->id} uploaded successfully.");
    }

    public function destroy(ClinicalReferralAttachment $attachment): RedirectResponse
    {
        $this->attachments->delete($attachment);

        return back()->with('status', 'Referral attachment deleted successfully.');
    }
}
