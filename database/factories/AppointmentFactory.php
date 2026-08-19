<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Appointment> */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $facility = Facility::query()->where('is_active', true)->orderBy('id')->first()
            ?? Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $servicePoint = ServicePoint::factory()->create(['department_id' => $department->id]);
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $start = fake()->dateTimeBetween('+1 day', '+14 days');
        $end = (clone $start)->modify('+30 minutes');

        return [
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id,
            'provider_id' => User::factory()->state(['user_type' => 'staff', 'is_active' => true]),
            'appointment_number' => 'APT-'.fake()->unique()->numerify('########').'-'.fake()->unique()->lexify('????'),
            'scheduled_start' => $start,
            'scheduled_end' => $end,
            'status' => Appointment::STATUS_SCHEDULED,
            'reason' => fake()->sentence(4),
            'notes' => null,
            'checked_in_at' => null,
            'cancelled_at' => null,
            'completed_at' => null,
            'created_by' => null,
        ];
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => Appointment::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }

    public function checkedIn(): static
    {
        return $this->state([
            'status' => Appointment::STATUS_CHECKED_IN,
            'checked_in_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => Appointment::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }
}
