<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\EmployeeInvitation;
use App\Models\Facility;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAdministrationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_facility_administrator_can_open_workspace_and_issue_staff_invitation(): void
    {
        $facility = Facility::query()->firstOrFail();
        $department = Department::query()->where('facility_id', $facility->id)->firstOrFail();
        $administrator = $this->staffAt($facility, 'administrator');
        $doctorRole = Role::query()->where('slug', 'doctor')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertSee('Staff administration')
            ->assertSee('Invite staff member');

        $response = $this->actingAs($administrator)->post(route('staff.store'), [
            'facility_id' => $facility->id,
            'name' => 'Invited Clinician',
            'email' => 'invited.clinician@citycare.test',
            'department_id' => $department->id,
            'employee_number' => 'INV-1001',
            'job_title' => 'Medical Officer',
            'roles' => [$doctorRole->id],
        ]);

        $staff = User::query()->where('email', 'invited.clinician@citycare.test')->firstOrFail();
        $invitation = EmployeeInvitation::query()->where('user_id', $staff->id)->sole();

        $response
            ->assertRedirect(route('staff.edit', ['staff' => $staff, 'facility_id' => $facility->id]))
            ->assertSessionHas('invitation_url');
        $this->assertFalse($staff->isActive());
        $this->assertNull($staff->password);
        $this->assertSame('pending', $staff->staffProfile->employment_status);
        $this->assertSame($department->id, $staff->staffProfile->department_id);
        $this->assertTrue($staff->hasRole('doctor'));
        $this->assertTrue($invitation->isPending());
        $this->assertDatabaseHas('audit_events', ['event_type' => 'staff.account.invited', 'auditable_id' => $staff->id]);
    }

    public function test_facility_administrator_cannot_browse_or_edit_foreign_staff(): void
    {
        $facility = Facility::query()->firstOrFail();
        $otherFacility = Facility::factory()->create();
        $administrator = $this->staffAt($facility, 'administrator');
        $foreignStaff = $this->staffAt($otherFacility, 'doctor');

        $this->actingAs($administrator)
            ->get(route('staff.index', ['facility_id' => $otherFacility->id]))
            ->assertForbidden();

        $this->actingAs($administrator)
            ->get(route('staff.edit', $foreignStaff))
            ->assertForbidden();
    }

    public function test_staff_without_management_permission_cannot_open_administration(): void
    {
        $facility = Facility::query()->firstOrFail();
        $doctor = $this->staffAt($facility, 'doctor');

        $this->actingAs($doctor)->get(route('staff.index'))->assertForbidden();
        $this->actingAs($doctor)->get(route('staff.create'))->assertForbidden();
    }

    public function test_pending_invitation_can_be_reissued_and_revoked(): void
    {
        $facility = Facility::query()->firstOrFail();
        $department = Department::query()->where('facility_id', $facility->id)->firstOrFail();
        $administrator = $this->staffAt($facility, 'administrator');
        $doctorRole = Role::query()->where('slug', 'doctor')->firstOrFail();

        $this->actingAs($administrator)->post(route('staff.store'), [
            'facility_id' => $facility->id,
            'name' => 'Pending Doctor',
            'email' => 'pending.doctor@citycare.test',
            'department_id' => $department->id,
            'roles' => [$doctorRole->id],
        ])->assertRedirect();

        $staff = User::query()->where('email', 'pending.doctor@citycare.test')->firstOrFail();
        $firstInvitation = EmployeeInvitation::query()->where('user_id', $staff->id)->sole();

        $this->actingAs($administrator)
            ->post(route('staff.invitations.reissue', $staff))
            ->assertRedirect(route('staff.edit', $staff))
            ->assertSessionHas('invitation_url');

        $this->assertTrue($firstInvitation->fresh()->isRevoked());
        $secondInvitation = EmployeeInvitation::query()->where('user_id', $staff->id)->latest('id')->firstOrFail();
        $this->assertTrue($secondInvitation->isPending());

        $this->actingAs($administrator)
            ->delete(route('staff.invitations.revoke', [$staff, $secondInvitation]))
            ->assertRedirect(route('staff.edit', $staff));

        $this->assertTrue($secondInvitation->fresh()->isRevoked());
    }

    private function staffAt(Facility $facility, string $roleSlug): User
    {
        $department = Department::query()->where('facility_id', $facility->id)->first()
            ?? Department::factory()->create(['facility_id' => $facility->id]);
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->sync([Role::query()->where('slug', $roleSlug)->valueOrFail('id')]);
        StaffProfile::create([
            'user_id' => $staff->id,
            'department_id' => $department->id,
            'employee_number' => 'HTTP-STAFF-'.$staff->id,
            'employment_status' => 'active',
        ]);

        return $staff;
    }
}
