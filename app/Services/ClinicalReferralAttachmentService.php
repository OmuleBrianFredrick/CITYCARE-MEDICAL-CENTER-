<?php

namespace App\Services;

use App\Models\ClinicalReferral;
use App\Models\ClinicalReferralAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ClinicalReferralAttachmentService
{
    public function upload(ClinicalReferral $referral, User $uploader, UploadedFile $file): ClinicalReferralAttachment
    {
        if (! $uploader->isStaff() || ! $uploader->isActive()) {
            throw ValidationException::withMessages([
                'uploaded_by' => 'Only active staff members can upload referral attachments.',
            ]);
        }

        $disk = (string) config('citycare.clinical_attachments_disk', 'local');
        $diskConfiguration = config('filesystems.disks.'.$disk);

        if ($disk === '' || ! is_array($diskConfiguration) || $disk === 'public' || ($diskConfiguration['visibility'] ?? null) === 'public') {
            throw ValidationException::withMessages([
                'file' => 'Clinical referral storage must use a configured private filesystem disk.',
            ]);
        }

        $path = $file->store('clinical-referrals/'.$referral->id, $disk);

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'file' => 'The referral attachment could not be stored. Please try again or contact an administrator.',
            ]);
        }

        try {
            return ClinicalReferralAttachment::create([
                'clinical_referral_id' => $referral->id,
                'uploaded_by' => $uploader->id,
                'disk' => $disk,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }

    public function delete(ClinicalReferralAttachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->file_path);
        $attachment->delete();
    }
}
