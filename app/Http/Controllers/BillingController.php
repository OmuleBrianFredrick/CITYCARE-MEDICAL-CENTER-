<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelBillingInvoiceRequest;
use App\Http\Requests\ReverseBillingPaymentRequest;
use App\Http\Requests\StoreBillingChargeRequest;
use App\Http\Requests\StoreBillingInvoiceRequest;
use App\Http\Requests\StoreBillingPaymentRequest;
use App\Http\Requests\VoidBillingChargeRequest;
use App\Models\BillableService;
use App\Models\Charge;
use App\Models\ClinicalEncounter;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\ServicePrice;
use App\Models\User;
use App\Services\BillingService;
use App\Services\FacilityAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly FacilityAccessService $facilities,
    ) {}

    public function index(Request $request): View
    {
        $facility = $this->facilities->currentFacility($request->user());
        $status = strtolower($request->string('status', 'open')->toString());
        $status = in_array($status, ['open', 'paid', 'cancelled', 'all'], true) ? $status : 'open';
        $search = trim($request->string('search')->toString());

        $invoices = Invoice::query()
            ->where('facility_id', $facility->id)
            ->with(['patient', 'lineItems.billableService', 'payments.receivedBy'])
            ->when($status === 'open', fn ($query) => $query->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID]))
            ->when($status === 'paid', fn ($query) => $query->where('status', Invoice::STATUS_PAID))
            ->when($status === 'cancelled', fn ($query) => $query->where('status', Invoice::STATUS_CANCELLED))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('patient', function ($patientQuery) use ($search) {
                            $patientQuery->where('medical_record_number', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('middle_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('issued_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $pendingCharges = Charge::query()
            ->where('facility_id', $facility->id)
            ->where('status', Charge::STATUS_PENDING)
            ->with(['patient', 'billableService'])
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('patient', function ($patientQuery) use ($search) {
                    $patientQuery->where('medical_record_number', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->limit(12)
            ->get();

        $outstandingBalance = Invoice::query()
            ->where('facility_id', $facility->id)
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID])
            ->sum('balance_due');

        $paymentsToday = Payment::query()
            ->where('status', Payment::STATUS_COMPLETED)
            ->whereDate('paid_at', today())
            ->whereHas('invoice', fn ($query) => $query->where('facility_id', $facility->id))
            ->sum('amount');

        $issuedToday = Invoice::query()
            ->where('facility_id', $facility->id)
            ->whereDate('issued_at', today())
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->sum('total');

        return view('billing.index', compact(
            'facility',
            'status',
            'invoices',
            'pendingCharges',
            'outstandingBalance',
            'paymentsToday',
            'issuedToday',
        ));
    }

    public function show(Request $request, Patient $patient): View
    {
        $this->facilities->assertPatientAccessible($request->user(), $patient);

        $patient->load([
            'facility',
            'charges' => fn ($query) => $query->with(['billableService', 'servicePrice', 'encounter'])->latest(),
            'invoices' => fn ($query) => $query->with(['lineItems.billableService', 'payments.receivedBy', 'payments.voidedBy'])->latest(),
        ]);

        $today = today()->toDateString();
        $servicePrices = ServicePrice::query()
            ->where('facility_id', $patient->facility_id)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))
            ->whereHas('billableService', fn ($query) => $query->where('is_active', true))
            ->with('billableService')
            ->orderByDesc('effective_from')
            ->get()
            ->unique('billable_service_id')
            ->sortBy(fn (ServicePrice $price) => $price->billableService?->name)
            ->values();

        $openEncounters = ClinicalEncounter::query()
            ->where('patient_id', $patient->id)
            ->where('facility_id', $patient->facility_id)
            ->where('status', ClinicalEncounter::STATUS_OPEN)
            ->latest('started_at')
            ->get();

        return view('billing.show', compact('patient', 'servicePrices', 'openEncounters'));
    }

    public function storeCharge(StoreBillingChargeRequest $request, Patient $patient): RedirectResponse
    {
        /** @var User $staff */
        $staff = $request->user();
        $this->facilities->assertPatientAccessible($staff, $patient);
        $validated = $request->validated();
        $service = BillableService::findOrFail($validated['billable_service_id']);
        $price = ServicePrice::findOrFail($validated['service_price_id']);
        $encounter = isset($validated['encounter_id'])
            ? ClinicalEncounter::findOrFail($validated['encounter_id'])
            : null;

        $this->billing->addCharge($staff, $patient, $service, $price, $validated + ['encounter' => $encounter]);

        return back()->with('status', 'Billing charge created successfully.');
    }

    public function storeInvoice(StoreBillingInvoiceRequest $request, Patient $patient): RedirectResponse
    {
        /** @var User $staff */
        $staff = $request->user();
        $this->facilities->assertPatientAccessible($staff, $patient);
        $validated = $request->validated();

        $this->billing->createInvoice($staff, $patient, $validated['charges'], $validated);

        return back()->with('status', 'Invoice created successfully.');
    }

    public function storePayment(StoreBillingPaymentRequest $request, Invoice $invoice): RedirectResponse
    {
        /** @var User $staff */
        $staff = $request->user();
        $this->facilities->assertFacilityAccessible($staff, $invoice->facility_id);
        $validated = $request->validated();

        $this->billing->recordPayment(
            $staff,
            $invoice,
            (float) $validated['amount'],
            $validated['method'],
            $validated,
        );

        return back()->with('status', 'Payment recorded successfully.');
    }

    public function cancelInvoice(CancelBillingInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        /** @var User $staff */
        $staff = $request->user();
        $this->facilities->assertFacilityAccessible($staff, $invoice->facility_id);
        $this->billing->cancelInvoice($staff, $invoice, $request->validated('reason'));

        return back()->with('status', 'Invoice cancelled successfully.');
    }

    public function voidCharge(VoidBillingChargeRequest $request, Charge $charge): RedirectResponse
    {
        $this->facilities->assertFacilityAccessible($request->user(), $charge->facility_id);
        $this->billing->voidCharge($request->user(), $charge, $request->validated('reason'));

        return back()->with('status', 'Pending charge voided successfully.');
    }

    public function reversePayment(ReverseBillingPaymentRequest $request, Payment $payment): RedirectResponse
    {
        $payment->loadMissing('invoice');
        $this->facilities->assertFacilityAccessible($request->user(), $payment->invoice->facility_id);
        $this->billing->reversePayment(
            $request->user(),
            $payment,
            $request->validated('action'),
            $request->validated('reason'),
        );

        return back()->with('status', 'Payment reversal recorded successfully.');
    }

    public function refreshInvoice(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->facilities->assertFacilityAccessible($request->user(), $invoice->facility_id);
        $this->billing->refreshInvoiceTotals($invoice);

        return back()->with('status', 'Invoice totals recalculated successfully.');
    }
}
