<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelBillingInvoiceRequest;
use App\Http\Requests\StoreBillingChargeRequest;
use App\Http\Requests\StoreBillingInvoiceRequest;
use App\Http\Requests\StoreBillingPaymentRequest;
use App\Models\BillableService;
use App\Models\Charge;
use App\Models\ClinicalEncounter;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\ServicePrice;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function __construct(private readonly BillingService $billing)
    {
    }

    public function show(Patient $patient): View
    {
        $patient->load([
            'facility',
            'charges' => fn ($query) => $query->with(['billableService', 'servicePrice', 'encounter'])->latest(),
            'invoices' => fn ($query) => $query->with(['lineItems.billableService', 'payments'])->latest(),
        ]);

        return view('billing.show', compact('patient'));
    }

    public function storeCharge(StoreBillingChargeRequest $request, Patient $patient): RedirectResponse
    {
        /** @var User $staff */
        $staff = $request->user();
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
        $validated = $request->validated();

        $this->billing->createInvoice($staff, $patient, $validated['charges'], $validated);

        return back()->with('status', 'Invoice created successfully.');
    }

    public function storePayment(StoreBillingPaymentRequest $request, Invoice $invoice): RedirectResponse
    {
        /** @var User $staff */
        $staff = $request->user();
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
        $this->billing->cancelInvoice($staff, $invoice, $request->validated('reason'));

        return back()->with('status', 'Invoice cancelled successfully.');
    }

    public function refreshInvoice(Invoice $invoice): RedirectResponse
    {
        $this->billing->refreshInvoiceTotals($invoice);

        return back()->with('status', 'Invoice totals recalculated successfully.');
    }
}
