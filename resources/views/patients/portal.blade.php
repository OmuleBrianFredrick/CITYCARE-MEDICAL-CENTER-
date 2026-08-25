@extends('layouts.app')

@section('title', 'Patient Portal Access · CityCare Medical Center')

@push('styles')
<style>
    .portal-admin-page{max-width:1050px;padding:clamp(24px,4vw,42px)}.portal-heading{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px}.portal-eyebrow{margin:0 0 7px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.14em}.portal-heading h1{margin:0;font-size:clamp(1.85rem,4vw,2.55rem);letter-spacing:-.045em}.portal-heading p{margin:8px 0 0;color:var(--muted);line-height:1.5}.portal-back{display:inline-flex;padding:10px 13px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--ink);font-size:.85rem;font-weight:800;text-decoration:none}.portal-card{margin-bottom:18px;padding:22px}.portal-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.portal-item small{display:block;color:var(--muted);font-size:.73rem;font-weight:750}.portal-item strong{display:block;margin-top:6px;font-size:.92rem}.portal-status{display:inline-block;padding:6px 9px;border-radius:999px;background:#ecfdf3;color:var(--green);font-size:.72rem;font-weight:850}.portal-status.pending{background:#fff7ed;color:#9a3412}.portal-status.disabled{background:#fee2e2;color:var(--red)}.portal-card h2{margin:0;font-size:1.08rem}.portal-card>p{margin:7px 0 17px;color:var(--muted);line-height:1.5}.portal-actions{display:flex;gap:10px;flex-wrap:wrap}.portal-action{border:0;border-radius:10px;padding:11px 15px;background:var(--blue);color:#fff;font-size:.85rem;font-weight:800;cursor:pointer}.portal-action.success{background:var(--green)}.portal-action.danger{background:var(--red)}.portal-note{padding:14px;border-radius:10px;background:#f8fafc;color:var(--muted);font-size:.84rem;line-height:1.55}@media(max-width:700px){.portal-admin-page{padding:24px 18px}.portal-heading{flex-direction:column}.portal-grid{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<section class="portal-admin-page">
    <div class="portal-heading">
        <div>
            <p class="portal-eyebrow">PATIENT RECORDS · PORTAL ACCESS</p>
            <h1>{{ $patient->full_name }}</h1>
            <p>MRN {{ $patient->medical_record_number }} · Manage the optional patient-facing account separately from the clinical record.</p>
        </div>
        <a class="portal-back" href="{{ route('patients.show', $patient) }}">Back to patient record</a>
    </div>

    @if (session('status'))<div class="status" style="margin-bottom:18px">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="error" style="margin-bottom:18px">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <section class="card portal-card">
        <div class="portal-grid">
            <div class="portal-item"><small>Patient email</small><strong>{{ $patient->email ?: 'Not recorded' }}</strong></div>
            <div class="portal-item"><small>Portal account</small><strong>{{ $patient->hasPortalAccount() ? 'Linked to patient record' : 'Not provisioned' }}</strong></div>
            <div class="portal-item"><small>Portal status</small><strong>
                @if (! $patient->hasPortalAccount())<span class="portal-status pending">Not provisioned</span>
                @elseif ($patient->hasActivePortal())<span class="portal-status">Active</span>
                @elseif ($patient->portal_disabled_at)<span class="portal-status disabled">Disabled</span>
                @else<span class="portal-status pending">Pending activation</span>@endif
            </strong></div>
        </div>
    </section>

    <section class="card portal-card">
        <h2>Portal access controls</h2>
        <p>Provisioning creates the identity; activation enables it. Neither action changes demographics, the MRN, or clinical information.</p>
        <div class="portal-actions">
            @if (! $patient->hasPortalAccount())
                <form method="POST" action="{{ route('patients.portal.provision', $patient) }}">@csrf<button class="portal-action" type="submit">Provision portal account</button></form>
            @elseif (! $patient->hasActivePortal())
                <form method="POST" action="{{ route('patients.portal.activate', $patient) }}">@csrf<button class="portal-action success" type="submit">Activate portal access</button></form>
            @else
                <form method="POST" action="{{ route('patients.portal.disable', $patient) }}">@csrf<button class="portal-action danger" type="submit">Disable portal access</button></form>
            @endif
        </div>
        <p class="portal-note" style="margin:20px 0 0">Portal access should be offered only after confirming a suitable patient email and explaining how account credentials are managed locally.</p>
    </section>

    <section class="card portal-card">
        <h2>Lifecycle</h2>
        <div class="portal-grid" style="margin-top:18px">
            <div class="portal-item"><small>Provisioned</small><strong>{{ $patient->portal_invited_at?->format('d M Y H:i') ?: '—' }}</strong></div>
            <div class="portal-item"><small>Activated</small><strong>{{ $patient->portal_activated_at?->format('d M Y H:i') ?: '—' }}</strong></div>
            <div class="portal-item"><small>Disabled</small><strong>{{ $patient->portal_disabled_at?->format('d M Y H:i') ?: '—' }}</strong></div>
        </div>
    </section>
</section>
@endsection
