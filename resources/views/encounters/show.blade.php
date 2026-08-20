@extends('layouts.app')
@section('title', 'Clinical Encounter · CityCare Medical Center')
@section('content')
<div style="padding:34px 24px;max-width:1100px">
    @if(session('status'))<div class="status" style="margin-bottom:18px">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="status" style="margin-bottom:18px;background:#fef2f2;color:#991b1b">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <div style="display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px">
        <div>
            <div style="color:#2563eb;font-size:.74rem;font-weight:900;letter-spacing:.14em">CLINICAL ENCOUNTER</div>
            <h1 style="margin:6px 0">{{ $encounter->encounter_number }}</h1>
            <p style="color:#627d98">{{ $encounter->patient->full_name }} · {{ $encounter->patient->medical_record_number }}</p>
        </div>
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
        <h2 style="margin-top:0">Vitals</h2>
        @forelse($encounter->vitals as $vital)
            <div style="padding:12px 0;border-bottom:1px solid #e5e7eb">
                <strong>{{ $vital->created_at?->format('d M Y H:i') }}</strong>
                <div style="color:#627d98;margin-top:5px">
                    Temperature: {{ $vital->temperature_c ?? '—' }} °C ·
                    Pulse: {{ $vital->pulse_bpm ?? '—' }} bpm ·
                    Respiratory rate: {{ $vital->respiratory_rate ?? '—' }} ·
                    Blood pressure: {{ $vital->systolic_bp ?? '—' }}/{{ $vital->diastolic_bp ?? '—' }} ·
                    SpO₂: {{ $vital->oxygen_saturation ?? '—' }}%
                </div>
                <div style="color:#627d98;margin-top:4px">Recorded by {{ $vital->recorder->name }}</div>
                @if($vital->notes)<div style="color:#627d98;margin-top:4px">{{ $vital->notes }}</div>@endif
            </div>
        @empty
            <p style="color:#627d98">No vitals recorded yet.</p>
        @endforelse
        @if($encounter->isOpen() && auth()->user()->hasPermissionTo('clinical.vitals.manage'))
            <form method="POST" action="{{ route('encounters.vitals.store', $encounter) }}" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:20px">
                @csrf
                <h3 style="grid-column:1/-1;margin:0">Record vitals</h3>
                <input name="temperature_c" value="{{ old('temperature_c') }}" placeholder="Temperature °C">
                <input name="pulse_bpm" value="{{ old('pulse_bpm') }}" placeholder="Pulse bpm">
                <input name="respiratory_rate" value="{{ old('respiratory_rate') }}" placeholder="Respiratory rate">
                <input name="systolic_bp" value="{{ old('systolic_bp') }}" placeholder="Systolic BP">
                <input name="diastolic_bp" value="{{ old('diastolic_bp') }}" placeholder="Diastolic BP">
                <input name="oxygen_saturation" value="{{ old('oxygen_saturation') }}" placeholder="Oxygen saturation %">
                <input name="weight_kg" value="{{ old('weight_kg') }}" placeholder="Weight kg">
                <input name="height_cm" value="{{ old('height_cm') }}" placeholder="Height cm">
                <input name="pain_score" value="{{ old('pain_score') }}" placeholder="Pain score 0-10">
                <input name="capillary_glucose_mmol_l" value="{{ old('capillary_glucose_mmol_l') }}" placeholder="Glucose mmol/L">
                <textarea name="notes" rows="3" placeholder="Clinical notes" style="grid-column:1/-1">{{ old('notes') }}</textarea>
                <button style="justify-self:start;background:#2563eb;color:#fff;border:0;border-radius:10px;padding:12px 16px;font-weight:800">Save vitals</button>
            </form>
        @endif
    </div>

    <div class="card" style="margin-top:18px;padding:24px">
        <h2 style="margin-top:0">Clinical notes</h2>
        @forelse($encounter->notes as $note)
            <div style="padding:12px 0;border-bottom:1px solid #e5e7eb">
                <div style="display:flex;justify-content:space-between;gap:12px">
                    <strong>{{ $note->diagnosis ?: ($note->chief_complaint ?: 'Clinical note') }}</strong>
                    <span style="font-weight:800;color:#2563eb">{{ $note->isFinalized() ? 'Finalized' : 'Draft' }}</span>
                </div>
                @if($note->chief_complaint)<div style="margin-top:6px"><strong>Chief complaint:</strong> {{ $note->chief_complaint }}</div>@endif
                @if($note->history_of_present_illness)<div style="margin-top:6px"><strong>History:</strong> {{ $note->history_of_present_illness }}</div>@endif
                @if($note->examination)<div style="margin-top:6px"><strong>Examination:</strong> {{ $note->examination }}</div>@endif
                @if($note->assessment)<div style="margin-top:6px"><strong>Assessment:</strong> {{ $note->assessment }}</div>@endif
                @if($note->treatment_plan)<div style="margin-top:6px"><strong>Treatment:</strong> {{ $note->treatment_plan }}</div>@endif
                <div style="color:#627d98;margin-top:6px">By {{ $note->author->name }}</div>
                @if(!$note->isFinalized() && auth()->user()->hasPermissionTo('clinical.notes.manage'))
                    <form method="POST" action="{{ route('encounters.notes.finalize', $note) }}" style="margin-top:8px">@csrf<button style="background:#15803d;color:#fff;border:0;border-radius:8px;padding:8px 12px;font-weight:700">Finalize note</button></form>
                @endif
            </div>
        @empty
            <p style="color:#627d98">No clinical notes recorded yet.</p>
        @endforelse
        @if($encounter->isOpen() && auth()->user()->hasPermissionTo('clinical.notes.manage'))
            <form method="POST" action="{{ route('encounters.notes.store', $encounter) }}" style="display:grid;gap:10px;margin-top:20px">
                @csrf
                <h3 style="margin:0">Add clinical note</h3>
                <input name="chief_complaint" value="{{ old('chief_complaint') }}" placeholder="Chief complaint">
                <textarea name="history_of_present_illness" rows="3" placeholder="History of present illness">{{ old('history_of_present_illness') }}</textarea>
                <textarea name="examination" rows="3" placeholder="Examination">{{ old('examination') }}</textarea>
                <textarea name="assessment" rows="3" placeholder="Assessment">{{ old('assessment') }}</textarea>
                <textarea name="diagnosis" rows="2" placeholder="Diagnosis">{{ old('diagnosis') }}</textarea>
                <textarea name="treatment_plan" rows="3" placeholder="Treatment plan">{{ old('treatment_plan') }}</textarea>
                <textarea name="follow_up_plan" rows="2" placeholder="Follow-up plan">{{ old('follow_up_plan') }}</textarea>
                <textarea name="referral_notes" rows="2" placeholder="Referral notes">{{ old('referral_notes') }}</textarea>
                <button style="justify-self:start;background:#2563eb;color:#fff;border:0;border-radius:10px;padding:12px 16px;font-weight:800">Save note</button>
            </form>
        @endif
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
                <input name="diagnosis" value="{{ old('diagnosis') }}" placeholder="Diagnosis" required>
                <input name="diagnosis_code" value="{{ old('diagnosis_code') }}" placeholder="Diagnosis code (optional)">
                <select name="type"><option value="primary">Primary diagnosis</option><option value="secondary">Secondary diagnosis</option></select>
                <textarea name="notes" rows="4" placeholder="Clinical notes (optional)">{{ old('notes') }}</textarea>
                <button style="justify-self:start;background:#2563eb;color:#fff;border:0;border-radius:10px;padding:12px 16px;font-weight:800">Record diagnosis</button>
            </form>
        @endif
    </div>

    @if($encounter->treatmentPlans->isNotEmpty() || ($encounter->isOpen() && auth()->user()->hasPermissionTo('clinical.treatment-plans.manage')))
    <div class="card" style="margin-top:18px;padding:24px">
        <h2 style="margin-top:0">Treatment plans</h2>
        @forelse($encounter->treatmentPlans as $plan)
            <div style="padding:12px 0;border-bottom:1px solid #e5e7eb">
                <strong>Plan #{{ $plan->id }}</strong><span style="margin-left:8px;font-weight:800;color:#2563eb">{{ ucfirst($plan->status) }}</span>
                <div style="color:#627d98;margin-top:4px">{{ $plan->plan }}</div>
                @if($plan->follow_up_date)<div style="color:#627d98;margin-top:4px">Follow-up: {{ $plan->follow_up_date->format('d M Y') }}</div>@endif
                <div style="color:#627d98;margin-top:4px">Created by {{ $plan->author->name }}</div>
            </div>
        @empty
            <p style="color:#627d98">No treatment plans recorded yet.</p>
        @endforelse
    </div>
    @endif

    @if($encounter->referrals->isNotEmpty() || ($encounter->isOpen() && auth()->user()->hasPermissionTo('clinical.referrals.manage')))
    <div class="card" style="margin-top:18px;padding:24px">
        <h2 style="margin-top:0">Referrals</h2>
        @forelse($encounter->referrals as $referral)
            <div style="padding:12px 0;border-bottom:1px solid #e5e7eb">
                <strong>Referral #{{ $referral->id }} · {{ $referral->referred_to }}</strong><span style="margin-left:8px;font-weight:800;color:#2563eb">{{ ucfirst($referral->status) }}</span>
                <div style="color:#627d98;margin-top:4px">{{ $referral->reason }}</div>
                @if($referral->attachments->isNotEmpty())<div style="margin-top:8px"><strong>Attachments</strong>@foreach($referral->attachments as $attachment)<div style="margin-top:4px">{{ $attachment->file_name }}</div>@endforeach</div>@endif
            </div>
        @empty
            <p style="color:#627d98">No referrals recorded yet.</p>
        @endforelse
    </div>
    @endif

    @if($encounter->isOpen())
    <div class="card" style="margin-top:18px;padding:24px">
        <h2 style="margin-top:0">Close encounter</h2>
        <form method="POST" action="{{ route('encounters.close', $encounter) }}" style="display:grid;gap:12px">
            @csrf
            <textarea name="summary" rows="5" placeholder="Enter consultation closing summary…">{{ $encounter->summary }}</textarea>
            <div style="display:flex;gap:10px"><button style="background:#15803d;color:#fff;border:0;border-radius:10px;padding:12px 16px;font-weight:800">Close encounter</button><button formmethod="POST" formaction="{{ route('encounters.cancel', $encounter) }}" style="background:#b91c1c;color:#fff;border:0;border-radius:10px;padding:12px 16px;font-weight:800">Cancel encounter</button></div>
        </form>
    </div>
    @endif
</div>
@endsection
