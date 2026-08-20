<?php

namespace App\Services;

use App\Models\ClinicalEncounter;
use App\Models\ClinicalReferral;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ClinicalReferralService
{
    public function create(ClinicalEncounter $encounter, User $author, array $data): ClinicalReferral
    {
        if (! $encounter->isOpen()) {
            throw ValidationException::withMessages([
                'encounter' => 'Referrals can only be created on open encounters.',
            ]);
        }

        if (! $author->isStaff() || ! $author->isActive()) {
            throw ValidationException::withMessages([
                'author_id' => 'Only active staff members can create referrals.',
            ]);
        }

        return ClinicalReferral::create([
            'encounter_id' => $encounter->id,
            'author_id' => $author->id,
            'referred_to' => $data['referred_to'],
            'reason' => $data['reason'],
            'priority' => $data['priority'] ?? ClinicalReferral::PRIORITY_ROUTINE,
            'status' => ClinicalReferral::STATUS_PENDING,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function complete(ClinicalReferral $referral): ClinicalReferral
    {
        if (! $referral->isPending()) {
            throw ValidationException::withMessages([
                'referral' => 'Only pending referrals can be completed.',
            ]);
        }

        $referral->forceFill([
            'status' => ClinicalReferral::STATUS_COMPLETED,
            'completed_at' => now(),
        ])->save();

        return $referral->refresh();
    }

    public function cancel(ClinicalReferral $referral): ClinicalReferral
    {
        if (! $referral->isPending()) {
            throw ValidationException::withMessages([
                'referral' => 'Only pending referrals can be cancelled.',
            ]);
        }

        $referral->forceFill([
            'status' => ClinicalReferral::STATUS_CANCELLED,
        ])->save();

        return $referral->refresh();
    }
}
