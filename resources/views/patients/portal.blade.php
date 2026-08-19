@extends('layout.app')

@section('title','Patient Portal Access')
@section('content')
<div style="max-width:1100px;margin:0 auto;padding:34px 22px">
    <div style="display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px;flex-wrap:wrap">
        <div>
            <div style="color:#2563eb;font-size:.74rem;font-weight:900;letter-spacing:.14em">PATIENT MANAGEMENT · PORTAL ACCESS</div>
            <h1 style="margin:6px 0;font-size:2.4rem;letter-spacing:-.04em">{{ $patient->full_name }}</h1>
            <p style="color:#627d98">MRN {{ $patient->medical_record_number }} · Manage this patient's optional portal access.</p>
        </div>
        <a href="{{ route('patients.show',$patient) }}" style="text-decoration:none;font-weight:800;color:#2563eb">← Patient profile</a>
    </div>

    @if(session('status'))<div style="padding:12px 14px;border:1px solid #bbf7d0;background:#ecfdf3;color:#166534;border-radius:12px;margin-bottom:16px">{{ session('status') }}</div>@endif
    @if($errors->any())<div style="padding:12px 14px;border:1px solid #fed7aa;background:#fff7ed;color:#9a3412;border-radius:12px;margin-bottom:16px">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <div style="background:#fff;border:1px solid #d9e2ec;border-radius:20px;padding:22px;box-shadow:0 8px 28px rgba(16,42,67,.05)">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:22px">
            <div><small style="color:#627d98">Patient email</small><div style="font-weight:800;margin-top:5px">{{ $patient->email ?: 'Not recorded' }}</div></div>
            <div><small style="color:#627d98">Portal account</small><div style="font-weight:800;margin-top:5px">{{ $patient->user ? 'Linked' : 'Not provisioned' }}</div></div>
            <div><small style="color:#627d98">Portal status</small><div style="font-weight:800;margin-top:5px">
                @if(!$patient->user) Not provisioned
                @elseif($patient->user->is_active) Active
                @else Pending / Disabled
                @endif
            </div></div>
        </div>

        @if(!$patient->user)
            <form method="POST" action="{{ route('patients.portal.provision',$patient) }}">
                @csrf
                <button style="border:0;border-radius:11px;padding:12px 16px;background:#2563eb;color:#fff;font-weight:850;cursor:pointer">Provision patient portal</button>
            </form>
        @elseif(!$patient->user->is_active)
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <form method="POST" action="{{ route('patients.portal.activate',$patient) }}">@csrf<button style="border:0;border-radius:11px;padding:12px 16px;background:#15803d;color:#fff;font-weight:850;cursor:pointer">Activate portal</button></form>
                <form method="POST" action="{{ route('patients.portal.disable',$patient) }}">@csrf<button style="border:0;border-radius:11px;padding:12px 16px;background:#eef2f7;color:#102a43;font-weight:850;cursor:pointer">Keep disabled</button></form>
            </div>
        @else
            <form method="POST" action="{{ route('patients.portal.disable',$patient) }}">@csrf<button style="border:0;border-radius:11px;padding:12px 16px;background:#b91c1c;color:#fff;font-weight:850;cursor:pointer">Disable portal access</button></form>
        @endif

        <div style="margin-top:22px;padding:14px;border-radius:12px;background:#f8fafc;color:#627d98;line-height:1.55">
            Patient registration and portal access remain separate. Provisioning a portal account does not alter the medical record and does not activate access until the account is explicitly activated.
        </div>
    </div>
</div>
@endsection
