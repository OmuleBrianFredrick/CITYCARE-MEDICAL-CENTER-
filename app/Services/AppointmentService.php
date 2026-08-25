<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
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

        return DB::transaction(function () use ($data, $start, $end) {
            $patient = Patient::query()->lockForUpdate()->findOrFail($data['patient_id']);
            if ((int) $patient->facility_id !== (int) $data['facility_id']) {
                throw ValidationException::withMessages(['patient_id' => 'The selected patient does not belong to this facility.']);
            }
            if (! $patient->isActive()) {
                throw ValidationException::withMessages(['patient_id' => 'Only active patients can be scheduled.']);
            }

            $this->ensureNoCollision($data, $start, $end, true);

            $data['appointment_number'] ??= $this->nextAppointmentNumber();
            $data['status'] ??= Appointment::STATUS_SCHEDULED;

            return Appointment::create($data);
        }, 3);
    }

    public function checkIn(Appointment $appointment): Appointment
    {
        return DB::transaction(function () use ($appointment) {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            if (! $appointment->isScheduled()) {
                throw ValidationException::withMessages(['appointment' => 'Only scheduled appointments can be checked in.']);
            }
            $appointment->update(['status' => Appointment::STATUS_CHECKED_IN, 'checked_in_at' => now()]);

            return $appointment->refresh();
        }, 3);
    }

    public function complete(Appointment $appointment): Appointment
    {
        return DB::transaction(function () use ($appointment) {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            if (! $appointment->isCheckedIn()) {
                throw ValidationException::withMessages(['appointment' => 'Only checked-in appointments can be completed.']);
            }
            $appointment->update(['status' => Appointment::STATUS_COMPLETED, 'completed_at' => now()]);

            return $appointment->refresh();
        }, 3);
    }

    public function cancel(Appointment $appointment): Appointment
    {
        return DB::transaction(function () use ($appointment) {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            if (! $appointment->isScheduled()) {
                throw ValidationException::withMessages(['appointment' => 'Only scheduled appointments can be cancelled.']);
            }
            $appointment->update(['status' => Appointment::STATUS_CANCELLED, 'cancelled_at' => now()]);

            return $appointment->refresh();
        }, 3);
    }

    private function ensureNoCollision(array $data, CarbonInterface $start, CarbonInterface $end, bool $lock = false): void
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

        if ($lock) {
            $query->lockForUpdate();
        }

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
