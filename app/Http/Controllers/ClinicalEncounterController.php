<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClinicalEncounterRequest;
use App\Http\Requests\StoreClinicalEncounterSummaryRequest;
use App\Models\Appointment;
use App\Models\BillableService;
use App\Models\ClinicalEncounter;
use App\Models\LaboratoryTest;
use App\Models\Medication;
use App\Services\ClinicalEncounterService;
use App\Services\FacilityAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClinicalEncounterController extends Controller
{
    public function __construct(
        private readonly ClinicalEncounterService $encounters,
        private readonly FacilityAccessService $facilityAccess,
    ) {}

    public function index(Request $request): View
    {
        $facility = $this->facilityAccess->currentFacility($request->user());
        $encounters = ClinicalEncounter::query()
            ->where('facility_id', $facility->id)
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

        $checkedInAppointments = collect();
        if ($request->user()?->hasPermissionTo('clinical.encounters.create')) {
            $checkedInAppointments = Appointment::query()
                ->with(['patient', 'department', 'servicePoint'])
                ->where('facility_id', $facility->id)
                ->where('status', Appointment::STATUS_CHECKED_IN)
                ->whereDoesntHave('clinicalEncounter')
                ->where(function ($query) use ($request) {
                    $query->whereNull('provider_id')
                        ->orWhere('provider_id', $request->user()->id);
                })
                ->orderBy('checked_in_at')
                ->limit(10)
                ->get();
        }

        return view('encounters.index', compact('encounters', 'checkedInAppointments'));
    }

    public function create(Request $request): View
    {
        $facility = $this->facilityAccess->currentFacility($request->user());
        $appointments = Appointment::query()
            ->with(['patient', 'department', 'servicePoint', 'provider'])
            ->where('facility_id', $facility->id)
            ->where('status', Appointment::STATUS_CHECKED_IN)
            ->whereDoesntHave('clinicalEncounter')
            ->where(function ($query) use ($request) {
                $query->whereNull('provider_id')
                    ->orWhere('provider_id', $request->user()->id);
            })
            ->orderBy('scheduled_start')
            ->get();

        $selectedAppointmentId = old('appointment_id', $request->input('appointment_id'));

        return view('encounters.create', compact('appointments', 'selectedAppointmentId'));
    }

    public function store(StoreClinicalEncounterRequest $request): RedirectResponse
    {
        $appointment = Appointment::query()->with('patient')->findOrFail($request->integer('appointment_id'));
        $this->facilityAccess->assertFacilityAccessible($request->user(), $appointment->facility_id);
        $encounter = $this->encounters->open($appointment, $request->user(), $request->validated());

        return redirect()->route('encounters.show', $encounter)->with('status', "Encounter {$encounter->encounter_number} opened successfully.");
    }

    public function show(ClinicalEncounter $encounter, Request $request): View
    {
        $this->facilityAccess->assertEncounterAccessible($request->user(), $encounter);

        $encounter->load([
            'patient', 'appointment', 'department', 'servicePoint', 'clinician',
            'notes' => fn ($query) => $query->with('author')->latest(),
            'vitals' => fn ($query) => $query->with('recorder')->latest(),
            'diagnoses' => fn ($query) => $query->with('recorder')->latest(),
            'treatmentPlans' => fn ($query) => $query->with('author')->latest(),
            'referrals' => fn ($query) => $query->with(['referrer', 'attachments'])->latest(),
            'laboratoryOrders' => fn ($query) => $query->with([
                'orderedBy',
                'items.laboratoryTest',
                'items.result.recordedBy',
            ])->latest('ordered_at'),
            'prescriptions' => fn ($query) => $query->with([
                'prescriber',
                'items.medication',
                'items.formulation',
                'items.dispensingItems.dispensing',
            ])->latest('prescribed_at'),
            'charges' => fn ($query) => $query->with(['billableService', 'servicePrice'])->latest(),
            'invoices' => fn ($query) => $query->with(['lineItems.billableService', 'payments'])->latest(),
        ]);

        $laboratoryTests = collect();
        $pharmacyMedications = collect();
        $billableServices = collect();

        if ($request->user()?->hasPermissionTo('laboratory.orders.create')) {
            $laboratoryTests = LaboratoryTest::query()
                ->where('facility_id', $encounter->facility_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        if ($request->user()?->hasPermissionTo('pharmacy.prescriptions.create')) {
            $pharmacyMedications = Medication::query()
                ->with('formulations')
                ->where('facility_id', $encounter->facility_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        if ($request->user()?->hasPermissionTo('billing.charges.manage')) {
            $today = now()->toDateString();
            $billableServices = BillableService::query()
                ->with(['prices' => fn ($query) => $query
                    ->where('facility_id', $encounter->facility_id)
                    ->where('is_active', true)
                    ->whereDate('effective_from', '<=', $today)
                    ->where(function ($q) use ($today) {
                        $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today);
                    })
                    ->orderByDesc('effective_from')])
                ->where('facility_id', $encounter->facility_id)
                ->where('is_active', true)
                ->whereHas('prices', function ($query) use ($encounter, $today) {
                    $query->where('facility_id', $encounter->facility_id)
                        ->where('is_active', true)
                        ->whereDate('effective_from', '<=', $today)
                        ->where(function ($q) use ($today) {
                            $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today);
                        });
                })
                ->orderBy('name')
                ->get();
        }

        return view('encounters.show', compact('encounter', 'laboratoryTests', 'pharmacyMedications', 'billableServices'));
    }

    public function close(StoreClinicalEncounterSummaryRequest $request, ClinicalEncounter $encounter): RedirectResponse
    {
        $this->facilityAccess->assertEncounterAccessible($request->user(), $encounter);
        $this->encounters->close($encounter, $request->validated('summary'));

        return back()->with('status', "Encounter {$encounter->encounter_number} closed successfully.");
    }

    public function cancel(Request $request, ClinicalEncounter $encounter): RedirectResponse
    {
        $this->facilityAccess->assertEncounterAccessible($request->user(), $encounter);
        $this->encounters->cancel($encounter);

        return back()->with('status', "Encounter {$encounter->encounter_number} cancelled.");
    }
}
