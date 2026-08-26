<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePatientRequest;
use App\Models\Patient;
use App\Services\FacilityAccessService;
use App\Services\PatientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function __construct(
        private readonly PatientService $patients,
        private readonly FacilityAccessService $facilityAccess,
    ) {}

    public function index(Request $request): View
    {
        $facility = $this->facilityAccess->currentFacility($request->user());
        $patients = $this->patients->findForSearch($facility->id, $request->string('search')->toString())
            ->paginate(15)
            ->withQueryString();

        return view('patients.index', compact('facility', 'patients'));
    }

    public function create(Request $request): View
    {
        return view('patients.create', [
            'facility' => $this->facilityAccess->currentFacility($request->user()),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $facility = $this->facilityAccess->currentFacility($request->user());
        $patients = $this->patients
            ->findForSearch($facility->id, $validated['q'])
            ->where('status', Patient::STATUS_ACTIVE)
            ->limit(10)
            ->get()
            ->map(fn (Patient $patient) => [
                'id' => $patient->id,
                'full_name' => $patient->full_name,
                'medical_record_number' => $patient->medical_record_number,
                'phone' => $patient->phone,
            ])
            ->values();

        return response()->json([
            'data' => $patients,
            'meta' => [
                'query' => $validated['q'],
                'count' => $patients->count(),
            ],
        ]);
    }

    public function store(StorePatientRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->facilityAccess->assertFacilityAccessible($request->user(), (int) $data['facility_id']);
        $patient = $this->patients->create($data);

        return redirect()->route('patients.show', $patient)->with('status', 'Patient registered successfully.');
    }

    public function show(Patient $patient, Request $request): View
    {
        $this->facilityAccess->assertPatientAccessible($request->user(), $patient);

        return view('patients.show', compact('patient'));
    }
}
