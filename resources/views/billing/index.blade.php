@extends('layouts.app')

@section('title', 'Billing Workspace · CityCare Medical Center')

@push('styles')
<style>
    .billing-page{max-width:1280px;padding:clamp(24px,4vw,42px)}.billing-heading{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px}.billing-eyebrow{margin:0 0 7px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.14em}.billing-heading h1{margin:0;font-size:clamp(1.85rem,4vw,2.65rem);letter-spacing:-.045em}.billing-heading p{max-width:760px;margin:9px 0 0;color:var(--muted);line-height:1.55}.billing-meta{padding:9px 11px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--muted);font-size:.82rem;white-space:nowrap}.billing-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:18px}.billing-stat{padding:20px}.billing-stat span{display:block;color:var(--muted);font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em}.billing-stat strong{display:block;margin-top:8px;font-size:1.45rem}.billing-filter{display:grid;grid-template-columns:minmax(0,1fr) 180px auto;gap:10px;margin-bottom:18px;padding:18px}.billing-filter input,.billing-filter select{min-width:0;padding:11px 12px;border:1px solid var(--line);border-radius:10px;background:#fff}.billing-filter button{border:0;border-radius:10px;background:var(--blue);color:#fff;padding:11px 16px;font-weight:800;cursor:pointer}.billing-grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(280px,.8fr);gap:18px;align-items:start}.billing-panel{padding:22px}.billing-panel-heading{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;margin-bottom:15px}.billing-panel-heading h2{margin:0;font-size:1.08rem}.billing-panel-heading p{margin:5px 0 0;color:var(--muted);font-size:.82rem;line-height:1.5}.billing-count{padding:6px 9px;border-radius:999px;background:#f1f5f9;color:#475569;font-size:.72rem;font-weight:850;white-space:nowrap}.invoice-card{padding:16px 0;border-bottom:1px solid var(--line)}.invoice-card:first-of-type{padding-top:4px}.invoice-top{display:flex;justify-content:space-between;gap:15px;align-items:flex-start}.invoice-card h3{margin:0;font-size:.96rem}.invoice-card p{margin:5px 0 0;color:var(--muted);font-size:.8rem;line-height:1.45}.billing-status{display:inline-block;padding:6px 9px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:.7rem;font-weight:850;white-space:nowrap}.invoice-money{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-top:13px}.invoice-money div{padding:10px;border-radius:9px;background:#f8fafc}.invoice-money span{display:block;color:var(--muted);font-size:.68rem;font-weight:800;text-transform:uppercase}.invoice-money strong{display:block;margin-top:4px;font-size:.86rem}.billing-link{display:inline-flex;margin-top:12px;color:var(--blue);font-size:.8rem;font-weight:850;text-decoration:none}.charge-card{padding:13px 0;border-bottom:1px solid var(--line)}.charge-card h3{margin:0;font-size:.9rem}.charge-card p{margin:5px 0 0;color:var(--muted);font-size:.78rem;line-height:1.45}.billing-empty{padding:28px 8px;color:var(--muted);text-align:center}.billing-pagination{margin-top:18px}@media(max-width:900px){.billing-grid{grid-template-columns:1fr}.billing-summary{grid-template-columns:1fr}}@media(max-width:700px){.billing-page{padding:24px 18px}.billing-heading{flex-direction:column}.billing-filter{grid-template-columns:1fr}.billing-filter button{width:100%}.invoice-top{flex-direction:column;gap:8px}.invoice-money{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<section class="billing-page">
    <div class="billing-heading">
        <div>
            <p class="billing-eyebrow">BILLING & PAYMENTS</p>
            <h1>Financial work queue</h1>
            <p>Find patient accounts, turn pending charges into invoices, record payments, and follow every outstanding balance through completion.</p>
        </div>
        <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
            @if (auth()->user()->hasPermissionTo('billing.manage'))<a class="billing-meta" style="color:var(--blue);font-weight:850;text-decoration:none" href="{{ route('billing.catalogue.index') }}">Service & price catalogue</a>@endif
            <span class="billing-meta">{{ $facility->name }}</span>
        </div>
    </div>

    @if (session('status'))<div class="status" style="margin-bottom:18px">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="error" style="margin-bottom:18px">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <div class="billing-summary">
        <article class="card billing-stat"><span>Outstanding balance</span><strong>{{ $facility->currency }} {{ number_format((float) $outstandingBalance, 2) }}</strong></article>
        <article class="card billing-stat"><span>Payments today</span><strong>{{ $facility->currency }} {{ number_format((float) $paymentsToday, 2) }}</strong></article>
        <article class="card billing-stat"><span>Invoices issued today</span><strong>{{ $facility->currency }} {{ number_format((float) $issuedToday, 2) }}</strong></article>
    </div>

    <form class="card billing-filter" method="GET" action="{{ route('billing.index') }}">
        <input name="search" value="{{ request('search') }}" placeholder="Search invoice, MRN, or patient name" aria-label="Search billing records">
        <select name="status" aria-label="Filter invoice status">
            <option value="open" @selected($status === 'open')>Outstanding</option>
            <option value="paid" @selected($status === 'paid')>Paid</option>
            <option value="cancelled" @selected($status === 'cancelled')>Cancelled</option>
            <option value="all" @selected($status === 'all')>All invoices</option>
        </select>
        <button type="submit">Filter billing</button>
    </form>

    <div class="billing-grid">
        <section class="card billing-panel">
            <div class="billing-panel-heading">
                <div><h2>Invoices</h2><p>Open an account to take payment, review receipts, or manage the invoice lifecycle.</p></div>
                <span class="billing-count">{{ $invoices->total() }} {{ Str::plural('invoice', $invoices->total()) }}</span>
            </div>

            @forelse ($invoices as $invoice)
                <article class="invoice-card">
                    <div class="invoice-top">
                        <div>
                            <h3>{{ $invoice->invoice_number }} · {{ $invoice->patient->full_name }}</h3>
                            <p>{{ $invoice->patient->medical_record_number }} · Issued {{ $invoice->issued_at?->format('d M Y H:i') ?? 'not dated' }} · {{ $invoice->lineItems->count() }} {{ Str::plural('line item', $invoice->lineItems->count()) }}</p>
                        </div>
                        <span class="billing-status">{{ str_replace('_', ' ', ucfirst($invoice->status)) }}</span>
                    </div>
                    <div class="invoice-money">
                        <div><span>Total</span><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</strong></div>
                        <div><span>Paid</span><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->paid_amount, 2) }}</strong></div>
                        <div><span>Balance</span><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->balance_due, 2) }}</strong></div>
                    </div>
                    <a class="billing-link" href="{{ route('billing.show', $invoice->patient) }}">Open patient account →</a>
                </article>
            @empty
                <div class="billing-empty">No invoices match this filter.</div>
            @endforelse

            <div class="billing-pagination">{{ $invoices->links() }}</div>
        </section>

        <aside class="card billing-panel">
            <div class="billing-panel-heading">
                <div><h2>Pending charges</h2><p>Charges shown here are ready to be grouped into an invoice.</p></div>
                <span class="billing-count">{{ $pendingCharges->count() }} shown</span>
            </div>
            @forelse ($pendingCharges as $charge)
                <article class="charge-card">
                    <h3>{{ $charge->patient->full_name }}</h3>
                    <p>{{ $charge->patient->medical_record_number }} · {{ $charge->billableService?->name ?? $charge->description }}<br>{{ $charge->currency }} {{ number_format((float) $charge->total, 2) }}</p>
                    <a class="billing-link" href="{{ route('billing.show', $charge->patient) }}">Prepare invoice →</a>
                </article>
            @empty
                <div class="billing-empty">There are no pending charges for this filter.</div>
            @endforelse
        </aside>
    </div>
</section>
@endsection
