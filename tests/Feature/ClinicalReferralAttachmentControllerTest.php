<?php

namespace Tests\Feature;

use App\Models\ClinicalReferral;
use App\Models\ClinicalReferralAttachment;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Role;
use App\Models\StaffProfile;
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
        Storage::fake('local');
        $this->seed();

        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->sync([Role::where('slug', 'doctor')->firstOrFail()->id]);
        $referral = ClinicalReferral::factory()->create();
        $this->assignToReferralFacility($staff, $referral);

        $response = $this->actingAs($staff)->from('/encounters/'.$referral->encounter_id)->withSession(['_token' => 'test-token'])->post(route('encounters.referrals.attachments.store', $referral), [
            '_token' => 'test-token',
            'file' => UploadedFile::fake()->create('referral-note.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('clinical_referral_attachments', [
            'clinical_referral_id' => $referral->id,
            'uploaded_by' => $staff->id,
            'disk' => 'local',
            'file_name' => 'referral-note.pdf',
        ]);
        $attachment = ClinicalReferralAttachment::query()->where('clinical_referral_id', $referral->id)->sole();
        Storage::disk('local')->assertExists($attachment->file_path);
    }

    public function test_attachment_upload_requires_permission(): void
    {
        Storage::fake('local');
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
        Storage::fake('local');
        $this->seed();

        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->sync([Role::where('slug', 'doctor')->firstOrFail()->id]);
        $referral = ClinicalReferral::factory()->create();
        $this->assignToReferralFacility($staff, $referral);
        $attachment = ClinicalReferralAttachment::factory()->create([
            'clinical_referral_id' => $referral->id,
            'uploaded_by' => $staff->id,
            'disk' => 'local',
        ]);
        Storage::disk('local')->put($attachment->file_path, 'protected referral content');

        $response = $this->actingAs($staff)->from('/encounters/'.$referral->encounter_id)->withSession(['_token' => 'test-token'])->delete(route('encounters.referrals.attachments.destroy', $attachment), [
            '_token' => 'test-token',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('clinical_referral_attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($attachment->file_path);
    }

    public function test_authorized_staff_can_download_a_private_attachment_from_their_facility(): void
    {
        Storage::fake('local');
        $this->seed();

        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->sync([Role::where('slug', 'doctor')->firstOrFail()->id]);
        $referral = ClinicalReferral::factory()->create();
        $this->assignToReferralFacility($staff, $referral);
        $attachment = ClinicalReferralAttachment::factory()->create([
            'clinical_referral_id' => $referral->id,
            'uploaded_by' => $staff->id,
            'disk' => 'local',
            'file_path' => 'clinical-referrals/'.$referral->id.'/specialist-note.pdf',
            'file_name' => 'specialist-note.pdf',
        ]);
        Storage::disk('local')->put($attachment->file_path, 'protected referral content');

        $response = $this->actingAs($staff)
            ->get(route('encounters.referrals.attachments.download', $attachment));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('x-content-type-options', 'nosniff');
        $this->assertStringContainsString('specialist-note.pdf', (string) $response->headers->get('content-disposition'));
        $this->assertSame('protected referral content', $response->streamedContent());
    }

    public function test_staff_cannot_download_an_attachment_from_another_facility(): void
    {
        Storage::fake('local');
        $this->seed();

        $referral = ClinicalReferral::factory()->create();
        $attachment = ClinicalReferralAttachment::factory()->create([
            'clinical_referral_id' => $referral->id,
            'disk' => 'local',
        ]);
        Storage::disk('local')->put($attachment->file_path, 'protected referral content');

        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->sync([Role::where('slug', 'doctor')->firstOrFail()->id]);
        $foreignFacility = Facility::factory()->create();
        $foreignDepartment = Department::factory()->create(['facility_id' => $foreignFacility->id]);
        StaffProfile::create([
            'user_id' => $staff->id,
            'department_id' => $foreignDepartment->id,
            'employee_number' => 'REFERRAL-FOREIGN-'.$staff->id,
            'employment_status' => 'active',
        ]);

        $this->actingAs($staff)
            ->get(route('encounters.referrals.attachments.download', $attachment))
            ->assertForbidden();
    }

    private function assignToReferralFacility(User $staff, ClinicalReferral $referral): void
    {
        $referral->loadMissing('encounter');
        $department = Department::factory()->create([
            'facility_id' => $referral->encounter->facility_id,
        ]);
        StaffProfile::create([
            'user_id' => $staff->id,
            'department_id' => $department->id,
            'employee_number' => 'REFERRAL-'.$staff->id,
            'employment_status' => 'active',
        ]);
        $staff->unsetRelation('staffProfile');
    }
}
