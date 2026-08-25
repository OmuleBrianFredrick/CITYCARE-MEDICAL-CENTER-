@extends('layouts.app')

@section('title', 'Clinical Workspace · CityCare Medical Center')

@push('styles')
<style>
    .clinical-page{padding:clamp(24px,4vw,42px)}.clinical-heading{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px}.clinical-eyebrow{margin:0 0 7px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.14em}.clinical-heading h1{margin:0;font-size:clamp(1.85rem,4vw,2.65rem);letter-spacing:-.045em}.clinical-heading p{max-width:720px;margin:9px 0 0;color:var(--muted);line-height:1.55}.clinical-button{display:inline-flex;align-items:center;justify-content:center;padding:11px 14px;border:1px solid var(--blue);border-radius:10px;background:var(--blue);color:#fff;font-size:.85rem;font-weight:800;text-decoration:none}.clinical-queue{margin-bottom:18px;padding:21px}.clinical-queue-header{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}.clinical-queue h2{margin:0;font-size:1.08rem}.clinical-queue p{margin:6px 0 0;color:var(--muted);font-size:.85rem;line-height:1.5}.clinical-count{display:inline-block;padding:6px 9px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:.72rem;font-weight:850}.clinical-queue-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:11px;margin-top:18px}.clinical-queue-item{padding:14px;border:1px solid #dbeafe;border-radius:11px;background:#f8fbff}.clinical-queue-item strong{display:block}.clinical-queue-item span{display:block;margin-top:4px;color:var(--muted);font-size:.8rem;line-height:1.45}.clinical-queue-item a{display:inline-block;margin-top:11px;color:var(--blue);font-size:.8rem;font-weight:850;text-decoration:none}.clinical-card{padding:20px}.clinical-filter{display:grid;grid-template-columns:minmax(0,1fr) 180px auto;gap:10px;margin-bottom:18px}.clinical-filter input,.clinical-filter select{min-width:0;padding:11px 12px;border:1px solid var(--line);border-radius:10px;background:#fff}.clinical-filter button{border:0;border-radius:10px;background:var(--blue);color:#fff;padding:11px 16px;font-weight:800;cursor:pointer}.clinical-table{overflow:auto}.clinical-table table{width:100%;border-collapse:collapse}.clinical-table th,.clinical-table td{padding:14px 10px;border-bottom:1px solid #edf2f7;text-align:left;vertical-align:top}.clinical-table th{color:var(--muted);font-size:.7rem;font-weight:850;letter-spacing:.08em;text-transform:uppercase;white-space:nowrap}.clinical-link{color:var(--blue);font-weight:850;text-decoration:none}.clinical-detail{display:block;margin-top:3px;color:var(--muted);font-size:.78rem}.clinical-status{display:inline-block;padding:5px 8px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:.7rem;font-weight:850}.clinical-empty{padding:34px 18px;text-align:center;color:var(--muted)}.clinical-pagination{margin-top:18px}@media(max-width:760px){.clinical-page{padding:24px 18px}.clinical-heading{flex-direction:column}.clinical-filter{grid-template-columns:1fr}.clinical-filter button{width:100%}.clinical-queue-header{flex-direction:column;gap:8px}}
</style>
@endpush

@section('content')
<section class="clinical-page">
    <div class="clinical-heading">
        <div>
            <p class="clinical-eyebrow">CLINICAL & NURSING WORKSPACE</p>
            <h1>Clinical encounters</h1>
            <p>Use checked-in appointments to open consultations, then document triage, clinical assessment, care plans, referrals, diagnostics, and medication workflows in the encounter record.</p>
        </div>
        @if (auth()->user()->hasPermissionTo('clinical.encounters.create'))
            <a class="clinical-button" href="{{ route('encounters.create') }}">Open encounter</a>
        @endif
    </div>

    @if (session('status'))<div class="status" style="margin-bottom:18px">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="error" style="margin-bottom:18px">{{ $errors->first() }}</div>@endif

    @if (auth()->user()->hasPermissionTo('clinical.encounters.create'))
        <section class="card clinical-queue">
            <div class="clinical-queue-header">
                <div><h2>Ready for consultation</h2><p>Only checked-in appointments assigned to you, or with no provider assigned, are shown here. Selecting a patient opens the encounter form with the appointment preselected.</p></div>
                <span class="clinical-count">{{ $checkedInAppointments->count() }} waiting</span>
            </div>
            <div class="clinical-queue-list">
                @forelse ($checkedInAppointments as $appointment)
                    <article class="clinical-queue-item">
                        <strong>{{ $appointment->patient->full_name }}</strong>
                        <span>{{ $appointment->appointment_number }} · {{ $appointment->patient->medical_record_number }}</span>
                        <span>{{ $appointment->department->name }} · {{ $appointment->servicePoint->name }}</span>
                        <a href="{{ route('encounters.create', ['appointment_id' => $appointment->id]) }}">Start encounter →</a>
                    </article>
                @empty
                    <p style="margin:18px 0 0;color:var(--muted)">No eligible checked-in appointments are waiting. Reception can check a scheduled patient in from the appointment queue.</p>
                @endforelse
            </div>
        </section>
    @endif

    <section class="card clinical-card">
        <form class="clinical-filter" method="GET" action="{{ route('encounters.index') }}">
            <input name="search" value="{{ request('search') }}" placeholder="Search encounter, MRN, or patient name" aria-label="Search clinical encounters">
            <select name="status" aria-label="Filter encounter status"><option value="">All statuses</option><option value="open" @selected(request('status') === 'open')>Open</option><option value="closed" @selected(request('status') === 'closed')>Closed</option><option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option></select>
            <button type="submit">Filter records</button>
        </form>

        <div class="clinical-table"><table>
            <thead><tr><th>Encounter</th><th>Patient</th><th>Clinician</th><th>Service point</th><th>Started</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($encounters as $encounter)
                    <tr>
                        <td><a class="clinical-link" href="{{ route('encounters.show', $encounter) }}">{{ $encounter->encounter_number }}</a></td>
                        <td><strong>{{ $encounter->patient->full_name }}</strong><span class="clinical-detail">{{ $encounter->patient->medical_record_number }}</span></td>
                        <td>{{ $encounter->clinician->name }}</td>
                        <td>{{ $encounter->servicePoint->name }}<span class="clinical-detail">{{ $encounter->department->name }}</span></td>
                        <td>{{ $encounter->started_at?->format('d M Y H:i') }}</td>
                        <td><span class="clinical-status">{{ ucfirst($encounter->status) }}</span></td>
                        <td><a class="clinical-link" href="{{ route('encounters.show', $encounter) }}">Open record</a></td>
                    </tr>
                @empty
                    <tr><td class="clinical-empty" colspan="7">No clinical encounters match this filter.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="clinical-pagination">{{ $encounters->links() }}</div>
    </section>
</section>
@endsection
