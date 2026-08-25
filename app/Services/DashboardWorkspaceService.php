<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\Facility;
use App\Models\Invoice;
use App\Models\LaboratoryOrder;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardWorkspaceService
{
    /**
     * Build the reusable shell context shared by every authenticated workspace.
     */
    public function shell(User $user): array
    {
        $user->loadMissing('roles.permissions');

        $permissions = $user->roles
            ->flatMap(fn ($role) => $role->permissions)
            ->pluck('slug')
            ->unique()
            ->values();

        $facility = Facility::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        return [
            'facility' => $facility,
            'roleLabel' => $user->roles->pluck('name')->join(', ') ?: 'CityCare account',
            'navigation' => $this->navigation($permissions),
        ];
    }

    /**
     * Build the role-aware, data-backed dashboard content.
     */
    public function dashboard(User $user): array
    {
        $shell = $this->shell($user);

        return [
            'metrics' => $this->metrics($shell['facility'], $user),
            'quickActions' => $this->quickActions($user),
        ];
    }

    private function navigation(Collection $permissions): array
    {
        $items = [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => 'dashboard', 'permission' => null],
            ['label' => 'Patients', 'route' => 'patients.index', 'active' => 'patients.*', 'permission' => 'patients.view'],
            ['label' => 'Appointments', 'route' => 'appointments.index', 'active' => 'appointments.*', 'permission' => 'appointments.manage'],
            ['label' => 'Clinical care', 'route' => 'encounters.index', 'active' => 'encounters.*', 'permission' => 'clinical.encounters.view'],
            ['label' => 'Inventory', 'route' => 'inventory.procurement.index', 'active' => 'inventory.procurement.*', 'permission' => 'inventory.view'],
            ['label' => 'Reports', 'route' => 'reports.index', 'active' => 'reports.*', 'permission' => 'reports.view'],
            ['label' => 'Audit log', 'route' => 'audit.index', 'active' => 'audit.*', 'permission' => 'audit.view'],
            ['label' => 'Organization', 'route' => 'organization.index', 'active' => 'organization.*', 'permission' => 'organization.view'],
        ];

        return collect($items)
            ->filter(fn (array $item) => $item['permission'] === null || $permissions->contains($item['permission']))
            ->map(fn (array $item) => array_merge($item, ['url' => route($item['route'])]))
            ->values()
            ->all();
    }

    private function metrics(?Facility $facility, User $user): array
    {
        if ($facility === null) {
            return [];
        }

        $facilityId = $facility->id;
        $metrics = [];

        if ($user->hasPermissionTo('patients.view')) {
            $metrics[] = $this->metric(
                'Active patients',
                Patient::query()->where('facility_id', $facilityId)->where('status', Patient::STATUS_ACTIVE)->count(),
                'Registered patient records available to your role.',
                route('patients.index'),
                'Open registry',
            );
        }

        if ($user->hasPermissionTo('appointments.manage')) {
            $metrics[] = $this->metric(
                'Appointments today',
                Appointment::query()->where('facility_id', $facilityId)->whereDate('scheduled_start', today())->count(),
                'Scheduled, checked-in, and completed visits for today.',
                route('appointments.index', ['date' => today()->toDateString()]),
                'View appointments',
            );
        }

        if ($user->hasPermissionTo('clinical.encounters.view')) {
            $metrics[] = $this->metric(
                'Open encounters',
                ClinicalEncounter::query()->where('facility_id', $facilityId)->where('status', ClinicalEncounter::STATUS_OPEN)->count(),
                'Clinical encounters that are still active.',
                route('encounters.index', ['status' => ClinicalEncounter::STATUS_OPEN]),
                'Open clinical care',
            );
        }

        if ($user->hasPermissionTo('laboratory.view')) {
            $metrics[] = $this->metric(
                'Laboratory work waiting',
                LaboratoryOrder::query()
                    ->where('facility_id', $facilityId)
                    ->whereIn('status', [LaboratoryOrder::STATUS_ORDERED, LaboratoryOrder::STATUS_IN_PROGRESS])
                    ->count(),
                'Orders that still require laboratory processing.',
                route('encounters.index'),
                'Open clinical worklist',
            );
        }

        if ($user->hasPermissionTo('pharmacy.view')) {
            $metrics[] = $this->metric(
                'Prescriptions waiting',
                Prescription::query()
                    ->where('facility_id', $facilityId)
                    ->whereIn('status', [Prescription::STATUS_PRESCRIBED, Prescription::STATUS_PARTIALLY_DISPENSED])
                    ->count(),
                'Prescriptions awaiting complete dispensing.',
                route('encounters.index'),
                'Open clinical worklist',
            );
        }

        if ($user->hasPermissionTo('billing.view')) {
            $outstanding = Invoice::query()
                ->where('facility_id', $facilityId)
                ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID])
                ->sum('balance_due');

            $metrics[] = $this->metric(
                'Outstanding balance',
                sprintf('%s %s', $facility->currency, number_format((float) $outstanding, 2)),
                'Open invoice balances for this facility.',
                route('patients.index'),
                'Find a patient',
            );
        }

        if ($user->hasPermissionTo('inventory.view')) {
            $lowStock = DB::table('inventory_stock_balances as balances')
                ->join('inventory_items as items', 'items.id', '=', 'balances.inventory_item_id')
                ->join('inventory_stores as stores', 'stores.id', '=', 'balances.store_id')
                ->where('stores.facility_id', $facilityId)
                ->where('balances.status', 'active')
                ->where('items.is_active', true)
                ->whereColumn('balances.quantity_available', '<=', 'items.reorder_level')
                ->count();

            $metrics[] = $this->metric(
                'Low-stock lines',
                $lowStock,
                'Stock balances at or below their configured reorder level.',
                route('inventory.procurement.index'),
                'Open inventory',
            );
        }

        return $metrics;
    }

    private function quickActions(User $user): array
    {
        $actions = [];

        if ($user->hasPermissionTo('patients.create')) {
            $actions[] = ['label' => 'Register patient', 'description' => 'Create a new patient medical record.', 'url' => route('patients.create')];
        }
        if ($user->hasPermissionTo('appointments.manage')) {
            $actions[] = ['label' => 'Schedule appointment', 'description' => 'Book a patient into an available service point.', 'url' => route('appointments.create')];
        }
        if ($user->hasPermissionTo('clinical.encounters.create')) {
            $actions[] = ['label' => 'Open encounter', 'description' => 'Start a clinical encounter for a patient.', 'url' => route('encounters.create')];
        }
        if ($user->hasPermissionTo('inventory.manage')) {
            $actions[] = ['label' => 'Create purchase order', 'description' => 'Begin a new procurement workflow.', 'url' => route('inventory.procurement.create')];
        }
        if ($user->hasPermissionTo('reports.view')) {
            $actions[] = ['label' => 'Run report', 'description' => 'Review available operational and management reports.', 'url' => route('reports.index')];
        }
        if ($user->hasPermissionTo('organization.manage')) {
            $actions[] = ['label' => 'Configure organization', 'description' => 'Manage facility, department, and service-point settings.', 'url' => route('organization.index')];
        }

        return $actions;
    }

    private function metric(string $label, int|string $value, string $description, string $url, string $linkLabel): array
    {
        return compact('label', 'value', 'description', 'url', 'linkLabel');
    }
}
