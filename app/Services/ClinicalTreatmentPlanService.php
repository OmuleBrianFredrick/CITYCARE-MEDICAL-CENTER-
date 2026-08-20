<?php

namespace App\Services;

use App\Models\ClinicalEncounter;
use App\Models\ClinicalTreatmentPlan;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ClinicalTreatmentPlanService
{
    public function create(ClinicalEncounter $encounter, User $author, array $data): ClinicalTreatmentPlan
    {
        $this->ensureCanDocument($encounter, $author);

        return ClinicalTreatmentPlan::create([
            'encounter_id' => $encounter->id,
            'author_id' => $author->id,
            'plan' => $data['plan'],
            'follow_up_date' => $data['follow_up_date'] ?? null,
            'status' => ClinicalTreatmentPlan::STATUS_ACTIVE,
        ]);
    }

    public function complete(ClinicalTreatmentPlan $plan): ClinicalTreatmentPlan
    {
        if (! $plan->isActive()) {
            throw ValidationException::withMessages([
                'treatment_plan' => 'Only active treatment plans can be completed.',
            ]);
        }

        $plan->forceFill([
            'status' => ClinicalTreatmentPlan::STATUS_COMPLETED,
            'completed_at' => now(),
        ])->save();

        return $plan->refresh();
    }

    public function cancel(ClinicalTreatmentPlan $plan): ClinicalTreatmentPlan
    {
        if (! $plan->isActive()) {
            throw ValidationException::withMessages([
                'treatment_plan' => 'Only active treatment plans can be cancelled.',
            ]);
        }

        $plan->forceFill([
            'status' => ClinicalTreatmentPlan::STATUS_CANCELLED,
        ])->save();

        return $plan->refresh();
    }

    private function ensureCanDocument(ClinicalEncounter $encounter, User $author): void
    {
        if (! $encounter->isOpen()) {
            throw ValidationException::withMessages([
                'encounter' => 'Treatment plans can only be documented on open encounters.',
            ]);
        }

        if (! $author->isStaff() || ! $author->isActive()) {
            throw ValidationException::withMessages([
                'author_id' => 'Only active staff members can document treatment plans.',
            ]);
        }
    }
}
