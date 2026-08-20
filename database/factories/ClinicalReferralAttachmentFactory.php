<?php

namespace Database\Factories;

use App\Models\ClinicalReferral;
use App\Models\ClinicalReferralAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClinicalReferralAttachment> */
class ClinicalReferralAttachmentFactory extends Factory
{
    protected $model = ClinicalReferralAttachment::class;

    public function definition(): array
    {
        return [
            'referral_id' => ClinicalReferral::factory(),
            'uploaded_by' => User::factory()->state(['user_type' => 'staff', 'is_active' => true]),
            'disk' => 'public',
            'path' => 'referrals/'.fake()->uuid().'.pdf',
            'original_name' => 'referral-document.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1024, 500000),
        ];
    }
}
