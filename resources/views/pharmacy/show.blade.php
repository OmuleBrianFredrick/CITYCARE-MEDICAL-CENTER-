@extends('layouts.app')
@section('title', 'Pharmacy · CityCare Medical Center')
@section('content')
<div style="padding:34px 24px;max-width:1100px">
    @if(session('status'))<div class="status" style="margin-bottom:18px">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="status" style="margin-bottom:18px;background:#fef2f2;color:#991b1b">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <div style="display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px">
        <div><div style="color:#2563eb;font-size:.74rem;font-weight:900;letter-spacing:.14em">PHARMACY</div><h1 style="margin:6px 0">{{ $encounter->encounter_number }}</h1><p style="color:#627d98">{{ $encounter->patient->full_name }} · {{ $encounter->patient->medical_record_number }}</p></div>
        <div style="padding:8px 12px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-weight:800">{{ ucfirst($encounter->status) }}</div>
    </div>

    <div class="card" style="margin-top:18px;padding:24px">
        <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start"><div><h2 style="margin:6px 0 0">Pharmacy & medication</h2><p style="color:#627d98;margin-bottom:0">Prescription status and dispensing progress for this encounter.</p></div><div style="padding:7px 11px;border-radius:999px;background:#f1f5f9;font-weight:800">{{ $encounter->prescriptions->count() }} {{ Str::plural('prescription', $encounter->prescriptions->count()) }}</div></div>

        <div style="margin-top:20px">
            @forelse($encounter->prescriptions as $prescription)
                <div style="padding:18px 0;border-bottom:1px solid #e5e7eb">
                    <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start">
                        <div><strong>{{ $prescription->prescription_number }}</strong><div style="color:#627d98;margin-top:4px">Prescribed {{ $prescription->prescribed_at?->format('d M Y H:i') }} by {{ $prescription->prescriber?->name ?? 'Unknown user' }}</div>@if($prescription->notes)<div style="color:#627d98;margin-top:6px">{{ $prescription->notes }}</div>@endif</div>
                        <span style="padding:6px 10px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-weight:800">{{ str_replace('_', ' ', ucfirst($prescription->status)) }}</span>
                    </div>
                    <div style="margin-top:14px;display:grid;gap:10px">
                        @foreach($prescription->items as $item)
                            <div style="padding:12px;border:1px solid #e5e7eb;border-radius:10px">
                                <div style="display:flex;justify-content:space-between;gap:12px"><div><strong>{{ $item->medication?->name ?? 'Medication' }}</strong>@if($item->formulation)<span style="color:#627d98"> · {{ $item->formulation->strength }} {{ $item->formulation->unit }}</span>@endif</div><span style="font-weight:800;color:#2563eb">{{ str_replace('_', ' ', ucfirst($item->status)) }}</span></div>
                                <div style="color:#627d98;margin-top:5px">Quantity: {{ $item->quantity }}{{ $item->dose ? ' · '.$item->dose : '' }}{{ $item->frequency ? ' · '.$item->frequency : '' }}{{ $item->duration ? ' · '.$item->duration : '' }}</div>
                                @php($dispensed = (float) $item->dispensingItems->sum('quantity_dispensed'))
                                <div style="margin-top:8px;padding:10px;background:#f8fafc;border-radius:8px"><strong>Dispensing status:</strong> {{ $dispensed }} / {{ $item->quantity }} dispensed @if($dispensed > 0 && $dispensed < (float) $item->quantity) <span style="font-weight:700;color:#2563eb">· Partially dispensed</span>@elseif($dispensed >= (float) $item->quantity) <span style="font-weight:700;color:#15803d">· Fully dispensed</span>@else <span style="color:#627d98">· Not yet dispensed</span>@endif</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <p style="color:#627d98;margin-top:20px">No prescriptions have been created for this encounter.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
