@extends('layouts.app')

@section('title', 'Manage '.$staff->name.' · CityCare')

@push('styles')
<style>
    .admin-wrap{max-width:1120px;padding:clamp(22px,3vw,42px);margin:0 auto}.admin-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:22px}.eyebrow{margin:0 0 6px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.13em}.admin-head h1{margin:0}.muted{color:var(--muted)}.admin-head p{margin:7px 0 0}.grid{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(290px,.75fr);gap:18px}.panel{padding:clamp(18px,3vw,26px);margin-bottom:18px}.panel h2{margin:0 0 5px;font-size:1.08rem}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:18px}.field{display:grid;gap:6px}.field span{font-size:.78rem;font-weight:800}.field input,.field select{width:100%;min-height:43px;padding:9px 11px;border:1px solid var(--line);border-radius:9px;background:#fff}.field small{color:var(--muted)}.role-grid{display:grid;gap:8px;margin-top:14px}.role-option{display:flex;align-items:flex-start;gap:9px;padding:10px;border:1px solid var(--line);border-radius:10px}.role-option input{margin-top:3px}.role-option strong{display:block;font-size:.84rem}.role-option small{display:block;margin-top:2px;color:var(--muted)}.actions{display:flex;justify-content:flex-end;gap:9px;margin-top:22px}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:41px;padding:9px 14px;border:1px solid transparent;border-radius:9px;font-weight:800;text-decoration:none;cursor:pointer}.btn-primary{background:var(--blue);color:#fff}.btn-secondary{border-color:var(--line);background:#fff;color:var(--ink)}.btn-danger{background:#fff;color:var(--red);border-color:#fecaca}.btn-success{background:#ecfdf3;color:var(--green);border-color:#bbf7d0}.account-state{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:13px;border-radius:10px;background:#f8fbfd}.state{font-weight:850;color:var(--green)}.state.inactive{color:var(--red)}.state.pending{color:#b45309}.fact-list{display:grid;gap:10px;margin:16px 0 0}.fact{padding-bottom:10px;border-bottom:1px solid #edf2f7}.fact:last-child{border-bottom:0}.fact small{display:block;color:var(--muted);font-size:.71rem;font-weight:800;text-transform:uppercase}.fact strong{display:block;margin-top:3px}.status,.error{margin-bottom:16px}.setup-link{margin-bottom:18px;padding:18px}.setup-link h2{margin:0 0 6px;font-size:1rem}.setup-link-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:9px;margin-top:12px}.setup-link input{min-width:0;padding:10px 11px;border:1px solid var(--line);border-radius:9px;background:#f8fafc}.invitation-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}@media(max-width:860px){.grid{grid-template-columns:1fr}}@media(max-width:650px){.form-grid{grid-template-columns:1fr}.admin-head{flex-direction:column}.setup-link-row{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<div class="admin-wrap">
    <header class="admin-head"><div><p class="eyebrow">STAFF ACCOUNT · {{ strtoupper($facility->name) }}</p><h1>{{ $staff->name }}</h1><p class="muted">Update employment details, credentials, approved roles, and account state through separately audited controls.</p></div><a class="btn btn-secondary" href="{{ route('staff.index', ['facility_id' => $facility->id]) }}">Back to staff</a></header>
    @if(session('status'))<div class="status" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error" role="alert"><strong>Please correct the request.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if(session('invitation_url'))
        <section class="card setup-link" aria-labelledby="setup-link-heading">
            <h2 id="setup-link-heading">Single-use staff setup link</h2>
            <p class="muted">Copy this link now and deliver it through an approved private channel. For security, it is shown only on this response.</p>
            <div class="setup-link-row"><input id="staff-setup-link" value="{{ session('invitation_url') }}" readonly aria-label="Staff setup link"><button class="btn btn-secondary" type="button" onclick="document.getElementById('staff-setup-link').select()">Select link</button></div>
        </section>
    @endif
    <div class="grid">
        <section>
            <form class="card panel" method="POST" action="{{ route('staff.update', $staff) }}">
                @csrf @method('PUT')
                <h2>Account and assignment</h2><p class="muted">Changing the department remains restricted to this facility.</p>
                @include('administration.staff._account_fields', ['staff' => $staff])
                <div class="actions"><button class="btn btn-primary" type="submit">Save account details</button></div>
            </form>
        </section>
        <aside>
            <section class="card panel">
                <h2>Role assignments</h2><p class="muted">Role changes are recorded in the security audit trail.</p>
                <form method="POST" action="{{ route('staff.roles.update', $staff) }}">@csrf @method('PUT')
                    <div class="role-grid">@foreach($roles as $role)<label class="role-option"><input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array((string) $role->id, array_map('strval', old('roles', $staff->roles->modelKeys())), true))><span><strong>{{ $role->name }}</strong><small>{{ $role->description ?: $role->slug }}</small></span></label>@endforeach</div>
                    <div class="actions"><button class="btn btn-primary" type="submit">Update roles</button></div>
                </form>
            </section>
            <section class="card panel">
                <h2>Setup invitation</h2><p class="muted">Setup links are single-use and expire after 48 hours.</p>
                @if($invitation)
                    @php($invitationState = $invitation->isAccepted() ? 'Accepted' : ($invitation->isRevoked() ? 'Revoked' : ($invitation->isExpired() ? 'Expired' : 'Pending')))
                    <div class="fact-list"><div class="fact"><small>Latest invitation</small><strong>{{ $invitationState }}</strong></div><div class="fact"><small>Expires</small><strong>{{ $invitation->expires_at?->format('d M Y, H:i') }}</strong></div></div>
                @else
                    <p class="muted">No invitation has been issued for this account.</p>
                @endif
                <div class="invitation-actions">
                    @if(! $staff->isActive())
                        <form method="POST" action="{{ route('staff.invitations.reissue', $staff) }}">@csrf<button class="btn btn-primary" type="submit">Issue new setup link</button></form>
                    @endif
                    @if($invitation?->isPending())
                        <form method="POST" action="{{ route('staff.invitations.revoke', [$staff, $invitation]) }}">@csrf @method('DELETE')<button class="btn btn-danger" type="submit">Revoke pending link</button></form>
                    @endif
                </div>
            </section>
            <section class="card panel">
                <h2>Account state</h2>
                @php($pendingSetup = ! $staff->isActive() && blank($staff->password))
                <div class="account-state"><span class="state {{ $pendingSetup ? 'pending' : ($staff->isActive() ? '' : 'inactive') }}">{{ $pendingSetup ? 'Pending setup' : ($staff->isActive() ? 'Active' : 'Inactive') }}</span>
                    @if($staff->isActive())<form method="POST" action="{{ route('staff.deactivate', $staff) }}" onsubmit="return confirm('Deactivate this staff account?')">@csrf<button class="btn btn-danger" type="submit">Deactivate</button></form>@elseif(! $pendingSetup)<form method="POST" action="{{ route('staff.reactivate', $staff) }}">@csrf<button class="btn btn-success" type="submit">Reactivate</button></form>@endif
                </div>
                <div class="fact-list"><div class="fact"><small>Employee number</small><strong>{{ $staff->staffProfile?->employee_number ?: 'Not assigned' }}</strong></div><div class="fact"><small>Department</small><strong>{{ $staff->staffProfile?->department?->name ?? 'Not assigned' }}</strong></div><div class="fact"><small>Service point</small><strong>{{ $staff->staffProfile?->servicePoint?->name ?? 'Not assigned' }}</strong></div></div>
            </section>
        </aside>
    </div>
</div>
@endsection
