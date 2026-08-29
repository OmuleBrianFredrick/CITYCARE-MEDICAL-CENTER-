<?php

namespace Tests\Feature;

use App\Models\EmployeeInvitation;
use App\Models\Role;
use App\Models\User;
use App\Services\EmployeeInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EmployeeInvitationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_authorized_administrator_can_create_pending_staff_invitation(): void
    {
        $admin = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => true,
        ]);
        $admin->roles()->attach(Role::where('slug', 'administrator')->firstOrFail());

        [$invitation, $plainToken] = app(EmployeeInvitationService::class)->create(
            $admin,
            'Dr. Test Doctor',
            'doctor.invited@citycare.test',
            'doctor',
        );

        $this->assertTrue(is_string($plainToken));
        $this->assertSame(64, strlen($plainToken));
        $this->assertNotSame($plainToken, $invitation->token_hash);
        $this->assertSame(hash('sha256', $plainToken), $invitation->token_hash);
        $this->assertSame(EmployeeInvitation::STATUS_PENDING, $invitation->status);
        $this->assertFalse($invitation->user->isActive());
        $this->assertTrue($invitation->user->isStaff());
        $this->assertTrue($invitation->user->hasRole('doctor'));
        $this->assertSame('pending', $invitation->user->staffProfile->employment_status);
        $this->assertNotNull($invitation->expires_at);
    }

    public function test_non_staff_cannot_issue_staff_invitation(): void
    {
        $patient = User::factory()->create([
            'user_type' => 'patient',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(EmployeeInvitationService::class)->create(
            $patient,
            'Unauthorized',
            'unauthorized@citycare.test',
            'doctor',
        );
    }

    public function test_staff_without_staff_manage_permission_cannot_issue_invitation(): void
    {
        $doctor = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => true,
        ]);
        $doctor->roles()->attach(Role::where('slug', 'doctor')->firstOrFail());

        $this->expectException(ValidationException::class);

        app(EmployeeInvitationService::class)->create(
            $doctor,
            'Unauthorized Staff',
            'unauthorized-staff@citycare.test',
            'nurse',
        );
    }

    public function test_only_super_admin_can_invite_super_admin(): void
    {
        $admin = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => true,
        ]);
        $admin->roles()->attach(Role::where('slug', 'administrator')->firstOrFail());

        $this->expectException(ValidationException::class);

        app(EmployeeInvitationService::class)->create(
            $admin,
            'Second Super Admin',
            'super2@citycare.test',
            'super-admin',
        );
    }

    public function test_active_existing_account_cannot_be_invited_again(): void
    {
        $admin = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => true,
        ]);
        $admin->roles()->attach(Role::where('slug', 'administrator')->firstOrFail());

        User::factory()->create([
            'email' => 'already@citycare.test',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(EmployeeInvitationService::class)->create(
            $admin,
            'Already Active',
            'already@citycare.test',
            'doctor',
        );
    }

    public function test_duplicate_live_pending_invitation_is_rejected(): void
    {
        $admin = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => true,
        ]);
        $admin->roles()->attach(Role::where('slug', 'administrator')->firstOrFail());

        app(EmployeeInvitationService::class)->create(
            $admin,
            'Pending Staff',
            'pending@citycare.test',
            'nurse',
        );

        $this->expectException(ValidationException::class);

        app(EmployeeInvitationService::class)->create(
            $admin,
            'Pending Staff Again',
            'pending@citycare.test',
            'nurse',
        );
    }
}
