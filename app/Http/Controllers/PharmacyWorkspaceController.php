<?php

namespace App\Http\Controllers;

use App\Models\InventoryStore;
use App\Models\Prescription;
use App\Services\FacilityAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PharmacyWorkspaceController extends Controller
{
    public function __construct(private readonly FacilityAccessService $facilityAccess) {}

    public function index(Request $request): View
    {
        $facility = $this->facilityAccess->currentFacility($request->user());
        $status = $request->string('status', 'pending')->toString();

        $prescriptions = Prescription::query()
            ->with([
                'patient',
                'encounter.department',
                'prescriber',
                'items.medication',
                'items.formulation',
                'items.dispensingItems.dispensing',
            ])
            ->where('facility_id', $facility->id)
            ->when($status === 'pending', fn ($query) => $query->whereIn('status', [Prescription::STATUS_PRESCRIBED, Prescription::STATUS_PARTIALLY_DISPENSED]))
            ->when(in_array($status, [Prescription::STATUS_COMPLETED, Prescription::STATUS_CANCELLED], true), fn ($query) => $query->where('status', $status))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($nested) use ($search) {
                    $nested->where('prescription_number', 'like', "%{$search}%")
                        ->orWhereHas('patient', fn ($patient) => $patient
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('medical_record_number', 'like', "%{$search}%"));
                });
            })
            ->latest('prescribed_at')
            ->paginate(15)
            ->withQueryString();

        $stores = InventoryStore::query()
            ->where('facility_id', $facility->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('pharmacy.index', compact('facility', 'prescriptions', 'stores', 'status'));
    }
}
