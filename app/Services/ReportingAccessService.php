<?php

namespace App\Services;

use App\Models\Facility;
use App\Models\ReportDefinition;
use App\Models\ReportRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportingAccessService
{
    /**
     * A report inherits the permission boundary of the operational data it
     * summarizes. The reports.view permission grants workspace access only.
     *
     * @var array<string, string>
     */
    private const REPORT_PERMISSIONS = [
        'clinical_activity' => 'clinical.encounters.view',
        'laboratory_activity' => 'laboratory.view',
        'pharmacy_activity' => 'pharmacy.view',
        'billing_summary' => 'billing.view',
        'inventory_summary' => 'inventory.view',
    ];

    /** @var array<string, string> */
    private const CATEGORY_PERMISSIONS = [
        'clinical' => 'clinical.encounters.view',
        'laboratory' => 'laboratory.view',
        'lab' => 'laboratory.view',
        'pharmacy' => 'pharmacy.view',
        'billing' => 'billing.view',
        'finance' => 'billing.view',
        'financial' => 'billing.view',
        'inventory' => 'inventory.view',
        'stock' => 'inventory.view',
        'operational' => 'organization.view',
        'management' => 'organization.view',
        'administration' => 'organization.view',
    ];

    public function __construct(private readonly FacilityAccessService $facilities) {}

    /**
     * @return Collection<int, ReportDefinition>
     */
    public function definitionsFor(User $staff): Collection
    {
        return $this->accessibleDefinitions($staff, true);
    }

    /**
     * @return Collection<int, int>
     */
    public function definitionIdsForHistory(User $staff): Collection
    {
        return $this->accessibleDefinitions($staff, false)->pluck('id');
    }

    public function canAccessDefinition(User $staff, ReportDefinition $definition): bool
    {
        if (! $staff->isStaff() || ! $staff->isActive() || ! $staff->hasPermissionTo('reports.view')) {
            return false;
        }

        $permission = self::REPORT_PERMISSIONS[$definition->code]
            ?? self::CATEGORY_PERMISSIONS[strtolower(trim($definition->category))]
            ?? null;

        if ($permission === null) {
            return false;
        }

        return $staff->hasPermissionTo($permission);
    }

    public function assertDefinitionAccessible(User $staff, ReportDefinition $definition): void
    {
        $this->assertWorkspaceAccess($staff);

        if (! $this->canAccessDefinition($staff, $definition)) {
            abort(403, 'You are not authorized to access this report.');
        }
    }

    /**
     * @return Collection<int, Facility>
     */
    public function facilitiesFor(User $staff): Collection
    {
        $this->assertWorkspaceAccess($staff);

        if ($staff->hasRole('super-admin')) {
            return Facility::query()->where('is_active', true)->orderBy('name')->get();
        }

        return collect([$this->facilities->currentFacility($staff)]);
    }

    /**
     * Resolve the requested reporting scope. Only super administrators may
     * intentionally use a null facility for an organization-wide aggregate.
     */
    public function resolveFacility(User $staff, ?int $requestedFacilityId): ?Facility
    {
        $this->assertWorkspaceAccess($staff);

        if ($staff->hasRole('super-admin') && $requestedFacilityId === null) {
            return null;
        }

        if ($requestedFacilityId === null) {
            return $this->facilities->currentFacility($staff);
        }

        $facility = Facility::query()
            ->where('is_active', true)
            ->findOrFail($requestedFacilityId);

        $this->facilities->assertFacilityAccessible($staff, $facility->id);

        return $facility;
    }

    public function scopeRuns(Builder $query, User $staff): Builder
    {
        $this->assertWorkspaceAccess($staff);

        if ($staff->hasRole('super-admin')) {
            return $query;
        }

        return $query->where('facility_id', $this->facilities->currentFacility($staff)->id);
    }

    public function assertRunAccessible(User $staff, ReportRun $run): void
    {
        $run->loadMissing('definition');
        $this->assertDefinitionAccessible($staff, $run->definition);

        if ($run->facility_id === null) {
            if (! $staff->hasRole('super-admin')) {
                abort(403, 'You are not authorized to access organization-wide report runs.');
            }

            return;
        }

        $this->facilities->assertFacilityAccessible($staff, $run->facility_id);
    }

    private function assertWorkspaceAccess(User $staff): void
    {
        if (! $staff->isStaff() || ! $staff->isActive() || ! $staff->hasPermissionTo('reports.view')) {
            abort(403, 'Only authorized active staff members may access reporting.');
        }
    }

    /**
     * @return Collection<int, ReportDefinition>
     */
    private function accessibleDefinitions(User $staff, bool $activeOnly): Collection
    {
        $this->assertWorkspaceAccess($staff);

        return ReportDefinition::query()
            ->when($activeOnly, fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->filter(fn (ReportDefinition $definition): bool => $this->canAccessDefinition($staff, $definition))
            ->values();
    }
}
