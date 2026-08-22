<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePharmacyPrescriptionRequest;
use App\Models\ClinicalEncounter;
use App\Models\Medication;
use App\Services\PharmacyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PharmacyController extends Controller
{
    public function __construct(private readonly PharmacyService $pharmacy)
    {
    }

    /**
     * Pharmacy-scoped view of an encounter: patient/prescription/dispensing
     * information only, without the rest of the clinical chart. Reached via
     * the dedicated pharmacy.view permission rather than clinical.encounters.view,
     * so pharmacy staff never need broader clinical access.
     */
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
}
