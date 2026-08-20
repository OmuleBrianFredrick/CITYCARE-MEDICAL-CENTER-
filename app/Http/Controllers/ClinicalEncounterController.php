<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClinicalEncounterRequest;
use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Services\ClinicalEncounterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClinicalEncounterController extends Controller
{
    public function __construct(private readonly ClinicalEncounterService $encounters)
    {
    }

    public function index(Request $request): View
    {
        $encounters = ClinicalEncounter::query()
            ->with(['patient', 'appointment', 'department', 'servicePoint', 'clinician'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($q) use ($search) {
                    $q->where('encounter_number', 'like', "%{$search}%")
                        ->orWhereHas('patient', fn ($patient) => $patient
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('medical_record_number', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('started_at')
            ->paginate(20)
            ->withQueryString();

        return view('encounters.index', compact('encounters'));
    }

    public function create(): View
    {
        $appointments = Appointment::query()->with(['patient', 'department', 'servicePoint', 'provider'])->where('status', Appointment::STATUS_CHECKED_IN)->orderBy('scheduled_start')->get();

        return view('encounters.create', compact('appointments'));
    }

    public function store(StoreClinicalEncounterRequest $request): RedirectResponse
    {
        $appointment = Appointment::query()->with('patient')->findOrFail($request->integer('appointment_id'));
        $encounter = $this->encounters->open($appointment, $request->user());

        return redirect()->route('encounters.show', $encounter)->with('status', "Encounter {$encounter->encounter_number} opened successfully.");
    }

    public function show(ClinicalEncounter $encounter): View
    {
        $encounter->load([
            'patient', 'appointment', 'department', 'servicePoint', 'clinician',
            'diagnoses' => fn ($query) => $query->with('recorder')->latest(),
        ]);

        return view('encounters.show', compact('encounter'));
    }

    public function close(Request $request, ClinicalEncounter $encounter): RedirectResponse
    {
        $this->encounters->close($encounter, $request->input('summary'));

        return back()->with('status', "Encounter {$encounter->encounter_number} closed successfully.");
    }

    public function cancel(ClinicalEncounter $encounter): RedirectResponse
    {
        $this->encounters->cancel($encounter);

        return back()->with('status', "Encounter {$encounter->encounter_number} cancelled.");
    }
}
