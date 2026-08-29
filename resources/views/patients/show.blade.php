@extends('layouts.app')

@section('title', $patient->full_name.' · CityCare Medical Center')

@push('styles')
<style>
    .record-page{max-width:1180px;padding:clamp(24px,4vw,42px)}.record-heading{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px}.record-eyebrow{margin:0 0 7px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.14em}.record-heading h1{margin:0;font-size:clamp(1.85rem,4vw,2.65rem);letter-spacing:-.045em}.record-mrn{margin-top:7px;color:var(--blue);font-size:.88rem;font-weight:850}.record-actions{display:flex;gap:9px;flex-wrap:wrap;justify-content:flex-end}.record-button{display:inline-flex;align-items:center;justify-content:center;padding:10px 13px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--ink);font-size:.82rem;font-weight:800;text-decoration:none}.record-button.primary{border-color:var(--blue);background:var(--blue);color:#fff}.record-card{margin-bottom:18px;padding:22px}.record-card-header{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:20px}.record-card h2{margin:0;font-size:1.08rem}.record-card-header p{margin:6px 0 0;color:var(--muted);font-size:.85rem}.record-status{display:inline-block;padding:6px 9px;border-radius:999px;background:#ecfdf3;color:var(--green);font-size:.72rem;font-weight:850}.record-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.record-item dt{margin:0 0 5px;color:var(--muted);font-size:.69rem;font-weight:850;letter-spacing:.07em;text-transform:uppercase}.record-item dd{margin:0;font-size:.92rem;font-weight:700;line-height:1.4}.record-guidance{display:flex;gap:11px;align-items:flex-start;margin:0;padding:14px;border-radius:10px;background:#f8fafc;color:var(--muted);font-size:.85rem;line-height:1.5}.record-guidance strong{color:var(--blue)}@media(max-width:760px){.record-page{padding:24px 18px}.record-heading{flex-direction:column}.record-actions{justify-content:flex-start}.record-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:500px){.record-grid{grid-template-columns:1fr}.record-actions{width:100%}.record-actions>*{flex:1}}
</style>
@endpush

@section('content')
<section class="record-page">
    <div class="record-heading">
        <div>
            <p class="record-eyebrow">PATIENT RECORD</p>
            <h1>{{ $patient->full_name }}</h1>
            <p class="record-mrn">{{ $patient->medical_record_number }}</p>
        </div>
        <div class="record-actions">
            <a class="record-button" href="{{ route('patients.index') }}">Patient registry</a>
            @if (auth()->user()->hasPermissionTo('appointments.manage') && $patient->isActive())
                <a class="record-button primary" href="{{ route('appointments.create', ['patient_id' => $patient->id]) }}">Schedule appointment</a>
            @endif
            @if (auth()->user()->hasPermissionTo('billing.view'))
                <a class="record-button" href="{{ route('billing.show', $patient) }}">Open billing</a>
            @endif
            @if (auth()->user()->hasPermissionTo('patients.update'))
                <a class="record-button" href="{{ route('patients.portal.show', $patient) }}">Portal access</a>
            @endif
        </div>
    </div>

    @if (session('status'))<div class="status" style="margin-bottom:18px">{{ session('status') }}</div>@endif

    <section class="card record-card">
        <div class="record-card-header">
            <div><h2>Patient identity</h2><p>Clinical identity and direct contact information for the registered record.</p></div>
            <span class="record-status">{{ ucfirst($patient->status) }}</span>
        </div>
        <dl class="record-grid">
            <div class="record-item"><dt>Full name</dt><dd>{{ $patient->full_name }}</dd></div>
            <div class="record-item"><dt>Sex</dt><dd>{{ $patient->sex ? ucfirst($patient->sex) : 'Not recorded' }}</dd></div>
            <div class="record-item"><dt>Date of birth</dt><dd>{{ $patient->date_of_birth?->format('d M Y') ?: 'Not recorded' }}</dd></div>
            <div class="record-item"><dt>National ID</dt><dd>{{ $patient->national_id ?: 'Not recorded' }}</dd></div>
            <div class="record-item"><dt>Phone</dt><dd>{{ $patient->phone ?: 'Not recorded' }}</dd></div>
            <div class="record-item"><dt>Email</dt><dd>{{ $patient->email ?: 'Not recorded' }}</dd></div>
            <div class="record-item"><dt>Registered</dt><dd>{{ $patient->registered_at?->format('d M Y H:i') ?: 'Not recorded' }}</dd></div>
        </dl>
    </section>

    <section class="card record-card">
        <div class="record-card-header"><div><h2>Address and care contacts</h2><p>Emergency and next-of-kin information used for safe care coordination.</p></div></div>
        <dl class="record-grid">
            <div class="record-item"><dt>Address</dt><dd>{{ collect([$patient->address_line1, $patient->address_line2, $patient->city, $patient->district])->filter()->implode(', ') ?: 'Not recorded' }}</dd></div>
            <div class="record-item"><dt>Emergency contact</dt><dd>{{ $patient->emergency_contact_name ?: 'Not recorded' }}{{ $patient->emergency_contact_relationship ? ' · '.$patient->emergency_contact_relationship : '' }}</dd></div>
            <div class="record-item"><dt>Emergency phone</dt><dd>{{ $patient->emergency_contact_phone ?: 'Not recorded' }}</dd></div>
            <div class="record-item"><dt>Next of kin</dt><dd>{{ $patient->next_of_kin_name ?: 'Not recorded' }}{{ $patient->next_of_kin_relationship ? ' · '.$patient->next_of_kin_relationship : '' }}</dd></div>
            <div class="record-item"><dt>Next-of-kin phone</dt><dd>{{ $patient->next_of_kin_phone ?: 'Not recorded' }}</dd></div>
        </dl>
    </section>

    <p class="record-guidance"><strong>Workflow:</strong><span>Use this record to schedule an active patient. Portal access is separate from clinical registration, and billing access is only visible to authorized staff.</span></p>
</section>
@endsection
