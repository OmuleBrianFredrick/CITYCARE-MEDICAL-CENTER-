<?php

namespace App\Services;

use App\Models\ClinicalDiagnosis;
use App\Models\ClinicalEncounter;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ClinicalDiagnosisService
{
    public function record(ClinicalEncounter $encounter, User $recorder, array $data): ClinicalDiagnosis
    {
        $this->ensureOpenEncounter($encounter);
        $this->ensureActiveStaff($recorder);

        return ClinicalDiagnosis::create([
            'encounter_id' => $encounter->id,
            'recorded_by' => $recorder->id,
            'diagnosis' => $data['diagnosis'],
            'diagnosis_code' => $data['diagnosis_code'] ?? null,
            'type' => $data['type'] ?? 'primary',
            'notes' => $data['notes'] ?? null,
        ]);
    }

    private function ensureOpenEncounter(ClinicalEncounter $encounter): void
    {
        if (! $encounter->isOpen()) {
            throw ValidationException::withMessages([
                'encounter' => 'Diagnoses can only be recorded for an open encounter.',
            ]);
        }
    }

    private function ensureActiveStaff(User $user): void
    {
        if (! $user->isStaff() || ! $user->isActive()) {
            throw ValidationException::withMessages([
                'user' => 'Only active staff members can record diagnoses.',
            ]);
        }
    }
}
