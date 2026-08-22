<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function storeCharge(Request $request, Patient $patient): RedirectResponse
    {
        /** @var User $staff */
        $staff = $request->user();
        $service = \App\Models\BillableService::findOrFail($request->integer('billable_service_id'));
        $price = \App\Models\ServicePrice::findOrFail($request->integer('service_price_id'));
        $this->billing->addCharge($staff, $patient, $service, $price, $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
            'discount_amount' => ['nullable', 'numeric', 'gte:0'],
            'adjustment_amount' => ['nullable', 'numeric'],
            'encounter_id' => ['nullable', 'integer', 'exists:clinical_encounters,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]) + ['encounter' => $request->integer('encounter_id') ? \App\Models\ClinicalEncounter::findOrFail($request->integer('encounter_id')) : null]);

        return back()->with('status', 'Billing charge created successfully.');
    }

    public function storeInvoice(Request $request, Patient $patient): RedirectResponse
    {
        /** @var User $staff */
        $staff = $request->user();
        $validated = $request->validate([
            'charges' => ['required', 'array', 'min:1'],
            'charges.*' => ['required', 'integer', 'distinct', 'exists:charges,id'],
            'discount_amount' => ['nullable', 'numeric', 'gte:0'],
            'adjustment_amount' => ['nullable', 'numeric'],
            'currency' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'encounter_id' => ['nullable', 'integer', 'exists:clinical_encounters,id'],
        ]);

        $this->billing->createInvoice($staff, $patient, $validated['charges'], $validated);

        return back()->with('status', 'Invoice created successfully.');
    }

    public function storePayment(Request $request, Invoice $invoice): RedirectResponse
    {
        /** @var User $staff */
        $staff = $request->user();
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'string', 'in:cash,mobile_money,bank_transfer,card,insurance,other'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->billing->recordPayment($staff, $invoice, (float) $validated['amount'], $validated['method'], $validated);

        return back()->with('status', 'Payment recorded successfully.');
    }

    public function cancelInvoice(Request $request, Invoice $invoice): RedirectResponse
    {
        /** @var User $staff */
        $staff = $request->user();
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->billing->cancelInvoice($staff, $invoice, $validated['reason']);

        return back()->with('status', 'Invoice cancelled successfully.');
    }

    public function refreshInvoice(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->billing->refreshInvoiceTotals($invoice);

        return back()->with('status', 'Invoice totals recalculated successfully.');
    }
}
