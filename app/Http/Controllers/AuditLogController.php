<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuditLogIndexRequest;
use App\Models\Facility;
use App\Services\AuditLogService;
use App\Services\FacilityAccessService;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly FacilityAccessService $facilityAccess,
    ) {}

    public function index(AuditLogIndexRequest $request): View
    {
        $staff = $request->user();
        $filters = $request->validated();
        $isOrganizationWide = $staff->hasRole('super-admin');

        if ($isOrganizationWide) {
            $availableFacilities = Facility::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']);

            if (isset($filters['facility_id']) && ! $availableFacilities->contains('id', (int) $filters['facility_id'])) {
                abort(403, 'You are not authorized to review audit activity for this facility.');
            }
        } else {
            $facility = $this->facilityAccess->currentFacility($staff);

            if (isset($filters['facility_id']) && (int) $filters['facility_id'] !== $facility->id) {
                abort(403, 'You are not authorized to review audit activity for this facility.');
            }

            $filters['facility_id'] = $facility->id;
            $availableFacilities = collect([$facility]);
        }

        $events = $this->audit
            ->query($filters)
            ->with(['actor:id,name', 'facility:id,name'])
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();

        return view('audit.index', compact('events', 'availableFacilities', 'isOrganizationWide'));
    }
}
