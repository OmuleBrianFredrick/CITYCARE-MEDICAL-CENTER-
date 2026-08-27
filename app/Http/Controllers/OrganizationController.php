<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationDepartmentRequest;
use App\Http\Requests\StoreOrganizationServicePointRequest;
use App\Http\Requests\UpdateOrganizationFacilityRequest;
use App\Http\Requests\UpdateOrganizationSettingRequest;
use App\Models\Department;
use App\Models\Facility;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\FacilityAccessService;
use App\Services\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function __construct(
        private readonly OrganizationService $organization,
        private readonly FacilityAccessService $facilityAccess,
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request): View
    {
        $request->validate([
            'facility_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $facility = $this->resolveFacility($user, $request->integer('facility_id') ?: null);
        $isSuperAdministrator = $user->hasRole('super-admin');

        return view('organization.index', [
            'facility' => $facility,
            'facilities' => $isSuperAdministrator
                ? $this->organization->activeFacilities()
                : collect([$facility]),
            'departments' => Department::query()
                ->where('facility_id', $facility->getKey())
                ->with(['servicePoints' => fn ($query) => $query->orderBy('sort_order')->orderBy('name')])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'settings' => SystemSetting::query()->orderBy('group')->orderBy('key')->get(),
            'isSuperAdministrator' => $isSuperAdministrator,
            'canManageOrganization' => $user->hasPermissionTo('organization.manage'),
            'canManageSettings' => $isSuperAdministrator && $user->hasPermissionTo('organization.manage'),
        ]);
    }

    public function updateFacility(UpdateOrganizationFacilityRequest $request): RedirectResponse
    {
        $facility = $this->resolveFacility($request->user(), (int) $request->validated('facility_id'));
        $before = $facility->only($this->auditedFacilityAttributes());
        $facility = $this->organization->saveFacility($request->facilityAttributes(), $facility);

        $this->audit->record(
            actor: $request->user(),
            eventType: 'organization.facility',
            action: 'updated',
            auditableType: Facility::class,
            auditableId: $facility->getKey(),
            facilityId: $facility->getKey(),
            before: $before,
            after: $facility->only($this->auditedFacilityAttributes()),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return to_route('organization.index', ['facility_id' => $facility->getKey()])
            ->with('status', 'Facility configuration updated successfully.');
    }

    public function storeDepartment(StoreOrganizationDepartmentRequest $request): RedirectResponse
    {
        $facility = $this->resolveFacility($request->user(), (int) $request->validated('facility_id'));
        $department = $this->organization->createDepartment($request->departmentAttributes(), $facility);

        $this->audit->record(
            actor: $request->user(),
            eventType: 'organization.department',
            action: 'created',
            auditableType: Department::class,
            auditableId: $department->getKey(),
            facilityId: $facility->getKey(),
            after: $department->only(['facility_id', 'name', 'code', 'description', 'is_active']),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return to_route('organization.index', ['facility_id' => $facility->getKey()])
            ->with('status', 'Department created successfully.');
    }

    public function storeServicePoint(StoreOrganizationServicePointRequest $request): RedirectResponse
    {
        $facility = $this->resolveFacility($request->user(), (int) $request->validated('facility_id'));
        $servicePoint = $this->organization->createServicePoint($request->servicePointAttributes(), $facility);

        $this->audit->record(
            actor: $request->user(),
            eventType: 'organization.service_point',
            action: 'created',
            auditableType: $servicePoint::class,
            auditableId: $servicePoint->getKey(),
            facilityId: $facility->getKey(),
            after: $servicePoint->only(['department_id', 'name', 'code', 'type', 'location', 'is_active']),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return to_route('organization.index', ['facility_id' => $facility->getKey()])
            ->with('status', 'Service point created successfully.');
    }

    public function updateSetting(UpdateOrganizationSettingRequest $request, string $key): RedirectResponse
    {
        $setting = $request->setting() ?? abort(404);
        $this->organization->updateSettingValue($setting, $request->validated('value'));

        $this->audit->record(
            actor: $request->user(),
            eventType: 'organization.setting',
            action: 'updated',
            auditableType: SystemSetting::class,
            auditableId: $setting->getKey(),
            context: ['key' => $key, 'type' => $setting->type],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', 'System setting updated successfully.');
    }

    private function resolveFacility(User $user, ?int $requestedFacilityId = null): Facility
    {
        $assignedFacility = $this->facilityAccess->currentFacility($user);

        if ($requestedFacilityId === null || $requestedFacilityId === $assignedFacility->getKey()) {
            return $assignedFacility;
        }

        $this->facilityAccess->assertFacilityAccessible($user, $requestedFacilityId);

        return Facility::query()
            ->where('is_active', true)
            ->findOrFail($requestedFacilityId);
    }

    private function auditedFacilityAttributes(): array
    {
        return [
            'name',
            'legal_name',
            'registration_number',
            'phone',
            'email',
            'website',
            'address_line1',
            'address_line2',
            'city',
            'district',
            'country',
            'timezone',
            'currency',
            'primary_color',
            'secondary_color',
            'accent_color',
        ];
    }
}
