<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\ServicePoint;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConcurrencyLifecycleIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_equivalent_booking_is_rejected_after_first_booking(): void
    {
        [$facility, $department, $servicePoint, $patient] = $this->context();
        $service = app(AppointmentService::class);
        $start = Carbon::tomorrow()->setTime(9, 0);
        $end = Carbon::tomorrow()->setTime(9, 30);

        $service->create($this->payload($facility, $department, $servicePoint, $patient, $start, $end));

        $this->expectException(ValidationException::class);
        $service->create($this->payload($facility, $department, $servicePoint, $patient, $start, $end));
    }

    public function test_appointment_must_follow_scheduled_checked_in_completed_lifecycle(): void
    {
        [$facility, $department, $servicePoint, $patient] = $this->context();
        $service = app(AppointmentService::class);
        $appointment = $service->create($this->payload(
            $facility, $department, $servicePoint, $patient,
            Carbon::tomorrow()->setTime(10, 0), Carbon::tomorrow()->setTime(10, 30)
        ));

        $this->expectException(ValidationException::class);
        $service->complete($appointment);
    }

    public function test_checked_in_appointment_can_complete_but_cannot_be_cancelled_afterward(): void
    {
        [$facility, $department, $servicePoint, $patient] = $this->context();
        $service = app(AppointmentService::class);
        $appointment = $service->create($this->payload(
            $facility, $department, $servicePoint, $patient,
            Carbon::tomorrow()->setTime(11, 0), Carbon::tomorrow()->setTime(11, 30)
        ));

        $appointment = $service->checkIn($appointment);
        $this->assertSame(Appointment::STATUS_CHECKED_IN, $appointment->status);

        $appointment = $service->complete($appointment);
        $this->assertSame(Appointment::STATUS_COMPLETED, $appointment->status);
        $this->assertNotNull($appointment->completed_at);

        $this->expectException(ValidationException::class);
        $service->cancel($appointment);
    }

    private function context(): array
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $servicePoint = ServicePoint::factory()->create(['department_id' => $department->id]);
        $patient = Patient::factory()->create(['facility_id' => $facility->id, 'status' => Patient::STATUS_ACTIVE]);

        return [$facility, $department, $servicePoint, $patient];
    }

    private function payload($facility, $department, $servicePoint, $patient, Carbon $start, Carbon $end): array
    {
        return [
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id,
            'scheduled_start' => $start,
            'scheduled_end' => $end,
            'reason' => 'Integrity test',
        ];
    }
}
