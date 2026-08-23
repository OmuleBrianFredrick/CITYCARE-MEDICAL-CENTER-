<?php

namespace App\Services;

use App\Models\ClinicalEncounter;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class FacilityAccessService
{
    public function assertPatientAccessible(User $staff, Patient $patient): void
    {
        $this->assertFacilityAccessible($staff, $patient->facility_id);
    }

    public function assertEncounterAccessible(User $staff, ClinicalEncounter $encounter): void
    {
        $this->assertFacilityAccessible($staff, $encounter->facility_id);
    }

    public function assertFacilityAccessible(User $staff, int $facilityId): void
    {
        if (! $staff->isStaff() || ! $staff->isActive()) {
            throw ValidationException::withMessages([
                'authorization' => 'Only active staff members may access facility-scoped records.',
            ]);
        }

        if ($staff->hasRole('super-admin')) {
            return;
        }

        $staff->loadMissing('staffProfile.department');
        $allowedFacilityId = $staff->staffProfile?->department?->facility_id;

        if ($allowedFacilityId === null || (int) $allowedFacilityId !== (int) $facilityId) {
            abort(403, 'You are not authorized to access records from this facility.');
        }
    }
}
