<?php

namespace App\Http\Controllers;

use App\Models\LaboratoryOrder;
use App\Services\FacilityAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LaboratoryWorkspaceController extends Controller
{
    public function __construct(private readonly FacilityAccessService $facilityAccess) {}

    public function index(Request $request): View
    {
        $facility = $this->facilityAccess->currentFacility($request->user());
        $status = $request->string('status', 'pending')->toString();

        $orders = LaboratoryOrder::query()
            ->with([
                'patient',
                'encounter.department',
                'orderedBy',
                'items.laboratoryTest',
                'items.result.recordedBy',
            ])
            ->where('facility_id', $facility->id)
            ->when($status === 'pending', function ($query) {
                $query->whereIn('status', [LaboratoryOrder::STATUS_ORDERED, LaboratoryOrder::STATUS_IN_PROGRESS])
                    ->whereHas('encounter', fn ($encounter) => $encounter->where('status', 'open'));
            })
            ->when(in_array($status, [LaboratoryOrder::STATUS_COMPLETED, LaboratoryOrder::STATUS_CANCELLED], true), fn ($query) => $query->where('status', $status))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($nested) use ($search) {
                    $nested->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('patient', fn ($patient) => $patient
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('medical_record_number', 'like', "%{$search}%"));
                });
            })
            ->latest('ordered_at')
            ->paginate(15)
            ->withQueryString();

        return view('laboratory.index', compact('facility', 'orders', 'status'));
    }
}
