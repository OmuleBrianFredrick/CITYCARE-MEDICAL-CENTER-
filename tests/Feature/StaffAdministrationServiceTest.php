<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\StaffAdministrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StaffAdministrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private StaffAdministrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->service = app(StaffAdministrationService::class);
    }

    public function test_facility_manager_only_queries_staff_in_their_assigned_facility(): void
    {
        $facility = Facility::query()->firstOrFail();
        $otherFacility = Facility::factory()->create();
        $manager = $this->staffAt($facility, 'administrator', 'Local Manager');
        $local = $this->staffAt($facility, 'doctor', 'Local Doctor');
        $this->staffAt($otherFacility, 'doctor', 'Remote Doctor');

        $resolved = $this->service->facilityFor($manager);
        $staffIds = $this->service->staffQuery($manager, $resolved)->pluck('users.id');

        $this->assertTrue($resolved->is($facility));
        $this->assertTrue($staffIds->contains($local->id));
        $this->assertFalse($staffIds->contains(User::where('name', 'Remote Doctor')->value('id')));
        $this->assertCount(1, $this->service->availableFacilities($manager));

        $this->expectException(HttpException::class);
        $this->service->facilityFor($manager, $otherFacility->id);
    }

    public function test_staff_creation_is_scoped_hashed_role_bound_and_audited_without_secret_data(): void
    {
        $facility = Facility::query()->firstOrFail();
        $manager = $this->staffAt($facility, 'administrator');
        $department = Department::factory()->for($facility)->create();
        $servicePoint = ServicePoint::factory()->for($department)->create();
        $doctorRole = Role::query()->where('slug', 'doctor')->firstOrFail();

        $staff = $this->service->create($manager, [
            'facility_id' => $facility->id,
            'name' => '  New Clinician  ',
            'email' => '  NEW.CLINICIAN@CITYCARE.TEST ',
            'password' => 'LongSecurePassword123!',
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'employee_number' => 'EMP-9001',
            'job_title' => 'Medical Officer',
            'phone' => '+256700000001',
            'joined_at' => today()->toDateString(),
            'roles' => [$doctorRole->id],
            'is_active' => true,
        ]);

        $this->assertSame('New Clinician', $staff->name);
        $this->assertSame('new.clinician@citycare.test', $staff->email);
        $this->assertTrue(Hash::check('LongSecurePassword123!', $staff->password));
        $this->assertSame($department->id, $staff->staffProfile->department_id);
        $this->assertSame($servicePoint->id, $staff->staffProfile->service_point_id);
        $this->assertTrue($staff->hasRole('doctor'));

        $event = AuditEvent::query()->where('event_type', 'staff.account.created')->firstOrFail();
        $this->assertSame($manager->id, $event->actor_id);
        $this->assertSame($facility->id, $event->facility_id);
        $this->assertSame($staff->id, $event->auditable_id);
        $this->assertArrayNotHasKey('password', $event->after_values);
    }

    public function test_facility_manager_cannot_assign_or_manage_access_administration_authority(): void
    {
        $facility = Facility::query()->firstOrFail();
        $manager = $this->staffAt($facility, 'administrator');
        $department = Department::query()->where('facility_id', $facility->id)->firstOrFail();
        $accessRole = Role::query()->create([
            'name' => 'Delegated Security',
            'slug' => 'delegated-security',
            'is_system' => false,
        ]);
        $accessRole->permissions()->attach(Permission::where('slug', 'access.manage')->valueOrFail('id'));

        try {
            $this->service->create($manager, [
                'facility_id' => $facility->id,
                'name' => 'Privilege Attempt',
                'email' => 'privilege.attempt@citycare.test',
                'password' => 'LongSecurePassword123!',
                'department_id' => $department->id,
                'roles' => [$accessRole->id],
            ]);
            $this->fail('Expected privileged role assignment to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('roles', $exception->errors());
        }

        $privilegedTarget = $this->staffAt($facility, 'doctor');
        $privilegedTarget->roles()->sync([$accessRole->id]);

        $this->expectException(HttpException::class);
        $this->service->facilityForTarget($manager, $privilegedTarget);
    }

    public function test_department_and_service_point_boundaries_cannot_be_crossed(): void
    {
        $facility = Facility::query()->firstOrFail();
        $otherFacility = Facility::factory()->create();
        $manager = $this->staffAt($facility, 'administrator');
        $department = Department::query()->where('facility_id', $facility->id)->firstOrFail();
        $foreignDepartment = Department::factory()->for($otherFacility)->create();
        $foreignPoint = ServicePoint::factory()->for($foreignDepartment)->create();
        $doctorRole = Role::where('slug', 'doctor')->firstOrFail();

        try {
            $this->service->create($manager, [
                'facility_id' => $facility->id,
                'name' => 'Wrong Facility',
                'email' => 'wrong.facility@citycare.test',
                'password' => 'LongSecurePassword123!',
                'department_id' => $foreignDepartment->id,
                'roles' => [$doctorRole->id],
            ]);
            $this->fail('Expected cross-facility assignment to be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->expectException(ValidationException::class);
        $this->service->create($manager, [
            'facility_id' => $facility->id,
            'name' => 'Wrong Service Point',
            'email' => 'wrong.point@citycare.test',
            'password' => 'LongSecurePassword123!',
            'department_id' => $department->id,
            'service_point_id' => $foreignPoint->id,
            'roles' => [$doctorRole->id],
        ]);
    }

    public function test_role_and_account_state_changes_are_audited_and_self_deactivation_is_blocked(): void
    {
        $facility = Facility::query()->firstOrFail();
        $manager = $this->staffAt($facility, 'administrator');
        $target = $this->staffAt($facility, 'doctor');
        $nurseRole = Role::where('slug', 'nurse')->firstOrFail();

        $this->service->syncRoles($manager, $target, [$nurseRole->id]);
        $this->service->deactivate($manager, $target);

        $this->assertTrue($target->fresh()->hasRole('nurse'));
        $this->assertFalse($target->fresh()->isActive());
        $this->assertSame('inactive', $target->fresh()->staffProfile->employment_status);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'staff.roles.updated', 'auditable_id' => $target->id]);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'staff.account.deactivated', 'auditable_id' => $target->id]);

        $this->expectException(ValidationException::class);
        $this->service->deactivate($manager, $manager);
    }

    private function staffAt(Facility $facility, string $roleSlug, ?string $name = null): User
    {
        $department = Department::query()
            ->where('facility_id', $facility->id)
            ->first() ?? Department::factory()->for($facility)->create();
        $user = User::factory()->create([
            'name' => $name ?? fake()->name(),
            'user_type' => 'staff',
            'is_active' => true,
        ]);
        $user->staffProfile()->create([
            'department_id' => $department->id,
            'employee_number' => 'EMP-'.$user->id,
            'employment_status' => 'active',
        ]);
        $user->roles()->attach(Role::where('slug', $roleSlug)->valueOrFail('id'));

        return $user;
    }
}
