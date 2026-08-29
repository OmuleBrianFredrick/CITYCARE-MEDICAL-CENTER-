@extends('layouts.app')
@section('title', 'Open Clinical Encounter · CityCare Medical Center')
@section('content')
<div style="padding:34px 24px;max-width:900px">
    <div style="margin-bottom:24px"><div style="color:#2563eb;font-size:.74rem;font-weight:900;letter-spacing:.14em">CLINICAL WORKSPACE</div><h1 style="margin:6px 0">Open clinical encounter</h1><p style="color:#627d98">Start a consultation from a checked-in appointment.</p></div>
    <div class="card" style="padding:24px">
        @if($errors->any())<div class="error" style="margin-bottom:16px">{{ $errors->first() }}</div>@endif
        @if($appointments->isEmpty())
            <div class="status">There are no checked-in appointments assigned to you or awaiting provider assignment. Ask reception to check in the patient before starting an encounter.</div>
        @else
        <form method="POST" action="{{ route('encounters.store') }}" style="display:grid;gap:16px">
            @csrf
            <label>Checked-in appointment<select name="appointment_id" required style="display:block;width:100%;margin-top:6px;padding:12px;border:1px solid #d9e2ec;border-radius:10px">@foreach($appointments as $appointment)<option value="{{ $appointment->id }}" @selected((string) $selectedAppointmentId === (string) $appointment->id)>{{ $appointment->appointment_number }} — {{ $appointment->patient->full_name }} — {{ $appointment->scheduled_start->format('d M Y H:i') }}</option>@endforeach</select></label>
            <label>Encounter type<select name="type" required style="display:block;width:100%;margin-top:6px;padding:12px;border:1px solid #d9e2ec;border-radius:10px"><option value="outpatient">Outpatient</option><option value="follow_up">Follow-up</option><option value="emergency">Emergency</option></select></label>
            <label>Initial summary<textarea name="summary" rows="5" style="display:block;width:100%;margin-top:6px;padding:12px;border:1px solid #d9e2ec;border-radius:10px">{{ old('summary') }}</textarea></label>
            <button style="background:#2563eb;color:#fff;border:0;border-radius:10px;padding:13px 16px;font-weight:800">Open encounter</button>
        </form>
        @endif
    </div>
</div>
@endsection
