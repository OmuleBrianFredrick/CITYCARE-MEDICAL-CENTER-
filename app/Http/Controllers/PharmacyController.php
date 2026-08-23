<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePharmacyDispensingRequest;
use App\Http\Requests\StorePharmacyPrescriptionRequest;
use App\Models\ClinicalEncounter;
use App\Models\InventoryStore;
use App\Models\Medication;
use App\Models\Prescription;
use App\Services\PharmacyInventoryDispensingService;
use App\Services\PharmacyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PharmacyController extends Controller
{
    public function __construct(
        private readonly PharmacyService $pharmacy,
        private readonly PharmacyInventoryDispensingService $pharmacyInventory
    ) {
    }

    public function show(ClinicalEncounter $encounter): View
    {
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
        $this->pharmacy->prescribe($encounter, $request->user(), $request->validated());

        return back()->with('status', "Prescription created for encounter {$encounter->encounter_number}.");
    }

    public function dispense(StorePharmacyDispensingRequest $request, Prescription $prescription): RedirectResponse
    {
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
}
