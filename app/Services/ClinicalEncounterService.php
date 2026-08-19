<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\Patient;
use Illuminate\Validation\ValidationException;

class ClinicalEncounterService
{
    public function open(Patient $patient, array $data): ClinicalEncounter
    {
        if (! $patient->isActive()) {
            throw ValidationException::withMessages([
                'patient_id' => 'Only active patients can have clinical encounters opened.',
            ]);
        }

        if ($this->hasOpenEncounter($patient)) {
            throw ValidationException::withMessages([
                'patient_id' => 'This patient already has an open clinical encounter.',
            ]);
        }

        $appointment = null;
        if (! empty($data['appointment_id'])) {
            $appointment = Appointment::query()->findOrFail($data['appointment_id']);

            if ($appointment->patient_id !== $patient->id) {
                throw ValidationException::withMessages([
                    'appointment_id' => 'The selected appointment does not belong to this patient.',
                ]);
            }

            if ($appointment->status === Appointment::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'appointment_id' => 'A cancelled appointment cannot be used to open an encounter.',
                ]);
            }
        }

        $data['facility_id'] = $patient->facility_id;
        $data['patient_id'] = $patient->id;
        $data['appointment_id'] = $appointment?->id;
        $data['encounter_number'] ??= $this->nextEncounterNumber();
        $data['status'] ??= ClinicalEncounter::STATUS_OPEN;
        $data['started_at'] ??= now();

        return ClinicalEncounter::create($data);
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
