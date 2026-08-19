<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ClinicalEncounterService
{
    public function open(Appointment $appointment, User $clinician, array $data = []): ClinicalEncounter
    {
        $patient = $appointment->patient;

        if (! $patient || ! $patient->isActive()) {
            throw ValidationException::withMessages([
                'patient_id' => 'Only active patients can have clinical encounters opened.',
            ]);
        }

        if (! $clinician->isStaff() || ! $clinician->isActive()) {
            throw ValidationException::withMessages([
                'clinician_id' => 'Only active staff members can open clinical encounters.',
            ]);
        }

        if ($appointment->provider_id !== null && $appointment->provider_id !== $clinician->id) {
            throw ValidationException::withMessages([
                'clinician_id' => 'The selected clinician is not the provider assigned to this appointment.',
            ]);
        }

        if (! $appointment->isCheckedIn()) {
            throw ValidationException::withMessages([
                'appointment_id' => 'Only checked-in appointments can open clinical encounters.',
            ]);
        }

        if ($this->hasOpenEncounter($patient)) {
            throw ValidationException::withMessages([
                'patient_id' => 'This patient already has an open clinical encounter.',
            ]);
        }

        return ClinicalEncounter::create([
            'facility_id' => $patient->facility_id,
            'department_id' => $appointment->department_id,
            'service_point_id' => $appointment->service_point_id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'clinician_id' => $clinician->id,
            'encounter_number' => $this->nextEncounterNumber(),
            'type' => $data['type'] ?? ClinicalEncounter::TYPE_OUTPATIENT,
            'status' => ClinicalEncounter::STATUS_OPEN,
            'started_at' => now(),
            'summary' => $data['summary'] ?? null,
        ]);
    }

    public function close(ClinicalEncounter $encounter, ?string $summary = null): ClinicalEncounter
    {
        if (! $encounter->isOpen()) {
            throw ValidationException::withMessages([
                'encounter' => 'Only open encounters can be closed.',
            ]);
        }

        $encounter->forceFill([
            'status' => ClinicalEncounter::STATUS_CLOSED,
            'closed_at' => now(),
            'summary' => $summary ?? $encounter->summary,
        ])->save();

        return $encounter->refresh();
    }

    public function cancel(ClinicalEncounter $encounter): ClinicalEncounter
    {
        if (! $encounter->isOpen()) {
            throw ValidationException::withMessages([
                'encounter' => 'Only open encounters can be cancelled.',
            ]);
        }

        $encounter->forceFill([
            'status' => ClinicalEncounter::STATUS_CANCELLED,
            'closed_at' => now(),
        ])->save();

        return $encounter->refresh();
    }

    private function hasOpenEncounter(Patient $patient): bool
    {
        return ClinicalEncounter::query()
            ->where('patient_id', $patient->id)
            ->where('status', ClinicalEncounter::STATUS_OPEN)
            ->exists();
    }

    private function nextEncounterNumber(): string
    {
        do {
            $number = 'ENC-'.now()->format('Ymd').'-'.str()->upper(str()->random(6));
        } while (ClinicalEncounter::query()->where('encounter_number', $number)->exists());

        return $number;
    }
}
