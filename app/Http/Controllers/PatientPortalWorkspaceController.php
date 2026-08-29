<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\Invoice;
use App\Models\LaboratoryOrder;
use App\Models\MedicationDispensing;
use App\Models\Payment;
use App\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatientPortalWorkspaceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        if (! $user->isPatient()) {
            abort(403, 'Only patient accounts may access the patient portal.');
        }

        $patient = $user->patientProfile()->with('facility')->first();

        if (! $patient || ! $patient->hasActivePortal()) {
            abort(403, 'This patient account is not linked to active portal access.');
        }

        $upcomingAppointments = Appointment::query()
            ->where('patient_id', $patient->id)
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->where('scheduled_end', '>=', now())
            ->with(['department', 'servicePoint', 'provider'])
            ->orderBy('scheduled_start')
            ->limit(10)
            ->get();

        $appointmentHistory = Appointment::query()
            ->where('patient_id', $patient->id)
            ->where(function ($query): void {
                $query->where('status', '!=', Appointment::STATUS_SCHEDULED)
                    ->orWhere('scheduled_end', '<', now());
            })
            ->with(['department', 'servicePoint', 'provider'])
            ->latest('scheduled_start')
            ->limit(10)
            ->get();

        $careHistory = ClinicalEncounter::query()
            ->where('patient_id', $patient->id)
            ->where('status', ClinicalEncounter::STATUS_CLOSED)
            ->with(['department', 'clinician', 'diagnoses'])
            ->latest('closed_at')
            ->limit(10)
            ->get();

        $laboratoryOrders = LaboratoryOrder::query()
            ->where('patient_id', $patient->id)
            ->where('status', '!=', LaboratoryOrder::STATUS_CANCELLED)
            ->whereHas('items.result')
            ->with([
                'items' => fn ($query) => $query->whereHas('result')->with(['laboratoryTest', 'result']),
            ])
            ->latest('ordered_at')
            ->limit(10)
            ->get();

        $prescriptions = Prescription::query()
            ->where('patient_id', $patient->id)
            ->where('status', '!=', Prescription::STATUS_CANCELLED)
            ->with([
                'prescriber',
                'items.medication',
                'items.formulation',
                'dispensings' => fn ($query) => $query
                    ->where('status', MedicationDispensing::STATUS_COMPLETED)
                    ->latest('dispensed_at'),
            ])
            ->latest('prescribed_at')
            ->limit(10)
            ->get();

        $invoices = Invoice::query()
            ->where('patient_id', $patient->id)
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])
            ->with([
                'lineItems',
                'payments' => fn ($query) => $query
                    ->whereIn('status', [Payment::STATUS_COMPLETED, Payment::STATUS_VOIDED, Payment::STATUS_REFUNDED])
                    ->latest('paid_at'),
            ])
            ->latest('issued_at')
            ->limit(10)
            ->get();

        $notifications = $user->notifications()->latest()->limit(20)->get();
        $unreadNotificationCount = $user->unreadNotifications()->count();

        return view('portal.index', [
            'patient' => $patient,
            'upcomingAppointments' => $upcomingAppointments,
            'appointmentHistory' => $appointmentHistory,
            'careHistory' => $careHistory,
            'laboratoryOrders' => $laboratoryOrders,
            'prescriptions' => $prescriptions,
            'invoices' => $invoices,
            'outstandingBalance' => $invoices->sum(
                fn (Invoice $invoice): float => $invoice->isCancelled() ? 0 : (float) $invoice->balance_due,
            ),
            'notifications' => $notifications,
            'unreadNotificationCount' => $unreadNotificationCount,
        ]);
    }
}
