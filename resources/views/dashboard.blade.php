@extends('layouts.app')

@section('title', 'Dashboard · CityCare Medical Center')

@push('styles')
<style>
    .dashboard{padding:clamp(24px,4vw,48px)}.dashboard-heading{display:flex;justify-content:space-between;align-items:flex-start;gap:24px;margin-bottom:30px}.dashboard-eyebrow{margin:0 0 7px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.14em}.dashboard-heading h1{margin:0;font-size:clamp(1.85rem,4vw,3rem);letter-spacing:-.045em}.dashboard-heading p{max-width:720px;margin:10px 0 0;color:var(--muted);line-height:1.6}.dashboard-date{padding:10px 13px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--muted);font-size:.83rem;white-space:nowrap}.metric-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px}.metric-card{display:flex;min-height:184px;flex-direction:column;padding:20px;border:1px solid var(--line);border-radius:16px;background:#fff;box-shadow:0 8px 24px rgba(16,42,67,.045)}.metric-label{color:var(--muted);font-size:.8rem;font-weight:750}.metric-value{display:block;margin:10px 0 7px;font-size:1.7rem;line-height:1.15;letter-spacing:-.035em}.metric-description{margin:0;color:var(--muted);font-size:.82rem;line-height:1.45}.metric-link{margin-top:auto;padding-top:16px;font-size:.82rem;font-weight:800;text-decoration:none}.dashboard-columns{display:grid;grid-template-columns:1.1fr .9fr;gap:18px;margin-top:20px}.dashboard-panel{padding:24px}.dashboard-panel h2{margin:0;font-size:1.1rem}.dashboard-panel>p{margin:7px 0 18px;color:var(--muted);font-size:.88rem;line-height:1.5}.action-list{display:grid;gap:10px}.action-card{display:block;padding:15px;border:1px solid var(--line);border-radius:12px;color:var(--ink);text-decoration:none;transition:border-color .15s,transform .15s}.action-card:hover{border-color:#93c5fd;transform:translateY(-1px)}.action-card strong{display:block;color:var(--blue);font-size:.9rem}.action-card span{display:block;margin-top:4px;color:var(--muted);font-size:.82rem;line-height:1.45}.readiness-list{display:grid;gap:11px;margin:0;padding:0;list-style:none}.readiness-list li{display:flex;gap:10px;align-items:flex-start;color:var(--muted);font-size:.86rem;line-height:1.5}.readiness-list b{display:inline-grid;place-items:center;width:20px;height:20px;flex:0 0 20px;border-radius:999px;background:#dcfce7;color:var(--green);font-size:.72rem}@media(max-width:760px){.dashboard{padding:24px 18px}.dashboard-heading{flex-direction:column;gap:12px}.dashboard-columns{grid-template-columns:1fr}.dashboard-date{display:none}}
</style>
@endpush

@section('content')
<section class="dashboard">
    <div class="dashboard-heading">
        <div>
            <p class="dashboard-eyebrow">CITYCARE COMMAND CENTER</p>
            <h1>Welcome back, {{ auth()->user()->name }}.</h1>
            <p>Your workspace surfaces the live operational data and actions that your assigned permissions allow.</p>
        </div>
        <div class="dashboard-date">{{ now()->format('l, d M Y') }}</div>
    </div>

    @if(session('status'))<div class="status" style="margin-bottom:18px">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error" style="margin-bottom:18px">{{ $errors->first() }}</div>@endif

    <div class="metric-grid">
        @forelse($metrics as $metric)
            <article class="metric-card">
                <span class="metric-label">{{ $metric['label'] }}</span>
                <strong class="metric-value">{{ $metric['value'] }}</strong>
                <p class="metric-description">{{ $metric['description'] }}</p>
                <a class="metric-link" href="{{ $metric['url'] }}">{{ $metric['linkLabel'] }} →</a>
            </article>
        @empty
            <article class="metric-card"><span class="metric-label">Workspace ready</span><strong class="metric-value">No data yet</strong><p class="metric-description">Your account has no data-enabled modules in the current facility context.</p></article>
        @endforelse
    </div>

    <div class="dashboard-columns">
        <section class="card dashboard-panel">
            <h2>Start a workflow</h2>
            <p>Each shortcut opens an implemented, server-authorized CityCare workflow.</p>
            <div class="action-list">
                @forelse($quickActions as $action)
                    <a class="action-card" href="{{ $action['url'] }}"><strong>{{ $action['label'] }} →</strong><span>{{ $action['description'] }}</span></a>
                @empty
                    <p style="margin:0;color:var(--muted)">No create actions are assigned to this account. Use the navigation to access your permitted records.</p>
                @endforelse
            </div>
        </section>

        <aside class="card dashboard-panel">
            <h2>Workspace safeguards</h2>
            <p>CityCare keeps presentation and access control connected to its existing backend safeguards.</p>
            <ul class="readiness-list">
                <li><b>✓</b><span>Navigation is filtered by the same permissions that protect the server routes.</span></li>
                <li><b>✓</b><span>Dashboard values are computed from live facility records, not placeholder metrics.</span></li>
                <li><b>✓</b><span>Actions lead only to existing routes and workflows.</span></li>
            </ul>
        </aside>
    </div>
</section>
@endsection
