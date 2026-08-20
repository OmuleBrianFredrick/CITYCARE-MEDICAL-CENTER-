<?php

namespace App\Services;

use App\Models\ClinicalEncounter;
use App\Models\ClinicalNote;
use App\Models\ClinicalVital;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class ClinicalCareService
{
    public function recordVitals(ClinicalEncounter $encounter, User $recorder, array $data): ClinicalVital
    {
        $this->ensureOpenEncounter($encounter);
        $this->ensureActiveStaff($recorder);

        return ClinicalVital::create([
            'encounter_id' => $encounter->id,
            'recorded_by' => $recorder->id,
            ...$data,
        ]);
    }

    public function saveNote(ClinicalEncounter $encounter, User $author, array $data): ClinicalNote
    {
        $this->ensureOpenEncounter($encounter);
        $this->ensureActiveStaff($author);

        return DB::transaction(function () use ($encounter, $author, $data): ClinicalNote {
            return ClinicalNote::create([
                'encounter_id' => $encounter->id,
                'author_id' => $author->id,
                ...$data,
            ]);
        });
    }

    public function finalizeNote(ClinicalNote $note): ClinicalNote
    {
        if ($note->isFinalized()) {
            throw ValidationException::withMessages([
                'note' => 'This clinical note has already been finalized.',
            ]);
        }

        $note->forceFill(['finalized_at' => now()])->save();

        return $note->refresh();
    }

    private function ensureOpenEncounter(ClinicalEncounter $encounter): void
    {
        if (! $encounter->isOpen()) {
            throw ValidationException::withMessages([
                'encounter' => 'Clinical documentation can only be recorded for an open encounter.',
            ]);
        }
    }

    private function ensureActiveStaff(User $user): void
    {
        if (! $user->isStaff() || ! $user->isActive()) {
            throw ValidationException::withMessages([
                'user' => 'Only active staff members can record clinical documentation.',
            ]);
        }
    }
}
