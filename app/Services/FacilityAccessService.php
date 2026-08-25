<?php

namespace App\Services;

use App\Models\ClinicalEncounter;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class FacilityAccessService
{
    /**
     * Resolve the active facility context for a staff-only operational workspace.
     */
    public function currentFacility(User $staff): Facility
    {
        if (! $staff->isStaff() || ! $staff->isActive()) {
            abort(403, 'Only active staff members may access facility workspaces.');
        }

        if ($staff->hasRole('super-admin')) {
            return Facility::query()->where('is_active', true)->orderBy('id')->firstOrFail();
        }

        $staff->loadMissing('staffProfile.department.facility');
        $facility = $staff->staffProfile?->department?->facility;

        if (! $facility?->is_active) {
            abort(403, 'Your active staff account is not assigned to an active facility.');
        }

        return $facility;
    }

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
