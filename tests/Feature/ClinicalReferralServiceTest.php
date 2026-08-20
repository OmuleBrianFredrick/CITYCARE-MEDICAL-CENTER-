<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\ClinicalReferral;
use App\Models\User;
use App\Services\ClinicalReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClinicalReferralServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_staff_can_create_referral_on_open_encounter(): void
    {
        $service = app(ClinicalReferralService::class);
        $referrer = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => true,
            'password' => Hash::make('Password123!'),
        ]);
        $encounter = ClinicalEncounter::factory()->create([
            'status' => ClinicalEncounter::STATUS_OPEN,
        ]);

        $referral = $service->create($encounter, $referrer, [
            'referred_to_department' => 'Cardiology',
            'reason' => 'Further specialist assessment.',
            'priority' => 'routine',
        ]);

        $this->assertSame($encounter->id, $referral->encounter_id);
        $this->assertSame($referrer->id, $referral->referrer_id);
        $this->assertSame(ClinicalReferral::STATUS_PENDING, $referral->status);
    }

    public function test_closed_encounter_rejects_new_referral(): void
    {
        $service = app(ClinicalReferralService::class);
        $referrer = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $encounter = ClinicalEncounter::factory()->create([
            'status' => ClinicalEncounter::STATUS_CLOSED,
        ]);

        $this->expectException(ValidationException::class);
        $service->create($encounter, $referrer, [
            'referred_to_department' => 'Cardiology',
            'reason' => 'Further review.',
            'priority' => 'routine',
        ]);
    }

    public function test_inactive_staff_cannot_create_referral(): void
    {
        $service = app(ClinicalReferralService::class);
        $referrer = User::factory()->create(['user_type' => 'staff', 'is_active' => false]);
        $encounter = ClinicalEncounter::factory()->create();

        $this->expectException(ValidationException::class);
        $service->create($encounter, $referrer, [
            'referred_to_department' => 'Cardiology',
            'reason' => 'Further review.',
        ]);
    }

    public function test_referral_status_can_progress_and_cancel(): void
    {
        $service = app(ClinicalReferralService::class);
        $referral = ClinicalReferral::factory()->create(['status' => ClinicalReferral::STATUS_PENDING]);

        $accepted = $service->accept($referral);
        $this->assertSame(ClinicalReferral::STATUS_ACCEPTED, $accepted->status);

        $completed = $service->complete($accepted);
        $this->assertSame(ClinicalReferral::STATUS_COMPLETED, $completed->status);

        $another = ClinicalReferral::factory()->create(['status' => ClinicalReferral::STATUS_PENDING]);
        $cancelled = $service->cancel($another);
        $this->assertSame(ClinicalReferral::STATUS_CANCELLED, $cancelled->status);
    }
}
