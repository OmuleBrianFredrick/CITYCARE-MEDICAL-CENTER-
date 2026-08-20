<?php

namespace Tests\Feature;

use App\Models\ClinicalReferral;
use App\Models\ClinicalReferralAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicalReferralAttachmentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_attachment_links_to_referral(): void
    {
        $referral = ClinicalReferral::factory()->create();

        $attachment = ClinicalReferralAttachment::create([
            'clinical_referral_id' => $referral->id,
            'file_name' => 'referral-summary.pdf',
            'file_path' => 'referrals/referral-summary.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);

        $this->assertTrue($attachment->referral->is($referral));
    }

    public function test_referral_exposes_attachments(): void
    {
        $referral = ClinicalReferral::factory()->create();

        ClinicalReferralAttachment::create([
            'clinical_referral_id' => $referral->id,
            'file_name' => 'specialist-note.pdf',
            'file_path' => 'referrals/specialist-note.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
        ]);

        $this->assertCount(1, $referral->fresh()->attachments);
    }
}
