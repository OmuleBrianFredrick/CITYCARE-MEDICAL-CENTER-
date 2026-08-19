@extends('layouts.app')
@section('title', 'Clinical Encounters · CityCare Medical Center')
@section('content')
<div style="padding:34px 24px">
    <div style="display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px">
        <div><div style="color:#2563eb;font-size:.74rem;font-weight:900;letter-spacing:.14em">CLINICAL WORKSPACE</div><h1 style="margin:6px 0">Clinical encounters</h1><p style="color:#627d98">Manage active and completed patient encounters.</p></div>
        @if(auth()->user()->hasPermissionTo('clinical.encounters.create'))<a href="{{ route('encounters.create') }}" style="background:#2563eb;color:white;padding:12px 16px;border-radius:10px;text-decoration:none;font-weight:800">+ Open encounter</a>@endif
    </div>
    <div class="card" style="padding:20px">
        <form method="GET" action="{{ route('encounters.index') }}" style="display:grid;grid-template-columns:1fr 180px auto;gap:10px;margin-bottom:18px">
            <input name="search" value="{{ request('search') }}" placeholder="Search encounter, MRN or patient name…">
            <select name="status"><option value="">All statuses</option><option value="open" @selected(request('status')==='open')>Open</option><option value="closed" @selected(request('status')==='closed')>Closed</option><option value="cancelled" @selected(request('status')==='cancelled')>Cancelled</option></select>
            <button style="background:#2563eb;color:white;border:0;border-radius:10px;padding:11px 16px;font-weight:800">Filter</button>
        </form>
        <div style="overflow:auto"><table style="width:100%;border-collapse:collapse"><thead><tr><th>Encounter</th><th>Patient</th><th>Clinician</th><th>Service point</th><th>Started</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($encounters as $encounter)
        <tr><td><strong>{{ $encounter->encounter_number }}</strong></td><td>{{ $encounter->patient->full_name }}</td><td>{{ $encounter->clinician->name }}</td><td>{{ $encounter->servicePoint->name }}</td><td>{{ $encounter->started_at?->format('d M Y H:i') }}</td><td>{{ ucfirst($encounter->status) }}</td><td><a href="{{ route('encounters.show', $encounter) }}">View →</a></td></tr>
        @empty<tr><td colspan="7" style="padding:28px;text-align:center;color:#627d98">No clinical encounters found.</td></tr>@endforelse
        </tbody></table></div>
        <div style="margin-top:18px">{{ $encounters->links() }}</div>
    </div>
</div>
@endsection
