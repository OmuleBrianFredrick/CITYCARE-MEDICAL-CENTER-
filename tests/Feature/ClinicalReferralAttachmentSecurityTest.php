<?php

namespace Tests\Feature;

use App\Models\ClinicalReferral;
use App\Models\User;
use App\Services\ClinicalReferralAttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClinicalReferralAttachmentSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinical_referral_attachments_are_stored_on_private_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->seed();
        $staff = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => true,
        ]);
        $referral = ClinicalReferral::factory()->create();
        $file = UploadedFile::fake()->create('referral-note.pdf', 20, 'application/pdf');

        $attachment = app(ClinicalReferralAttachmentService::class)->upload($referral, $staff, $file);

        $this->assertSame('local', $attachment->disk);
        Storage::disk('local')->assertExists($attachment->file_path);
        Storage::disk('public')->assertMissing($attachment->file_path);
    }
}
