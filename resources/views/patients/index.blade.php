@extends('layouts.app')

@section('title', 'Patient Registry · CityCare Medical Center')

@push('styles')
<style>
    .patient-page{padding:clamp(24px,4vw,42px)}.patient-heading{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px}.patient-eyebrow{margin:0 0 7px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.14em}.patient-heading h1{margin:0;font-size:clamp(1.85rem,4vw,2.65rem);letter-spacing:-.045em}.patient-heading p{max-width:720px;margin:9px 0 0;color:var(--muted);line-height:1.55}.patient-actions{display:flex;gap:10px;flex-wrap:wrap}.patient-button{display:inline-flex;align-items:center;justify-content:center;padding:11px 14px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--ink);font-size:.85rem;font-weight:800;text-decoration:none}.patient-button.primary{border-color:var(--blue);background:var(--blue);color:#fff}.registry-card{padding:20px}.registry-filter{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;margin-bottom:18px}.registry-filter input{min-width:0;padding:11px 12px;border:1px solid var(--line);border-radius:10px}.registry-filter button{border:0;border-radius:10px;background:var(--blue);color:#fff;padding:11px 16px;font-weight:800;cursor:pointer}.registry-meta{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:14px;color:var(--muted);font-size:.82rem}.registry-table{overflow:auto}.registry-table table{width:100%;border-collapse:collapse}.registry-table th,.registry-table td{padding:14px 10px;border-bottom:1px solid #edf2f7;text-align:left;vertical-align:top}.registry-table th{color:var(--muted);font-size:.7rem;font-weight:850;letter-spacing:.08em;text-transform:uppercase;white-space:nowrap}.patient-name{display:block;color:var(--ink);font-weight:850;text-decoration:none}.patient-name:hover{color:var(--blue)}.patient-detail{display:block;margin-top:3px;color:var(--muted);font-size:.78rem}.patient-mrn{color:var(--blue);font-size:.78rem;font-weight:850;white-space:nowrap}.patient-status{display:inline-block;padding:5px 8px;border-radius:999px;background:#ecfdf3;color:var(--green);font-size:.7rem;font-weight:850}.table-actions{display:flex;gap:7px;flex-wrap:wrap;min-width:155px}.table-action{color:var(--blue);font-size:.78rem;font-weight:800;text-decoration:none}.empty-state{padding:34px 18px;text-align:center;color:var(--muted)}.registry-pagination{margin-top:18px}@media(max-width:760px){.patient-page{padding:24px 18px}.patient-heading{flex-direction:column}.registry-filter{grid-template-columns:1fr}.registry-filter button{width:100%}}
</style>
@endpush

@section('content')
<section class="patient-page">
    <div class="patient-heading">
        <div>
            <p class="patient-eyebrow">RECEPTION & PATIENT RECORDS</p>
            <h1>Patient registry</h1>
            <p>Find an existing medical record before registering a patient, then continue directly to scheduling, portal access, or permitted billing work.</p>
        </div>
        <div class="patient-actions">
            @if (auth()->user()->hasPermissionTo('appointments.manage'))
                <a class="patient-button" href="{{ route('appointments.index') }}">Appointment queue</a>
            @endif
            @if (auth()->user()->hasPermissionTo('patients.create'))
                <a class="patient-button primary" href="{{ route('patients.create') }}">Register patient</a>
            @endif
        </div>
    </div>

    @if (session('status'))<div class="status" style="margin-bottom:18px">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="error" style="margin-bottom:18px">{{ $errors->first() }}</div>@endif

    <section class="card registry-card">
        <form class="registry-filter" method="GET" action="{{ route('patients.index') }}">
            <input name="search" value="{{ request('search') }}" placeholder="Search by name, MRN, phone, or national ID" aria-label="Search patient registry" autofocus>
            <button type="submit">Search registry</button>
        </form>

        <div class="registry-meta">
            <span>{{ $patients->total() }} {{ Str::plural('patient', $patients->total()) }} in {{ $facility->name }}</span>
            @if (filled(request('search')))<a href="{{ route('patients.index') }}">Clear search</a>@endif
        </div>

        <div class="registry-table">
            <table>
                <thead><tr><th>Patient</th><th>MRN</th><th>Contact</th><th>Registered</th><th>Status</th><th>Workflow</th></tr></thead>
                <tbody>
                    @forelse ($patients as $patient)
                        <tr>
                            <td>
                                <a class="patient-name" href="{{ route('patients.show', $patient) }}">{{ $patient->full_name }}</a>
                                <span class="patient-detail">{{ $patient->sex ? ucfirst($patient->sex) : 'Sex not recorded' }} · {{ $patient->date_of_birth?->format('d M Y') ?: 'DOB not recorded' }}</span>
                            </td>
                            <td><span class="patient-mrn">{{ $patient->medical_record_number }}</span></td>
                            <td>{{ $patient->phone ?: ($patient->email ?: 'No contact recorded') }}</td>
                            <td>{{ $patient->registered_at?->format('d M Y') ?: '—' }}</td>
                            <td><span class="patient-status">{{ ucfirst($patient->status) }}</span></td>
                            <td>
                                <div class="table-actions">
                                    <a class="table-action" href="{{ route('patients.show', $patient) }}">Open record</a>
                                    @if (auth()->user()->hasPermissionTo('appointments.manage') && $patient->isActive())
                                        <a class="table-action" href="{{ route('appointments.create', ['patient_id' => $patient->id]) }}">Schedule</a>
                                    @endif
                                    @if (auth()->user()->hasPermissionTo('billing.view'))
                                        <a class="table-action" href="{{ route('billing.show', $patient) }}">Billing</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="empty-state" colspan="6">No patients match this search. Register a new patient only after checking for an existing record.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="registry-pagination">{{ $patients->links() }}</div>
    </section>
</section>
@endsection
