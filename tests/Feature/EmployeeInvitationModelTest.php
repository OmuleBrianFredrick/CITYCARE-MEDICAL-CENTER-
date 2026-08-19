<?php

namespace Tests\Feature;

use App\Models\EmployeeInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EmployeeInvitationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_invitation_tracks_inviter_and_employee(): void
    {
        $inviter = User::factory()->create(['user_type' => 'staff']);
        $employee = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => false,
        ]);

        $invitation = EmployeeInvitation::create([
            'user_id' => $employee->id,
            'invited_by' => $inviter->id,
            'email' => $employee->email,
            'name' => $employee->name,
            'role_slug' => 'doctor',
            'token_hash' => hash('sha256', 'test-invitation-token'),
            'status' => EmployeeInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(2),
        ]);

        $this->assertTrue($invitation->isPending());
        $this->assertFalse($invitation->isExpired());
        $this->assertFalse($invitation->isRevoked());
        $this->assertFalse($invitation->isAccepted());
        $this->assertTrue($invitation->user->is($employee));
        $this->assertTrue($invitation->inviter->is($inviter));
    }

    public function test_expired_invitation_is_not_pending(): void
    {
        $invitation = EmployeeInvitation::create([
            'user_id' => User::factory()->create(['user_type' => 'staff'])->id,
            'invited_by' => User::factory()->create(['user_type' => 'staff'])->id,
            'email' => 'expired@citycare.test',
            'name' => 'Expired Staff',
            'role_slug' => 'nurse',
            'token_hash' => hash('sha256', 'expired-token'),
            'status' => EmployeeInvitation::STATUS_PENDING,
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        $this->assertTrue($invitation->isExpired());
        $this->assertFalse($invitation->isPending());
    }

    public function test_revoked_and_accepted_states_are_explicit(): void
    {
        $invitation = EmployeeInvitation::create([
            'user_id' => User::factory()->create(['user_type' => 'staff'])->id,
            'invited_by' => User::factory()->create(['user_type' => 'staff'])->id,
            'email' => 'state@citycare.test',
            'name' => 'State Staff',
            'role_slug' => 'receptionist',
            'token_hash' => hash('sha256', 'state-token'),
            'status' => EmployeeInvitation::STATUS_REVOKED,
            'expires_at' => now()->addDay(),
            'revoked_at' => now(),
        ]);

        $this->assertTrue($invitation->isRevoked());
        $this->assertFalse($invitation->isPending());

        $invitation->update([
            'status' => EmployeeInvitation::STATUS_ACCEPTED,
            'accepted_at' => now(),
            'revoked_at' => null,
        ]);

        $this->assertTrue($invitation->isAccepted());
        $this->assertFalse($invitation->isPending());
    }
}
