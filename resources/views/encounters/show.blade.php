@extends('layouts.app')
@section('title', 'Clinical Encounter · CityCare Medical Center')
@section('content')
<div style="padding:34px 24px;max-width:1100px">
    @if(session('status'))<div class="status" style="margin-bottom:18px">{{ session('status') }}</div>@endif
    <div style="display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px">
        <div><div style="color:#2563eb;font-size:.74rem;font-weight:900;letter-spacing:.14em">CLINICAL ENCOUNTER</div><h1 style="margin:6px 0">{{ $encounter->encounter_number }}</h1><p style="color:#627d98">{{ $encounter->patient->full_name }} · {{ $encounter->patient->medical_record_number }}</p></div>
        <div style="padding:8px 12px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-weight:800">{{ ucfirst($encounter->status) }}</div>
    </div>
    <div class="card" style="padding:24px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px">
        <div><strong>Clinician</strong><div style="color:#627d98;margin-top:5px">{{ $encounter->clinician->name }}</div></div>
        <div><strong>Department</strong><div style="color:#627d98;margin-top:5px">{{ $encounter->department->name }}</div></div>
        <div><strong>Service point</strong><div style="color:#627d98;margin-top:5px">{{ $encounter->servicePoint->name }}</div></div>
        <div><strong>Started</strong><div style="color:#627d98;margin-top:5px">{{ $encounter->started_at?->format('d M Y H:i') }}</div></div>
        <div style="grid-column:1/-1"><strong>Summary</strong><div style="color:#627d98;margin-top:5px">{{ $encounter->summary ?: 'No summary recorded yet.' }}</div></div>
    </div>

    <div class="card" style="margin-top:18px;padding:24px">
        <h2 style="margin-top:0">Diagnoses</h2>
        @forelse($encounter->diagnoses as $diagnosis)
            <div style="padding:12px 0;border-bottom:1px solid #e5e7eb">
                <div style="display:flex;justify-content:space-between;gap:12px"><strong>{{ $diagnosis->diagnosis }}</strong><span style="font-weight:800;color:#2563eb">{{ ucfirst($diagnosis->type) }}</span></div>
                <div style="color:#627d98;margin-top:4px">{{ $diagnosis->diagnosis_code ?: 'No diagnosis code' }} · Recorded by {{ $diagnosis->recorder->name }}</div>
                @if($diagnosis->notes)<div style="color:#627d98;margin-top:6px">{{ $diagnosis->notes }}</div>@endif
            </div>
        @empty
            <p style="color:#627d98">No diagnoses recorded yet.</p>
        @endforelse

        @if($encounter->isOpen() && auth()->user()->hasPermissionTo('clinical.diagnoses.manage'))
            <form method="POST" action="{{ route('encounters.diagnoses.store', $encounter) }}" style="display:grid;gap:12px;margin-top:20px">
                @csrf
                <h3 style="margin:0">Record diagnosis</h3>
                <input name="diagnosis" value="{{ old('diagnosis') }}" placeholder="Diagnosis" required style="width:100%;padding:12px;border:1px solid #d9e2ec;border-radius:10px">
                <input name="diagnosis_code" value="{{ old('diagnosis_code') }}" placeholder="Diagnosis code (optional)" style="width:100%;padding:12px;border:1px solid #d9e2ec;border-radius:10px">
                <select name="type" style="width:100%;padding:12px;border:1px solid #d9e2ec;border-radius:10px"><option value="primary" @selected(old('type') === 'primary')>Primary diagnosis</option><option value="secondary" @selected(old('type') === 'secondary')>Secondary diagnosis</option></select>
                <textarea name="notes" rows="4" placeholder="Clinical notes (optional)" style="width:100%;padding:12px;border:1px solid #d9e2ec;border-radius:10px">{{ old('notes') }}</textarea>
                <button style="justify-self:start;background:#2563eb;color:#fff;border:0;border-radius:10px;padding:12px 16px;font-weight:800">Record diagnosis</button>
            </form>
        @endif
    </div>

    @if($encounter->isOpen())
    <div class="card" style="margin-top:18px;padding:24px">
        <h2 style="margin-top:0">Close encounter</h2>
        <form method="POST" action="{{ route('encounters.close', $encounter) }}" style="display:grid;gap:12px">
            @csrf
            <textarea name="summary" rows="5" placeholder="Enter consultation closing summary…" style="width:100%;padding:12px;border:1px solid #d9e2ec;border-radius:10px">{{ $encounter->summary }}</textarea>
            <div style="display:flex;gap:10px"><button style="background:#15803d;color:#fff;border:0;border-radius:10px;padding:12px 16px;font-weight:800">Close encounter</button><button formmethod="POST" formaction="{{ route('encounters.cancel', $encounter) }}" style="background:#b91c1c;color:#fff;border:0;border-radius:10px;padding:12px 16px;font-weight:800">Cancel encounter</button></div>
        </form>
    </div>
    @endif
</div>
@endsection
