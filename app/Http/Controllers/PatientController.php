<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePatientRequest;
use App\Models\Facility;
use App\Models\Patient;
use App\Services\FacilityAccessService;
use App\Services\PatientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function __construct(
        private readonly PatientService $patients,
        private readonly FacilityAccessService $facilityAccess,
    ) {
    }

    public function index(Request $request): View
    {
        $facility = Facility::query()->where('is_active', true)->orderBy('id')->firstOrFail();
        $patients = $this->patients->findForSearch($facility->id, $request->string('search')->toString())
            ->paginate(15)
            ->withQueryString();

        return view('patients.index', compact('facility', 'patients'));
    }

    public function create(): View
    {
        return view('patients.create', [
            'facility' => Facility::query()->where('is_active', true)->orderBy('id')->firstOrFail(),
        ]);
    }

    public function store(StorePatientRequest $request): RedirectResponse
    {
        $patient = $this->patients->create($request->validated());

        return redirect()->route('patients.show', $patient)->with('status', 'Patient registered successfully.');
    }

    public function show(Patient $patient, Request $request): View
    {
        $this->facilityAccess->assertPatientAccessible($request->user(), $patient);

        return view('patients.show', compact('patient'));
    }
}
