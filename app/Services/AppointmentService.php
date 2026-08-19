<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    public function create(array $data): Appointment
    {
        $start = $data['scheduled_start'];
        $end = $data['scheduled_end'];

        if (! $start instanceof CarbonInterface || ! $end instanceof CarbonInterface) {
            throw new \InvalidArgumentException('Appointment times must be Carbon date-time instances.');
        }

        if ($end->lte($start)) {
            throw ValidationException::withMessages(['scheduled_end' => 'Appointment end must be after the start time.']);
        }

        $patient = Patient::query()->findOrFail($data['patient_id']);
        if (! $patient->isActive()) {
            throw ValidationException::withMessages(['patient_id' => 'Only active patients can be scheduled.']);
        }

        $this->ensureNoCollision($data, $start, $end);

        $data['appointment_number'] ??= $this->nextAppointmentNumber();
        $data['status'] ??= Appointment::STATUS_SCHEDULED;

        return Appointment::create($data);
    }

    private function ensureNoCollision(array $data, CarbonInterface $start, CarbonInterface $end): void
    {
        $query = Appointment::query()
            ->whereIn('status', [Appointment::STATUS_SCHEDULED, Appointment::STATUS_CHECKED_IN])
            ->where('scheduled_start', '<', $end)
            ->where('scheduled_end', '>', $start);

        $query->where(function ($q) use ($data) {
            $q->where('service_point_id', $data['service_point_id']);
            if (! empty($data['provider_id'])) {
                $q->orWhere('provider_id', $data['provider_id']);
            }
        });

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'scheduled_start' => 'The selected provider or service point is already booked for this time.',
            ]);
        }
    }

    private function nextAppointmentNumber(): string
    {
        do {
            $number = 'APT-'.now()->format('Ymd').'-'.str()->upper(str()->random(6));
        } while (Appointment::where('appointment_number', $number)->exists());

        return $number;
    }
}
