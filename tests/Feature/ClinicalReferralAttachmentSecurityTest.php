<?php

namespace Tests\Feature;

use App\Models\ClinicalReferral;
use App\Models\User;
use App\Services\ClinicalReferralAttachmentService;
use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
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

    public function test_a_configured_private_disk_can_be_used_for_durable_clinical_storage(): void
    {
        Storage::fake('s3');
        config()->set('citycare.clinical_attachments_disk', 's3');

        $this->seed();
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $referral = ClinicalReferral::factory()->create();
        $file = UploadedFile::fake()->create('specialist-note.pdf', 20, 'application/pdf');

        $attachment = app(ClinicalReferralAttachmentService::class)->upload($referral, $staff, $file);

        $this->assertSame('s3', $attachment->disk);
        Storage::disk('s3')->assertExists($attachment->file_path);
    }

    public function test_public_disk_is_rejected_for_clinical_attachments(): void
    {
        Storage::fake('public');
        config()->set('citycare.clinical_attachments_disk', 'public');

        $this->seed();
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $referral = ClinicalReferral::factory()->create();
        $file = UploadedFile::fake()->create('referral-note.pdf', 20, 'application/pdf');

        $this->expectException(ValidationException::class);

        app(ClinicalReferralAttachmentService::class)->upload($referral, $staff, $file);
    }

    public function test_public_visibility_disk_alias_is_rejected_for_clinical_attachments(): void
    {
        config()->set('filesystems.disks.exposed-clinical', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/exposed-clinical'),
            'visibility' => 'public',
        ]);
        config()->set('citycare.clinical_attachments_disk', 'exposed-clinical');

        $this->seed();
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $referral = ClinicalReferral::factory()->create();
        $file = UploadedFile::fake()->create('referral-note.pdf', 20, 'application/pdf');

        $this->expectException(ValidationException::class);

        app(ClinicalReferralAttachmentService::class)->upload($referral, $staff, $file);
    }

    public function test_failed_storage_write_does_not_create_attachment_metadata(): void
    {
        config()->set('filesystems.disks.failing-clinical', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/failing-clinical'),
            'throw' => false,
        ]);
        config()->set('citycare.clinical_attachments_disk', 'failing-clinical');

        $disk = Mockery::mock(FilesystemContract::class);
        $disk->shouldReceive('putFileAs')->once()->andReturnFalse();
        Storage::set('failing-clinical', $disk);

        $this->seed();
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $referral = ClinicalReferral::factory()->create();
        $file = UploadedFile::fake()->create('referral-note.pdf', 20, 'application/pdf');

        try {
            app(ClinicalReferralAttachmentService::class)->upload($referral, $staff, $file);
            $this->fail('A failed storage write must not create attachment metadata.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }

        $this->assertDatabaseCount('clinical_referral_attachments', 0);
    }
}
