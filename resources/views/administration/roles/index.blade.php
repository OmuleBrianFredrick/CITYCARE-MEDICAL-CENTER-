@extends('layouts.app')

@section('title', 'Role permissions · CityCare')

@push('styles')
<style>
    .access-wrap{padding:clamp(22px,3vw,42px)}.access-head{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:22px}.eyebrow{margin:0 0 6px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.13em}.access-head h1{margin:0}.access-head p{max-width:760px;margin:8px 0 0}.muted{color:var(--muted)}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:41px;padding:9px 14px;border:1px solid transparent;border-radius:9px;font-weight:800;text-decoration:none;cursor:pointer}.btn-primary{background:var(--blue);color:#fff}.btn-secondary{border-color:var(--line);background:#fff;color:var(--ink)}.role-stack{display:grid;gap:16px}.role-card{padding:clamp(18px,2.4vw,26px)}.role-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start}.role-head h2{margin:0;font-size:1.15rem}.role-head p{margin:5px 0 0}.role-meta{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}.tag{padding:5px 8px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:.68rem;font-weight:850}.tag.protected{background:#fff7ed;color:#c2410c}.permission-groups{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:13px;margin-top:20px}.permission-group{padding:13px;border:1px solid var(--line);border-radius:11px;background:#fbfdff}.permission-group h3{margin:0 0 10px;color:#486581;font-size:.72rem;letter-spacing:.07em;text-transform:uppercase}.permission-list{display:grid;gap:8px}.permission{display:flex;align-items:flex-start;gap:8px;font-size:.82rem}.permission input{margin-top:3px}.permission strong{display:block}.permission small{display:block;margin-top:2px;color:var(--muted)}.actions{display:flex;justify-content:flex-end;margin-top:18px}.protected-copy{padding:13px;margin-top:18px;border-radius:10px;background:#fff7ed;color:#9a3412}.status,.error{margin-bottom:16px}@media(max-width:1000px){.permission-groups{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:680px){.permission-groups{grid-template-columns:1fr}.access-head,.role-head{flex-direction:column}.role-meta{justify-content:flex-start}}
</style>
@endpush

@section('content')
<div class="access-wrap">
    <header class="access-head"><div><p class="eyebrow">SUPER ADMINISTRATION</p><h1>Role permissions</h1><p class="muted">Review effective capability bundles and update internal role permissions. Protected super-administrator and patient boundaries cannot be changed here.</p></div><a class="btn btn-secondary" href="{{ route('staff.index') }}">Staff administration</a></header>
    @if(session('status'))<div class="status" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error" role="alert"><strong>The permission set was not changed.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="role-stack">
        @foreach($roles as $role)
            @php($protected = in_array($role->slug, ['super-admin', 'patient'], true))
            <section class="card role-card" id="role-{{ $role->id }}">
                <div class="role-head"><div><h2>{{ $role->name }}</h2><p class="muted">{{ $role->description ?: 'No role description configured.' }}</p></div><div class="role-meta"><span class="tag">{{ $role->users_count }} assigned</span><span class="tag">{{ $role->permissions->count() }} permissions</span>@if($protected)<span class="tag protected">Protected</span>@endif</div></div>
                @if($protected)
                    <div class="protected-copy">{{ $role->slug === 'super-admin' ? 'Full system authority is seeded and protected against accidental privilege loss.' : 'Patient portal access is isolated from internal staff permission administration.' }}</div>
                @else
                    <form method="POST" action="{{ route('access.roles.update', $role) }}">@csrf @method('PUT')
                        <input type="hidden" name="role_id" value="{{ $role->id }}">
                        @php($selectedPermissionIds = (int) old('role_id') === $role->id ? old('permissions', []) : $role->permissions->modelKeys())
                        <div class="permission-groups">
                            @foreach($permissionGroups as $group => $permissions)
                                <fieldset class="permission-group"><legend><h3>{{ str($group)->replace('-', ' ')->title() }}</h3></legend><div class="permission-list">
                                @foreach($permissions as $permission)<label class="permission"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(in_array((string) $permission->id, array_map('strval', $selectedPermissionIds), true))><span><strong>{{ $permission->name }}</strong><small>{{ $permission->slug }}</small></span></label>@endforeach
                                </div></fieldset>
                            @endforeach
                        </div>
                        <div class="actions"><button class="btn btn-primary" type="submit">Save {{ $role->name }} permissions</button></div>
                    </form>
                @endif
            </section>
        @endforeach
    </div>
</div>
@endsection
