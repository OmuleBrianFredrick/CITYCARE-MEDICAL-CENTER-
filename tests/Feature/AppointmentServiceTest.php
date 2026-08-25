<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AppointmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_patient_can_be_scheduled_with_generated_number(): void
    {
        [$facility, $department, $servicePoint] = $this->structure();
        $patient = Patient::factory()->create(['facility_id' => $facility->id, 'status' => Patient::STATUS_ACTIVE]);
        $provider = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);

        $appointment = app(AppointmentService::class)->create([
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id,
            'provider_id' => $provider->id,
            'scheduled_start' => Carbon::tomorrow()->setTime(9, 0),
            'scheduled_end' => Carbon::tomorrow()->setTime(9, 30),
            'reason' => 'Routine review',
        ]);

        $this->assertSame(Appointment::STATUS_SCHEDULED, $appointment->status);
        $this->assertStringStartsWith('APT-', $appointment->appointment_number);
    }

    public function test_inactive_patient_cannot_be_scheduled(): void
    {
        [$facility, $department, $servicePoint] = $this->structure();
        $patient = Patient::factory()->create(['facility_id' => $facility->id, 'status' => Patient::STATUS_INACTIVE]);

        $this->expectException(ValidationException::class);
        app(AppointmentService::class)->create([
            'facility_id' => $facility->id, 'department_id' => $department->id, 'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id, 'scheduled_start' => Carbon::tomorrow()->setTime(9, 0), 'scheduled_end' => Carbon::tomorrow()->setTime(9, 30),
        ]);
    }

    public function test_patient_from_another_facility_cannot_be_scheduled(): void
    {
        [$facility, $department, $servicePoint] = $this->structure();
        $otherPatient = Patient::factory()->create(['facility_id' => Facility::factory()->create()->id]);

        $this->expectException(ValidationException::class);
        app(AppointmentService::class)->create([
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $otherPatient->id,
            'scheduled_start' => Carbon::tomorrow()->setTime(9, 0),
            'scheduled_end' => Carbon::tomorrow()->setTime(9, 30),
        ]);
    }

    public function test_overlapping_service_point_appointment_is_rejected(): void
    {
        [$facility, $department, $servicePoint] = $this->structure();
        $patient = Patient::factory()->create(['facility_id' => $facility->id, 'status' => Patient::STATUS_ACTIVE]);
        $service = app(AppointmentService::class);
        $start = Carbon::tomorrow()->setTime(10, 0);
        $end = Carbon::tomorrow()->setTime(10, 30);

        $service->create(['facility_id' => $facility->id, 'department_id' => $department->id, 'service_point_id' => $servicePoint->id, 'patient_id' => $patient->id, 'scheduled_start' => $start, 'scheduled_end' => $end]);

        $this->expectException(ValidationException::class);
        $service->create(['facility_id' => $facility->id, 'department_id' => $department->id, 'service_point_id' => $servicePoint->id, 'patient_id' => $patient->id, 'scheduled_start' => $start->copy()->addMinutes(10), 'scheduled_end' => $end->copy()->addMinutes(10)]);
    }

    public function test_cancelled_appointment_does_not_block_a_new_booking(): void
    {
        [$facility, $department, $servicePoint] = $this->structure();
        $patient = Patient::factory()->create(['facility_id' => $facility->id, 'status' => Patient::STATUS_ACTIVE]);
        $start = Carbon::tomorrow()->setTime(11, 0);
        $end = Carbon::tomorrow()->setTime(11, 30);
        Appointment::create(['facility_id' => $facility->id, 'department_id' => $department->id, 'service_point_id' => $servicePoint->id, 'patient_id' => $patient->id, 'appointment_number' => 'APT-CANCELLED', 'scheduled_start' => $start, 'scheduled_end' => $end, 'status' => Appointment::STATUS_CANCELLED]);

        $appointment = app(AppointmentService::class)->create(['facility_id' => $facility->id, 'department_id' => $department->id, 'service_point_id' => $servicePoint->id, 'patient_id' => $patient->id, 'scheduled_start' => $start, 'scheduled_end' => $end]);
        $this->assertSame(Appointment::STATUS_SCHEDULED, $appointment->status);
    }

    private function structure(): array
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $servicePoint = ServicePoint::factory()->create(['department_id' => $department->id]);

        return [$facility, $department, $servicePoint];
    }
}
