<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePharmacyDispensingRequest;
use App\Http\Requests\StorePharmacyPrescriptionRequest;
use App\Models\ClinicalEncounter;
use App\Models\InventoryStore;
use App\Models\Prescription;
use App\Services\FacilityAccessService;
use App\Services\PharmacyInventoryDispensingService;
use App\Services\PharmacyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PharmacyController extends Controller
{
    public function __construct(
        private readonly PharmacyService $pharmacy,
        private readonly PharmacyInventoryDispensingService $pharmacyInventory,
        private readonly FacilityAccessService $facilityAccess,
    ) {}

    public function show(ClinicalEncounter $encounter, Request $request): View
    {
        $this->facilityAccess->assertEncounterAccessible($request->user(), $encounter);
        $encounter->load([
            'patient',
            'prescriptions' => fn ($query) => $query->with([
                'prescriber',
                'items.medication',
                'items.formulation',
                'items.dispensingItems.dispensing',
            ])->latest('prescribed_at'),
        ]);

        return view('pharmacy.show', compact('encounter'));
    }

    public function store(StorePharmacyPrescriptionRequest $request, ClinicalEncounter $encounter): RedirectResponse
    {
        $this->facilityAccess->assertEncounterAccessible($request->user(), $encounter);
        $this->pharmacy->prescribe($encounter, $request->user(), $request->validated());

        return back()->with('status', "Prescription created for encounter {$encounter->encounter_number}.");
    }

    public function dispense(StorePharmacyDispensingRequest $request, Prescription $prescription): RedirectResponse
    {
        $this->facilityAccess->assertFacilityAccessible($request->user(), $prescription->facility_id);
        $store = InventoryStore::query()->findOrFail($request->integer('store_id'));

        $this->pharmacyInventory->dispenseWithInventory(
            $prescription,
            $request->user(),
            $store,
            $request->validated('items'),
            $request->validated('notes')
        );

        return back()->with('status', "Dispensing posted for prescription {$prescription->prescription_number}.");
    }

    public function cancel(Request $request, Prescription $prescription): RedirectResponse
    {
        $this->facilityAccess->assertFacilityAccessible($request->user(), $prescription->facility_id);
        $this->pharmacy->cancelPrescription($prescription, $request->user());

        return back()->with('status', "Prescription {$prescription->prescription_number} cancelled.");
    }
}
