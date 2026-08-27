@extends('layouts.app')

@section('title', 'Staff administration · CityCare')

@push('styles')
<style>
    .admin-wrap{padding:clamp(22px,3vw,42px)}.admin-head{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:22px}.eyebrow{margin:0 0 6px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.13em}.admin-head h1{margin:0;font-size:clamp(1.6rem,3vw,2.3rem)}.muted{color:var(--muted)}.admin-head p{max-width:720px;margin:8px 0 0}.head-actions{display:flex;flex-wrap:wrap;gap:9px}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:9px 14px;border:1px solid transparent;border-radius:9px;font-weight:800;text-decoration:none;cursor:pointer}.btn-primary{background:var(--blue);color:#fff}.btn-secondary{border-color:var(--line);background:#fff;color:var(--ink)}.filter-card{display:grid;grid-template-columns:minmax(220px,1fr) minmax(170px,.45fr) minmax(150px,.35fr) auto;gap:12px;align-items:end;padding:16px;margin-bottom:18px}.field{display:grid;gap:6px}.field span{font-size:.76rem;font-weight:800;color:#334e68}.field input,.field select{width:100%;min-height:42px;padding:9px 11px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--ink)}.summary{display:flex;justify-content:space-between;align-items:center;gap:16px;margin:18px 2px 10px}.summary h2{margin:0;font-size:1.08rem}.table-card{overflow:hidden}.staff-table{width:100%;border-collapse:collapse}.staff-table th,.staff-table td{padding:14px 16px;border-bottom:1px solid #edf2f7;text-align:left;vertical-align:top}.staff-table th{background:#f8fbfd;color:#486581;font-size:.72rem;letter-spacing:.06em;text-transform:uppercase}.staff-table tr:last-child td{border-bottom:0}.staff-name{font-weight:850}.staff-email,.staff-meta{display:block;margin-top:3px;color:var(--muted);font-size:.82rem}.pills{display:flex;flex-wrap:wrap;gap:5px}.pill{display:inline-block;padding:4px 8px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:.7rem;font-weight:800}.state{display:inline-flex;align-items:center;gap:6px;font-size:.78rem;font-weight:800}.state:before{content:"";width:8px;height:8px;border-radius:50%;background:var(--green)}.state.inactive{color:var(--red)}.state.inactive:before{background:var(--red)}.state.pending{color:#b45309}.state.pending:before{background:#f59e0b}.empty{padding:36px;text-align:center;color:var(--muted)}.pagination{padding:16px;border-top:1px solid var(--line)}
    @media(max-width:900px){.filter-card{grid-template-columns:1fr 1fr}.staff-table thead{display:none}.staff-table,.staff-table tbody,.staff-table tr,.staff-table td{display:block;width:100%}.staff-table tr{padding:12px 0;border-bottom:1px solid var(--line)}.staff-table td{padding:6px 16px;border:0}.staff-table td:before{content:attr(data-label);display:block;margin-bottom:3px;color:#829ab1;font-size:.67rem;font-weight:850;text-transform:uppercase}.admin-head{flex-direction:column}}@media(max-width:580px){.filter-card{grid-template-columns:1fr}.head-actions{width:100%}.head-actions .btn{flex:1}}
</style>
@endpush

@section('content')
<div class="admin-wrap">
    <header class="admin-head">
        <div>
            <p class="eyebrow">IDENTITY & ACCESS</p>
            <h1>Staff administration</h1>
            <p class="muted">Manage employee accounts assigned to {{ $facility->name }}. Facility boundaries and privileged role assignments are enforced on every change.</p>
        </div>
        <div class="head-actions">
            @if(auth()->user()->hasRole('super-admin') && auth()->user()->hasPermissionTo('access.manage'))
                <a class="btn btn-secondary" href="{{ route('access.roles.index') }}">Role permissions</a>
            @endif
            <a class="btn btn-primary" href="{{ route('staff.create', ['facility_id' => $facility->id]) }}">Invite staff member</a>
        </div>
    </header>

    @if(session('status'))<div class="status" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error" role="alert"><strong>Please correct the request.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <form class="card filter-card" method="GET" action="{{ route('staff.index') }}">
        @if($facilities->count() > 1)
            <label class="field"><span>Facility</span><select name="facility_id">@foreach($facilities as $availableFacility)<option value="{{ $availableFacility->id }}" @selected($availableFacility->is($facility))>{{ $availableFacility->name }}</option>@endforeach</select></label>
        @else
            <input type="hidden" name="facility_id" value="{{ $facility->id }}">
        @endif
        <label class="field"><span>Search staff</span><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name, email, employee no."></label>
        <label class="field"><span>Account status</span><select name="status"><option value="">All accounts</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option></select></label>
        <button class="btn btn-secondary" type="submit">Apply filters</button>
    </form>

    <div class="summary"><h2>{{ $facility->name }} staff</h2><span class="muted">{{ $staffMembers->total() }} account{{ $staffMembers->total() === 1 ? '' : 's' }}</span></div>
    <section class="card table-card" aria-label="Staff accounts">
        @if($staffMembers->isEmpty())
            <div class="empty">No staff accounts match the selected filters.</div>
        @else
            <table class="staff-table">
                <thead><tr><th>Staff member</th><th>Assignment</th><th>Roles</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach($staffMembers as $staff)
                    <tr>
                        <td data-label="Staff member"><span class="staff-name">{{ $staff->name }}</span><span class="staff-email">{{ $staff->email }}</span><span class="staff-meta">{{ $staff->staffProfile?->employee_number ?: 'No employee number' }}</span></td>
                        <td data-label="Assignment"><strong>{{ $staff->staffProfile?->department?->name ?? 'Unassigned' }}</strong><span class="staff-meta">{{ $staff->staffProfile?->job_title ?: 'No job title' }}{{ $staff->staffProfile?->servicePoint ? ' · '.$staff->staffProfile->servicePoint->name : '' }}</span></td>
                        <td data-label="Roles"><div class="pills">@forelse($staff->roles as $role)<span class="pill">{{ $role->name }}</span>@empty<span class="muted">No role</span>@endforelse</div></td>
                        @php($pendingSetup = ! $staff->isActive() && $staff->staffProfile?->employment_status === 'pending')
                        <td data-label="Status"><span class="state {{ $pendingSetup ? 'pending' : ($staff->isActive() ? '' : 'inactive') }}">{{ $pendingSetup ? 'Pending setup' : ($staff->isActive() ? 'Active' : 'Inactive') }}</span></td>
                        <td data-label="Action"><a class="btn btn-secondary" href="{{ route('staff.edit', $staff) }}">Manage</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="pagination">{{ $staffMembers->links() }}</div>
        @endif
    </section>
</div>
@endsection
