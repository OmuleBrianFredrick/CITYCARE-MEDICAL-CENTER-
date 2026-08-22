<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePharmacyPrescriptionRequest;
use App\Models\ClinicalEncounter;
use App\Models\Medication;
use App\Services\PharmacyService;
use Illuminate\Http\RedirectResponse;

class PharmacyController extends Controller
{
    public function __construct(private readonly PharmacyService $pharmacy)
    {
    }

    public function store(StorePharmacyPrescriptionRequest $request, ClinicalEncounter $encounter): RedirectResponse
    {
        $this->pharmacy->prescribe($encounter, $request->user(), $request->validated());

        return back()->with('status', "Prescription created for encounter {$encounter->encounter_number}.");
    }
}
