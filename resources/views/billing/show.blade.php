@extends('layouts.app')

@section('title', 'Patient Billing · CityCare Medical Center')

@push('styles')
<style>
    .account-page{max-width:1280px;padding:clamp(24px,4vw,42px)}.account-heading{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px}.account-eyebrow{margin:0 0 7px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.14em}.account-heading h1{margin:0;font-size:clamp(1.8rem,4vw,2.55rem);letter-spacing:-.045em}.account-heading p{margin:8px 0 0;color:var(--muted);line-height:1.5}.account-actions{display:flex;gap:9px;flex-wrap:wrap;justify-content:flex-end}.account-link{display:inline-flex;padding:9px 12px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--blue);font-size:.79rem;font-weight:850;text-decoration:none}.account-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:18px}.account-stat{padding:19px}.account-stat span{display:block;color:var(--muted);font-size:.7rem;font-weight:850;text-transform:uppercase;letter-spacing:.08em}.account-stat strong{display:block;margin-top:7px;font-size:1.25rem}.account-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,.72fr);gap:18px;align-items:start}.account-stack{display:grid;gap:18px}.account-panel{padding:22px}.account-panel h2{margin:0;font-size:1.1rem}.account-panel>p{margin:6px 0 0;color:var(--muted);font-size:.82rem;line-height:1.5}.account-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:17px}.account-form label{display:grid;gap:6px;color:#334155;font-size:.76rem;font-weight:850}.account-form input,.account-form select,.account-form textarea{width:100%;min-width:0;padding:10px 11px;border:1px solid var(--line);border-radius:9px;background:#fff}.account-form .full{grid-column:1/-1}.account-form button,.invoice-action{border:0;border-radius:9px;background:var(--blue);color:#fff;padding:10px 13px;font-size:.8rem;font-weight:850;cursor:pointer}.charge-row{padding:14px 0;border-bottom:1px solid var(--line)}.charge-top,.invoice-top{display:flex;justify-content:space-between;gap:14px;align-items:flex-start}.charge-row h3,.invoice-card h3{margin:0;font-size:.94rem}.charge-row p,.invoice-card p{margin:5px 0 0;color:var(--muted);font-size:.79rem;line-height:1.5}.account-status{display:inline-block;padding:6px 9px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:.69rem;font-weight:850;white-space:nowrap}.charge-choice{display:flex;gap:10px;align-items:flex-start;padding:11px 0;border-bottom:1px solid var(--line);font-size:.82rem}.charge-choice input{margin-top:3px}.invoice-card{margin-top:16px;padding:19px;border:1px solid var(--line);border-radius:12px}.invoice-totals{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-top:13px}.invoice-totals div{padding:10px;border-radius:9px;background:#f8fafc}.invoice-totals span{display:block;color:var(--muted);font-size:.67rem;font-weight:850;text-transform:uppercase}.invoice-totals strong{display:block;margin-top:4px;font-size:.84rem}.line-items{margin-top:13px;padding-top:11px;border-top:1px solid var(--line)}.line-item,.receipt{display:flex;justify-content:space-between;gap:14px;padding:7px 0;color:#475569;font-size:.78rem}.receipt-list{margin-top:13px;padding-top:11px;border-top:1px solid var(--line)}.receipt{display:block}.receipt strong{color:#0f172a}.invoice-controls{display:flex;gap:9px;flex-wrap:wrap;margin-top:14px}.invoice-controls form{margin:0}.invoice-action.secondary{background:#fff;color:var(--blue);border:1px solid #bfdbfe}.invoice-action.danger{background:#fff;color:#b91c1c;border:1px solid #fecaca}.cancel-form{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:9px;width:100%;margin-top:10px!important}.cancel-form input{min-width:0;padding:9px 10px;border:1px solid var(--line);border-radius:8px}.account-empty{padding:22px 4px;color:var(--muted);text-align:center;font-size:.83rem}@media(max-width:940px){.account-grid{grid-template-columns:1fr}.account-summary{grid-template-columns:1fr}}@media(max-width:700px){.account-page{padding:24px 18px}.account-heading{flex-direction:column}.account-actions{justify-content:flex-start}.account-form{grid-template-columns:1fr}.account-form .full{grid-column:auto}.charge-top,.invoice-top{flex-direction:column;gap:8px}.invoice-totals{grid-template-columns:1fr}.cancel-form{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
@php
    $pendingCharges = $patient->charges->where('status', \App\Models\Charge::STATUS_PENDING);
    $openInvoices = $patient->invoices->whereIn('status', [\App\Models\Invoice::STATUS_ISSUED, \App\Models\Invoice::STATUS_PARTIALLY_PAID]);
    $outstanding = $openInvoices->sum(fn ($invoice) => (float) $invoice->balance_due);
    $paid = $patient->invoices->sum(fn ($invoice) => (float) $invoice->paid_amount);
@endphp
<section class="account-page">
    <div class="account-heading">
        <div>
            <p class="account-eyebrow">PATIENT BILLING ACCOUNT</p>
            <h1>{{ $patient->full_name }}</h1>
            <p>{{ $patient->medical_record_number }} · {{ $patient->facility->name }}</p>
        </div>
        <div class="account-actions">
            <a class="account-link" href="{{ route('billing.index') }}">← Billing queue</a>
            @if (auth()->user()->hasPermissionTo('patients.view'))<a class="account-link" href="{{ route('patients.show', $patient) }}">Patient record</a>@endif
        </div>
    </div>

    @if (session('status'))<div class="status" style="margin-bottom:18px">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="error" style="margin-bottom:18px">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <div class="account-summary">
        <article class="card account-stat"><span>Pending charges</span><strong>{{ $pendingCharges->count() }}</strong></article>
        <article class="card account-stat"><span>Outstanding balance</span><strong>{{ $patient->facility->currency }} {{ number_format($outstanding, 2) }}</strong></article>
        <article class="card account-stat"><span>Total payments recorded</span><strong>{{ $patient->facility->currency }} {{ number_format($paid, 2) }}</strong></article>
    </div>

    <div class="account-grid">
        <div class="account-stack">
            @if (auth()->user()->hasPermissionTo('billing.charges.manage'))
                <section class="card account-panel">
                    <h2>Create charge</h2>
                    <p>Add a current facility service to this patient account. Pricing is taken from the selected effective service price.</p>
                    @if ($servicePrices->isNotEmpty())
                        <form class="account-form" method="POST" action="{{ route('billing.charges.store', $patient) }}" data-charge-form>
                            @csrf
                            <input type="hidden" name="billable_service_id" value="{{ old('billable_service_id') }}" data-service-id>
                            <label class="full">Service and current price
                                <select name="service_price_id" required data-service-price>
                                    <option value="">Select a billable service</option>
                                    @foreach ($servicePrices as $price)
                                        <option value="{{ $price->id }}" data-service-id="{{ $price->billable_service_id }}" @selected((string) old('service_price_id') === (string) $price->id)>{{ $price->billableService->name }} · {{ $price->currency }} {{ number_format((float) $price->amount, 2) }} / {{ $price->billableService->unit }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>Quantity<input type="number" name="quantity" min="0.001" step="0.001" value="{{ old('quantity', 1) }}" required></label>
                            <label>Open encounter (optional)<select name="encounter_id"><option value="">No encounter link</option>@foreach ($openEncounters as $encounter)<option value="{{ $encounter->id }}" @selected((string) old('encounter_id') === (string) $encounter->id)>{{ $encounter->encounter_number }} · {{ ucfirst($encounter->type) }}</option>@endforeach</select></label>
                            <label>Charge discount<input type="number" name="discount_amount" min="0" step="0.01" value="{{ old('discount_amount', 0) }}"></label>
                            <label>Adjustment (+/−)<input type="number" name="adjustment_amount" step="0.01" value="{{ old('adjustment_amount', 0) }}"></label>
                            <label class="full">Description<input name="description" maxlength="255" value="{{ old('description') }}" placeholder="Optional charge description"></label>
                            <div class="full"><button type="submit">Add charge</button></div>
                        </form>
                    @else
                        <div class="account-empty">No active service prices are available for this facility.</div>
                    @endif
                </section>
            @endif

            <section class="card account-panel">
                <h2>Charges</h2>
                <p>Clinical and administrative services recorded against this patient.</p>
                @forelse ($patient->charges as $charge)
                    <article class="charge-row">
                        <div class="charge-top">
                            <div>
                                <h3>{{ $charge->billableService?->name ?? 'Billable service' }}</h3>
                                <p>{{ $charge->description }} · Qty {{ $charge->quantity }} · {{ $charge->currency }} {{ number_format((float) $charge->total, 2) }}@if ($charge->encounter)<br>Encounter {{ $charge->encounter->encounter_number }}@endif</p>
                            </div>
                            <span class="account-status">{{ ucfirst($charge->status) }}</span>
                        </div>
                        @if ($charge->isPending() && auth()->user()->hasPermissionTo('billing.work.manage'))
                            <form class="cancel-form" method="POST" action="{{ route('billing.charges.void', $charge) }}">@csrf<input name="reason" maxlength="2000" required placeholder="Reason for voiding this pending charge"><button class="invoice-action danger" type="submit">Void charge</button></form>
                        @endif
                        @if ($charge->isVoided() && $charge->void_reason)<p style="color:#991b1b"><strong>Void reason:</strong> {{ $charge->void_reason }}</p>@endif
                    </article>
                @empty
                    <div class="account-empty">No charges have been recorded for this patient.</div>
                @endforelse
            </section>
        </div>

        <div class="account-stack">
            @if ($pendingCharges->isNotEmpty() && auth()->user()->hasPermissionTo('billing.invoices.manage'))
                <section class="card account-panel">
                    <h2>Create invoice</h2>
                    <p>Select one or more pending charges. The invoice currency is derived from those charges.</p>
                    <form class="account-form" method="POST" action="{{ route('billing.invoices.store', $patient) }}">
                        @csrf
                        <div class="full">
                            @foreach ($pendingCharges as $charge)
                                <label class="charge-choice"><input type="checkbox" name="charges[]" value="{{ $charge->id }}" @checked(in_array($charge->id, old('charges', [])))><span><strong>{{ $charge->billableService?->name ?? $charge->description }}</strong><br>{{ $charge->currency }} {{ number_format((float) $charge->total, 2) }}</span></label>
                            @endforeach
                        </div>
                        <label>Invoice discount<input type="number" name="discount_amount" min="0" step="0.01" value="{{ old('discount_amount', 0) }}"></label>
                        <label>Adjustment (+/−)<input type="number" name="adjustment_amount" step="0.01" value="{{ old('adjustment_amount', 0) }}"></label>
                        <label class="full">Open encounter (optional)<select name="encounter_id"><option value="">Use charge encounter</option>@foreach ($openEncounters as $encounter)<option value="{{ $encounter->id }}" @selected((string) old('encounter_id') === (string) $encounter->id)>{{ $encounter->encounter_number }} · {{ ucfirst($encounter->type) }}</option>@endforeach</select></label>
                        <label class="full">Notes<textarea name="notes" rows="2" maxlength="2000">{{ old('notes') }}</textarea></label>
                        <div class="full"><button type="submit">Create and issue invoice</button></div>
                    </form>
                </section>
            @endif
        </div>
    </div>

    <section class="card account-panel" style="margin-top:18px">
        <h2>Invoices and receipts</h2>
        <p>Review line items, balances, payment receipts, and authorized invoice actions.</p>

        @forelse ($patient->invoices as $invoice)
            <article class="invoice-card">
                <div class="invoice-top">
                    <div><h3>{{ $invoice->invoice_number }}</h3><p>Issued {{ $invoice->issued_at?->format('d M Y H:i') ?? 'not dated' }}@if ($invoice->notes) · {{ $invoice->notes }}@endif</p></div>
                    <span class="account-status">{{ str_replace('_', ' ', ucfirst($invoice->status)) }}</span>
                </div>
                <div class="invoice-totals">
                    <div><span>Total</span><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</strong></div>
                    <div><span>Paid</span><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->paid_amount, 2) }}</strong></div>
                    <div><span>Balance</span><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->balance_due, 2) }}</strong></div>
                </div>
                <div class="line-items">
                    @foreach ($invoice->lineItems as $item)
                        <div class="line-item"><span>{{ $item->description }} · {{ $item->quantity }} × {{ number_format((float) $item->unit_price, 2) }}</span><strong>{{ $item->currency }} {{ number_format((float) $item->line_total, 2) }}</strong></div>
                    @endforeach
                </div>

                @if ($invoice->payments->isNotEmpty())
                    <div class="receipt-list">
                        @foreach ($invoice->payments as $payment)
                            <div class="receipt"><strong>Receipt {{ $payment->receipt_number }}</strong> · {{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }} · {{ str_replace('_', ' ', ucfirst($payment->method)) }} · <span class="account-status">{{ ucfirst($payment->status) }}</span><br><span>{{ $payment->paid_at?->format('d M Y H:i') }} · Received by {{ $payment->receivedBy?->name ?? 'Unknown user' }}@if ($payment->transaction_reference) · Ref {{ $payment->transaction_reference }}@endif</span>
                                @if ($payment->isCompleted() && auth()->user()->hasPermissionTo('billing.work.manage'))
                                    <form class="account-form" style="margin-top:10px" method="POST" action="{{ route('billing.payments.reverse', $payment) }}">@csrf<label>Reversal type<select name="action" required><option value="void">Void erroneous posting</option><option value="refund">Refund completed payment</option></select></label><label>Reason<input name="reason" maxlength="2000" required></label><div class="full"><button class="invoice-action danger" type="submit">Record reversal</button></div></form>
                                @elseif (in_array($payment->status, [\App\Models\Payment::STATUS_VOIDED, \App\Models\Payment::STATUS_REFUNDED], true))
                                    <p style="color:#991b1b"><strong>{{ ucfirst($payment->status) }}:</strong> {{ $payment->void_reason }} · {{ $payment->voidedBy?->name ?? 'Unknown user' }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if (in_array($invoice->status, [\App\Models\Invoice::STATUS_ISSUED, \App\Models\Invoice::STATUS_PARTIALLY_PAID], true) && auth()->user()->hasPermissionTo('billing.payments.record'))
                    <form class="account-form" method="POST" action="{{ route('billing.payments.store', $invoice) }}">
                        @csrf
                        <label>Payment amount<input type="number" name="amount" min="0.01" max="{{ $invoice->balance_due }}" step="0.01" value="{{ old('amount', $invoice->balance_due) }}" required></label>
                        <label>Payment method<select name="method" required>@foreach (['cash' => 'Cash', 'mobile_money' => 'Mobile money', 'bank_transfer' => 'Bank transfer', 'card' => 'Card', 'insurance' => 'Insurance', 'other' => 'Other'] as $value => $label)<option value="{{ $value }}" @selected(old('method', 'cash') === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label>Transaction reference<input name="transaction_reference" maxlength="120" value="{{ old('transaction_reference') }}"></label>
                        <label>Payment notes<input name="notes" maxlength="2000" value="{{ old('notes') }}"></label>
                        <div class="full"><button type="submit">Record payment and issue receipt</button></div>
                    </form>
                @endif

                @if (in_array($invoice->status, [\App\Models\Invoice::STATUS_ISSUED, \App\Models\Invoice::STATUS_PARTIALLY_PAID], true) && auth()->user()->hasPermissionTo('billing.work.manage'))
                    <div class="invoice-controls">
                        <form method="POST" action="{{ route('billing.invoices.refresh', $invoice) }}">@csrf<button class="invoice-action secondary" type="submit">Recalculate totals</button></form>
                        @if ((float) $invoice->paid_amount === 0.0)
                            <form class="cancel-form" method="POST" action="{{ route('billing.invoices.cancel', $invoice) }}">@csrf<input name="reason" maxlength="1000" required placeholder="Cancellation reason"><button class="invoice-action danger" type="submit">Cancel invoice</button></form>
                        @endif
                    </div>
                @endif
                @if ($invoice->isCancelled() && $invoice->cancel_reason)<p style="margin-top:12px;color:#991b1b"><strong>Cancellation:</strong> {{ $invoice->cancel_reason }}</p>@endif
            </article>
        @empty
            <div class="account-empty">No invoices have been created for this patient.</div>
        @endforelse
    </section>
</section>

@push('scripts')
<script>
    document.querySelectorAll('[data-charge-form]').forEach((form) => {
        const price = form.querySelector('[data-service-price]');
        const service = form.querySelector('[data-service-id]');
        const syncService = () => { service.value = price.selectedOptions[0]?.dataset.serviceId || ''; };
        price.addEventListener('change', syncService);
        syncService();
    });
</script>
@endpush
@endsection
