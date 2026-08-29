<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AppointmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_authorized_staff_can_view_appointment_workspace_and_form(): void
    {
        $staff = $this->staffWithRole('receptionist');
        $context = $this->appointmentContext();

        $this->actingAs($staff)->get(route('appointments.index'))->assertOk();
        $this->actingAs($staff)->get(route('appointments.create'))
            ->assertOk()
            ->assertSee('Search patient by name, MRN, phone, or national ID')
            ->assertDontSee($context['patient']->medical_record_number);
    }

    public function test_receptionist_can_continue_from_a_patient_record_to_a_preselected_appointment_form(): void
    {
        $staff = $this->staffWithRole('receptionist');
        $context = $this->appointmentContext();

        $this->actingAs($staff)
            ->get(route('appointments.create', ['patient_id' => $context['patient']->id]))
            ->assertOk()
            ->assertSee($context['patient']->medical_record_number)
            ->assertSee('<option value="'.$context['patient']->id.'" selected>', false);
    }

    public function test_staff_can_schedule_appointment_through_http_workflow(): void
    {
        $staff = $this->staffWithRole('receptionist');
        $context = $this->appointmentContext();

        $response = $this->actingAs($staff)->post(route('appointments.store'), [
            'facility_id' => $context['facility']->id,
            'department_id' => $context['department']->id,
            'service_point_id' => $context['servicePoint']->id,
            'patient_id' => $context['patient']->id,
            'provider_id' => $context['provider']->id,
            'scheduled_start' => now()->addDay()->setTime(9, 0)->format('Y-m-d H:i'),
            'scheduled_end' => now()->addDay()->setTime(9, 30)->format('Y-m-d H:i'),
            'reason' => 'Routine consultation',
        ]);

        $response->assertRedirect(route('appointments.index'));
        $appointment = Appointment::query()->where('patient_id', $context['patient']->id)->latest('id')->firstOrFail();
        $this->assertSame(Appointment::STATUS_SCHEDULED, $appointment->status);
        $this->assertSame($staff->id, $appointment->created_by);
        $this->assertStringStartsWith('APT-', $appointment->appointment_number);
    }

    public function test_staff_cannot_schedule_an_appointment_for_a_patient_from_another_facility(): void
    {
        $staff = $this->staffWithRole('receptionist');
        $context = $this->appointmentContext();
        $otherFacility = Facility::factory()->create();
        $otherPatient = Patient::factory()->create(['facility_id' => $otherFacility->id]);

        $this->actingAs($staff)
            ->from(route('appointments.create'))
            ->post(route('appointments.store'), [
                'facility_id' => $context['facility']->id,
                'department_id' => $context['department']->id,
                'service_point_id' => $context['servicePoint']->id,
                'patient_id' => $otherPatient->id,
                'scheduled_start' => now()->addDay()->setTime(9, 0)->format('Y-m-d H:i'),
                'scheduled_end' => now()->addDay()->setTime(9, 30)->format('Y-m-d H:i'),
            ])
            ->assertRedirect(route('appointments.create'))
            ->assertSessionHasErrors('patient_id');

        $this->assertDatabaseMissing('appointments', ['patient_id' => $otherPatient->id]);
    }

    public function test_appointment_lifecycle_can_check_in_complete_and_cancel(): void
    {
        $staff = $this->staffWithRole('receptionist');
        $context = $this->appointmentContext();
        $appointment = Appointment::factory()->create([
            'facility_id' => $context['facility']->id,
            'department_id' => $context['department']->id,
            'service_point_id' => $context['servicePoint']->id,
            'patient_id' => $context['patient']->id,
            'provider_id' => $context['provider']->id,
            'scheduled_start' => now()->addDay()->setTime(10, 0),
            'scheduled_end' => now()->addDay()->setTime(10, 30),
        ]);

        $this->actingAs($staff)->post(route('appointments.check-in', $appointment))->assertRedirect();
        $appointment->refresh();
        $this->assertSame(Appointment::STATUS_CHECKED_IN, $appointment->status);
        $this->assertNotNull($appointment->checked_in_at);

        $this->actingAs($staff)->post(route('appointments.complete', $appointment))->assertRedirect();
        $appointment->refresh();
        $this->assertSame(Appointment::STATUS_COMPLETED, $appointment->status);
        $this->assertNotNull($appointment->completed_at);

        $cancelled = Appointment::factory()->create([
            'facility_id' => $context['facility']->id,
            'department_id' => $context['department']->id,
            'service_point_id' => $context['servicePoint']->id,
            'patient_id' => $context['patient']->id,
            'provider_id' => $context['provider']->id,
            'scheduled_start' => now()->addDay()->setTime(11, 0),
            'scheduled_end' => now()->addDay()->setTime(11, 30),
        ]);

        $this->actingAs($staff)->post(route('appointments.cancel', $cancelled))->assertRedirect();
        $cancelled->refresh();
        $this->assertSame(Appointment::STATUS_CANCELLED, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_user_without_appointment_permission_cannot_manage_appointments(): void
    {
        $staff = $this->staffWithRole('laboratory');
        $this->appointmentContext();

        $this->actingAs($staff)->get(route('appointments.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('appointments.create'))->assertForbidden();
    }

    public function test_appointment_workspace_can_filter_by_status_and_patient_search(): void
    {
        $staff = $this->staffWithRole('receptionist');
        $context = $this->appointmentContext();
        $appointment = Appointment::factory()->create([
            'facility_id' => $context['facility']->id,
            'department_id' => $context['department']->id,
            'service_point_id' => $context['servicePoint']->id,
            'patient_id' => $context['patient']->id,
            'provider_id' => $context['provider']->id,
            'appointment_number' => 'APT-SEARCH-001',
        ]);

        $this->actingAs($staff)->get(route('appointments.index', ['status' => Appointment::STATUS_SCHEDULED]))
            ->assertOk()->assertSee($appointment->appointment_number);
        $this->actingAs($staff)->get(route('appointments.index', ['search' => $context['patient']->medical_record_number]))
            ->assertOk()->assertSee($appointment->appointment_number);
    }

    public function test_appointment_workspace_and_lifecycle_actions_are_facility_scoped(): void
    {
        $staff = $this->staffWithRole('receptionist');
        $otherFacility = Facility::factory()->create(['is_active' => true]);
        $otherDepartment = Department::factory()->create(['facility_id' => $otherFacility->id]);
        $otherServicePoint = ServicePoint::factory()->create(['department_id' => $otherDepartment->id]);
        $otherPatient = Patient::factory()->create(['facility_id' => $otherFacility->id]);
        $scheduled = Appointment::factory()->create([
            'facility_id' => $otherFacility->id,
            'department_id' => $otherDepartment->id,
            'service_point_id' => $otherServicePoint->id,
            'patient_id' => $otherPatient->id,
            'appointment_number' => 'APT-HIDDEN-FACILITY',
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
        $checkedIn = Appointment::factory()->create([
            'facility_id' => $otherFacility->id,
            'department_id' => $otherDepartment->id,
            'service_point_id' => $otherServicePoint->id,
            'patient_id' => $otherPatient->id,
            'status' => Appointment::STATUS_CHECKED_IN,
        ]);

        $this->actingAs($staff)
            ->get(route('appointments.index', ['search' => 'APT-HIDDEN-FACILITY']))
            ->assertOk()
            ->assertDontSee($otherPatient->full_name);
        $this->actingAs($staff)->post(route('appointments.check-in', $scheduled))->assertForbidden();
        $this->actingAs($staff)->post(route('appointments.cancel', $scheduled))->assertForbidden();
        $this->actingAs($staff)->post(route('appointments.complete', $checkedIn))->assertForbidden();

        $this->assertSame(Appointment::STATUS_SCHEDULED, $scheduled->fresh()->status);
        $this->assertSame(Appointment::STATUS_CHECKED_IN, $checkedIn->fresh()->status);
    }

    public function test_staff_cannot_forge_an_entire_appointment_in_another_facility(): void
    {
        $staff = $this->staffWithRole('receptionist');
        $otherFacility = Facility::factory()->create(['is_active' => true]);
        $otherDepartment = Department::factory()->create(['facility_id' => $otherFacility->id]);
        $otherServicePoint = ServicePoint::factory()->create(['department_id' => $otherDepartment->id]);
        $otherPatient = Patient::factory()->create(['facility_id' => $otherFacility->id]);

        $this->actingAs($staff)->post(route('appointments.store'), [
            'facility_id' => $otherFacility->id,
            'department_id' => $otherDepartment->id,
            'service_point_id' => $otherServicePoint->id,
            'patient_id' => $otherPatient->id,
            'scheduled_start' => now()->addDays(2)->setTime(9, 0)->toDateTimeString(),
            'scheduled_end' => now()->addDays(2)->setTime(9, 30)->toDateTimeString(),
        ])->assertForbidden();

        $this->assertDatabaseMissing('appointments', ['patient_id' => $otherPatient->id]);
    }

    private function appointmentContext(): array
    {
        $facility = Facility::query()->where('name', 'CityCare Medical Center')->where('is_active', true)->firstOrFail();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $servicePoint = ServicePoint::factory()->create(['department_id' => $department->id]);
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $provider = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        StaffProfile::query()->create([
            'user_id' => $provider->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'employee_number' => 'PROVIDER-'.$provider->id,
            'employment_status' => 'active',
        ]);

        return compact('facility', 'department', 'servicePoint', 'patient', 'provider');
    }

    private function staffWithRole(string $roleSlug): User
    {
        $user = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => true,
            'password' => Hash::make('Password123!'),
        ]);

        $user->roles()->attach(Role::where('slug', $roleSlug)->firstOrFail());
        $facility = Facility::query()->where('name', 'CityCare Medical Center')->firstOrFail();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        StaffProfile::create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'employee_number' => 'APPOINTMENT-'.$user->id,
            'employment_status' => 'active',
        ]);

        return $user;
    }
}
