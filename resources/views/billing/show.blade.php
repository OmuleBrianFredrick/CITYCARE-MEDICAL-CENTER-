@extends('layouts.app')
@section('title', 'Billing · CityCare Medical Center')
@section('content')
<div style="padding:34px 24px;max-width:1100px">
    @if(session('status'))<div class="status" style="margin-bottom:18px">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="status" style="margin-bottom:18px;background:#fef2f2;color:#991b1b">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <div style="display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px">
        <div><div style="color:#2563eb;font-size:.74rem;font-weight:900;letter-spacing:.14em">BILLING</div><h1 style="margin:6px 0">{{ $patient->full_name }}</h1><p style="color:#627d98">{{ $patient->medical_record_number }} · {{ $patient->facility->name }}</p></div>
        <div style="padding:8px 12px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-weight:800">Billing workspace</div>
    </div>

    <div class="card" style="padding:24px">
        <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start"><div><h2 style="margin:6px 0 0">Charges</h2><p style="color:#627d98;margin-bottom:0">Billable clinical activity and current charge status.</p></div><div style="padding:7px 11px;border-radius:999px;background:#f1f5f9;font-weight:800">{{ $patient->charges->count() }} {{ Str::plural('charge', $patient->charges->count()) }}</div></div>
        <div style="margin-top:18px">
            @forelse($patient->charges as $charge)
                <div style="padding:14px 0;border-bottom:1px solid #e5e7eb">
                    <div style="display:flex;justify-content:space-between;gap:16px"><div><strong>{{ $charge->billableService?->name ?? 'Billable service' }}</strong><div style="color:#627d98;margin-top:4px">{{ $charge->description }} · Qty {{ $charge->quantity }} · {{ $charge->currency }} {{ number_format((float) $charge->total, 2) }}</div>@if($charge->encounter)<div style="color:#627d98;margin-top:4px">Encounter {{ $charge->encounter->encounter_number }}</div>@endif</div><span style="font-weight:800;color:#2563eb">{{ ucfirst($charge->status) }}</span></div>
                </div>
            @empty
                <p style="color:#627d98;margin-top:18px">No billing charges have been recorded for this patient.</p>
            @endforelse
        </div>
    </div>

    <div class="card" style="margin-top:18px;padding:24px">
        <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start"><div><h2 style="margin:6px 0 0">Invoices</h2><p style="color:#627d98;margin-bottom:0">Invoice lifecycle, payments, and outstanding balances.</p></div><div style="padding:7px 11px;border-radius:999px;background:#f1f5f9;font-weight:800">{{ $patient->invoices->count() }} {{ Str::plural('invoice', $patient->invoices->count()) }}</div></div>
        <div style="margin-top:18px">
            @forelse($patient->invoices as $invoice)
                <div style="padding:16px 0;border-bottom:1px solid #e5e7eb">
                    <div style="display:flex;justify-content:space-between;gap:16px"><div><strong>{{ $invoice->invoice_number }}</strong><div style="color:#627d98;margin-top:4px">Total: {{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }} · Paid: {{ number_format((float) $invoice->paid_amount, 2) }} · Balance: {{ number_format((float) $invoice->balance_due, 2) }}</div></div><span style="font-weight:800;color:#2563eb">{{ str_replace('_', ' ', ucfirst($invoice->status)) }}</span></div>
                    @if($invoice->lineItems->isNotEmpty())
                        <div style="margin-top:10px;color:#627d98">{{ $invoice->lineItems->count() }} {{ Str::plural('line item', $invoice->lineItems->count()) }}</div>
                    @endif
                </div>
            @empty
                <p style="color:#627d98;margin-top:18px">No invoices have been created for this patient.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
