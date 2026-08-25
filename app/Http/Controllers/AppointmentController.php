<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentService $appointments) {}

    public function index(Request $request): View
    {
        $appointments = Appointment::query()
            ->with(['patient', 'department', 'servicePoint', 'provider'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('scheduled_start', $request->string('date')->toString()))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($q) use ($search) {
                    $q->where('appointment_number', 'like', "%{$search}%")
                        ->orWhereHas('patient', fn ($patient) => $patient
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('medical_record_number', 'like', "%{$search}%"));
                });
            })
            ->orderBy('scheduled_start')
            ->paginate(20)
            ->withQueryString();

        return view('appointments.index', compact('appointments'));
    }

    public function create(Request $request): View
    {
        $facility = Facility::query()->where('is_active', true)->orderBy('id')->firstOrFail();
        $departments = Department::query()
            ->where('facility_id', $facility->id)
            ->where('is_active', true)
            ->with(['servicePoints' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $selectedPatientId = old('patient_id', $request->input('patient_id'));
        $selectedPatient = $selectedPatientId
            ? Patient::query()
                ->where('facility_id', $facility->id)
                ->where('status', Patient::STATUS_ACTIVE)
                ->find($selectedPatientId)
            : null;
        $providers = User::query()->where('user_type', 'staff')->where('is_active', true)->orderBy('name')->get();

        return view('appointments.create', compact('facility', 'departments', 'providers', 'selectedPatient'));
    }

    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        $appointment = $this->appointments->create($request->appointmentData());

        return redirect()->route('appointments.index')->with('status', "Appointment {$appointment->appointment_number} scheduled successfully.");
    }

    public function cancel(Appointment $appointment): RedirectResponse
    {
        if (in_array($appointment->status, [Appointment::STATUS_CANCELLED, Appointment::STATUS_COMPLETED], true)) {
            throw ValidationException::withMessages(['appointment' => 'This appointment cannot be cancelled in its current state.']);
        }

        $appointment->forceFill([
            'status' => Appointment::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ])->save();

        return back()->with('status', "Appointment {$appointment->appointment_number} cancelled.");
    }

    public function checkIn(Appointment $appointment): RedirectResponse
    {
        if ($appointment->status !== Appointment::STATUS_SCHEDULED) {
            throw ValidationException::withMessages(['appointment' => 'Only scheduled appointments can be checked in.']);
        }

        $appointment->forceFill([
            'status' => Appointment::STATUS_CHECKED_IN,
            'checked_in_at' => now(),
        ])->save();

        return back()->with('status', "Appointment {$appointment->appointment_number} checked in.");
    }

    public function complete(Appointment $appointment): RedirectResponse
    {
        if ($appointment->status !== Appointment::STATUS_CHECKED_IN) {
            throw ValidationException::withMessages(['appointment' => 'Only checked-in appointments can be completed.']);
        }

        $appointment->forceFill([
            'status' => Appointment::STATUS_COMPLETED,
            'completed_at' => now(),
        ])->save();

        return back()->with('status', "Appointment {$appointment->appointment_number} completed.");
    }
}
