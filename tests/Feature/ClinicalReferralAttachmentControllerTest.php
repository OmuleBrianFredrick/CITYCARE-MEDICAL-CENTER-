<?php

namespace Tests\Feature;

use App\Models\ClinicalReferral;
use App\Models\ClinicalReferralAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClinicalReferralAttachmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_staff_can_upload_attachment_to_referral(): void
    {
        Storage::fake('public');
        $this->seed();

        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->sync([\App\Models\Role::where('slug', 'doctor')->firstOrFail()->id]);
        $referral = ClinicalReferral::factory()->create();

        $response = $this->actingAs($staff)->from('/encounters/'.$referral->encounter_id)->withSession(['_token' => 'test-token'])->post(route('encounters.referrals.attachments.store', $referral), [
            '_token' => 'test-token',
            'file' => UploadedFile::fake()->create('referral-note.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('clinical_referral_attachments', [
            'clinical_referral_id' => $referral->id,
            'uploaded_by' => $staff->id,
            'file_name' => 'referral-note.pdf',
        ]);
    }

    public function test_attachment_upload_requires_permission(): void
    {
        Storage::fake('public');
        $this->seed();

        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $referral = ClinicalReferral::factory()->create();

        $response = $this->actingAs($staff)->from('/encounters/'.$referral->encounter_id)->withSession(['_token' => 'test-token'])->post(route('encounters.referrals.attachments.store', $referral), [
            '_token' => 'test-token',
            'file' => UploadedFile::fake()->create('referral-note.pdf', 100, 'application/pdf'),
        ]);

        $response->assertForbidden();
    }

    public function test_attachment_delete_removes_database_record(): void
    {
        Storage::fake('public');
        $this->seed();

        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->sync([\App\Models\Role::where('slug', 'doctor')->firstOrFail()->id]);
        $referral = ClinicalReferral::factory()->create();
        $attachment = ClinicalReferralAttachment::factory()->create(['uploaded_by' => $staff->id]);

        $response = $this->actingAs($staff)->from('/encounters/'.$referral->encounter_id)->withSession(['_token' => 'test-token'])->delete(route('encounters.referrals.attachments.destroy', $attachment), [
            '_token' => 'test-token',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('clinical_referral_attachments', ['id' => $attachment->id]);
    }
}
