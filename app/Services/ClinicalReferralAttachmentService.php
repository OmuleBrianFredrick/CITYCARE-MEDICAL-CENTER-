<?php

namespace App\Services;

use App\Models\ClinicalReferral;
use App\Models\ClinicalReferralAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ClinicalReferralAttachmentService
{
    public function upload(ClinicalReferral $referral, User $uploader, UploadedFile $file): ClinicalReferralAttachment
    {
        if (! $uploader->isStaff() || ! $uploader->isActive()) {
            throw ValidationException::withMessages([
                'uploaded_by' => 'Only active staff members can upload referral attachments.',
            ]);
        }

        // Clinical referral attachments can contain protected health information.
        // Keep them on Laravel's private local disk rather than the public disk,
        // so possession of a predictable storage URL cannot expose patient files.
        $disk = 'local';
        $path = $file->store('clinical-referrals/'.$referral->id, $disk);

        return ClinicalReferralAttachment::create([
            'clinical_referral_id' => $referral->id,
            'uploaded_by' => $uploader->id,
            'disk' => $disk,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);
    }

    public function delete(ClinicalReferralAttachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->file_path);
        $attachment->delete();
    }
}
