<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Facility;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\StaffAdministrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeInvitationAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_invited_employee_can_activate_once_and_sign_in(): void
    {
        [$staff, $invitation, $plainToken] = $this->inviteStaff();

        $this->get(route('staff-invitations.accept.create', $plainToken))
            ->assertOk()
            ->assertSee('Create your password')
            ->assertSee($staff->email);

        $this->post(route('staff-invitations.accept.store'), [
            'token' => $plainToken,
            'email' => strtoupper($staff->email),
            'password' => 'SecureStaffPassword123!',
            'password_confirmation' => 'SecureStaffPassword123!',
        ])->assertRedirect(route('login'));

        $staff->refresh();
        $this->assertTrue($staff->isActive());
        $this->assertTrue(Hash::check('SecureStaffPassword123!', $staff->password));
        $this->assertNotNull($staff->email_verified_at);
        $this->assertSame('active', $staff->staffProfile->employment_status);
        $this->assertTrue($invitation->fresh()->isAccepted());
        $this->assertDatabaseHas('audit_events', ['event_type' => 'staff.invitation.accepted', 'auditable_id' => $invitation->id]);

        $this->get(route('staff-invitations.accept.create', $plainToken))->assertNotFound();

        $this->post(route('login.store'), [
            'email' => $staff->email,
            'password' => 'SecureStaffPassword123!',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_expired_invitation_cannot_be_previewed_or_accepted(): void
    {
        [$staff, $invitation, $plainToken] = $this->inviteStaff();
        $invitation->update(['expires_at' => now()->subMinute()]);

        $this->get(route('staff-invitations.accept.create', $plainToken))->assertNotFound();

        $this->from(route('staff-invitations.accept.create', str_repeat('A', 64)))
            ->post(route('staff-invitations.accept.store'), [
                'token' => $plainToken,
                'email' => $staff->email,
                'password' => 'SecureStaffPassword123!',
                'password_confirmation' => 'SecureStaffPassword123!',
            ])
            ->assertSessionHasErrors('email');

        $this->assertFalse($staff->fresh()->isActive());
    }

    public function test_wrong_email_and_short_password_are_rejected_without_consuming_link(): void
    {
        [$staff, $invitation, $plainToken] = $this->inviteStaff();

        $this->post(route('staff-invitations.accept.store'), [
            'token' => $plainToken,
            'email' => 'wrong@citycare.test',
            'password' => 'SecureStaffPassword123!',
            'password_confirmation' => 'SecureStaffPassword123!',
        ])->assertSessionHasErrors('email');

        $this->post(route('staff-invitations.accept.store'), [
            'token' => $plainToken,
            'email' => $staff->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($invitation->fresh()->isPending());
        $this->assertFalse($staff->fresh()->isActive());
    }

    private function inviteStaff(): array
    {
        $facility = Facility::query()->firstOrFail();
        $department = Department::query()->where('facility_id', $facility->id)->firstOrFail();
        $administrator = $this->staffAt($facility, 'administrator');
        $doctorRole = Role::query()->where('slug', 'doctor')->firstOrFail();

        return app(StaffAdministrationService::class)->invite($administrator, [
            'facility_id' => $facility->id,
            'name' => 'Invited Employee',
            'email' => 'invited.employee@citycare.test',
            'department_id' => $department->id,
            'roles' => [$doctorRole->id],
        ]);
    }

    private function staffAt(Facility $facility, string $roleSlug): User
    {
        $department = Department::query()->where('facility_id', $facility->id)->firstOrFail();
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->sync([Role::query()->where('slug', $roleSlug)->valueOrFail('id')]);
        StaffProfile::create([
            'user_id' => $staff->id,
            'department_id' => $department->id,
            'employee_number' => 'INVITER-'.$staff->id,
            'employment_status' => 'active',
        ]);

        return $staff;
    }
}
