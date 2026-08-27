@extends('layouts.app')

@section('title', 'Create staff account · CityCare')

@push('styles')
<style>
    .admin-wrap{max-width:1050px;padding:clamp(22px,3vw,42px);margin:0 auto}.admin-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:22px}.eyebrow{margin:0 0 6px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.13em}.admin-head h1{margin:0}.muted{color:var(--muted)}.admin-head p{margin:7px 0 0}.panel{padding:clamp(18px,3vw,28px)}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.field{display:grid;gap:6px}.field span{font-size:.78rem;font-weight:800}.field input,.field select{width:100%;min-height:43px;padding:9px 11px;border:1px solid var(--line);border-radius:9px;background:#fff}.field small{color:var(--muted)}.role-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px;margin-top:10px}.role-option{display:flex;align-items:flex-start;gap:9px;padding:11px;border:1px solid var(--line);border-radius:10px}.role-option input{margin-top:3px}.role-option strong{display:block;font-size:.86rem}.role-option small{display:block;margin-top:3px;color:var(--muted)}.section-title{margin:26px 0 4px;font-size:1.02rem}.actions{display:flex;justify-content:flex-end;gap:9px;margin-top:24px}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:9px 15px;border:1px solid transparent;border-radius:9px;font-weight:800;text-decoration:none;cursor:pointer}.btn-primary{background:var(--blue);color:#fff}.btn-secondary{border-color:var(--line);background:#fff;color:var(--ink)}.active-option{display:flex;align-items:center;gap:9px;margin-top:16px;font-weight:750}.error{margin-bottom:16px}@media(max-width:700px){.form-grid,.role-grid{grid-template-columns:1fr}.admin-head{flex-direction:column}}
</style>
@endpush

@section('content')
<div class="admin-wrap">
    <header class="admin-head"><div><p class="eyebrow">{{ strtoupper($facility->name) }}</p><h1>Invite staff member</h1><p class="muted">Bind the employee to an active department, service point, and approved role set. CityCare will create a single-use setup link so the employee chooses their own password.</p></div><a class="btn btn-secondary" href="{{ route('staff.index', ['facility_id' => $facility->id]) }}">Back to staff</a></header>
    @if($errors->any())<div class="error" role="alert"><strong>Please correct the highlighted details.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form class="card panel" method="POST" action="{{ route('staff.store') }}">
        @csrf
        @include('administration.staff._account_fields', ['staff' => null])
        <h2 class="section-title">Initial role assignments</h2><p class="muted">Only roles you are authorized to delegate are shown. The account remains inactive until the employee accepts the invitation.</p>
        <div class="role-grid">@foreach($roles as $role)<label class="role-option"><input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array((string) $role->id, array_map('strval', old('roles', [])), true))><span><strong>{{ $role->name }}</strong><small>{{ $role->description ?: $role->slug }}</small></span></label>@endforeach</div>
        <div class="actions"><a class="btn btn-secondary" href="{{ route('staff.index', ['facility_id' => $facility->id]) }}">Cancel</a><button class="btn btn-primary" type="submit">Create invitation</button></div>
    </form>
</div>
@endsection
