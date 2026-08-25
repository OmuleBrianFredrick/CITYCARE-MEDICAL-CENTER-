<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Role;
use App\Models\ServicePoint;
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

    private function appointmentContext(): array
    {
        $facility = Facility::query()->where('name', 'CityCare Medical Center')->where('is_active', true)->firstOrFail();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $servicePoint = ServicePoint::factory()->create(['department_id' => $department->id]);
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $provider = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);

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

        return $user;
    }
}
