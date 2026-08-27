@extends('layouts.app')

@section('title', 'Reports | CityCare')

@push('styles')
<style>
    .reports-page{max-width:1380px;padding:clamp(24px,4vw,42px)}.reports-heading{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:22px}.reports-eyebrow{margin:0 0 7px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.14em}.reports-heading h1{margin:0;font-size:clamp(1.85rem,4vw,2.65rem);letter-spacing:-.045em}.reports-heading p{max-width:760px;margin:9px 0 0;color:var(--muted);line-height:1.55}.reports-scope{padding:9px 11px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--muted);font-size:.8rem;font-weight:750;white-space:nowrap}.reports-message{margin-bottom:18px}.reports-message ul{margin:7px 0 0;padding-left:20px}.reports-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:22px}.reports-stat{padding:20px}.reports-stat span{display:block;color:var(--muted);font-size:.7rem;font-weight:850;letter-spacing:.08em;text-transform:uppercase}.reports-stat strong{display:block;margin-top:8px;font-size:1.35rem}.reports-section-heading{display:flex;justify-content:space-between;gap:14px;align-items:flex-end;margin:0 0 14px}.reports-section-heading h2{margin:0;font-size:1.12rem}.reports-section-heading p{margin:5px 0 0;color:var(--muted);font-size:.82rem}.report-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-bottom:26px}.report-card{padding:22px}.report-card-top{display:flex;justify-content:space-between;gap:14px;align-items:flex-start}.report-card h3{margin:0;font-size:1.02rem}.report-card p{margin:7px 0 0;color:var(--muted);font-size:.82rem;line-height:1.5}.report-category{padding:5px 8px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:.67rem;font-weight:850;white-space:nowrap}.report-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:18px;padding-top:16px;border-top:1px solid #edf2f7}.report-form label{display:grid;gap:6px;color:var(--ink);font-size:.75rem;font-weight:800}.report-form input,.report-form select{width:100%;min-width:0;padding:10px 11px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--ink)}.report-form-scope{grid-column:1/-1;margin:0!important;padding:10px 11px;border-radius:9px;background:#f8fafc;color:var(--muted)!important}.report-submit{grid-column:1/-1;border:0;border-radius:9px;background:var(--blue);color:#fff;padding:10px 14px;font-size:.8rem;font-weight:850;cursor:pointer}.reports-empty{grid-column:1/-1;padding:34px 18px;color:var(--muted);text-align:center}.runs-card{padding:22px}.runs-table-wrap{overflow:auto}.runs-table{width:100%;border-collapse:collapse}.runs-table th,.runs-table td{padding:13px 10px;border-bottom:1px solid #edf2f7;text-align:left;vertical-align:top}.runs-table th{color:var(--muted);font-size:.68rem;font-weight:850;letter-spacing:.08em;text-transform:uppercase;white-space:nowrap}.runs-table td{font-size:.8rem}.run-link{color:var(--blue);font-weight:850;text-decoration:none}.run-secondary{display:block;margin-top:4px;color:var(--muted);font-size:.73rem}.run-status{display:inline-block;padding:5px 8px;border-radius:999px;background:#f1f5f9;color:#334155;font-size:.67rem;font-weight:850;white-space:nowrap}.runs-empty{padding:32px 18px!important;color:var(--muted);text-align:center!important}@media(max-width:900px){.reports-summary,.report-grid{grid-template-columns:1fr}}@media(max-width:680px){.reports-page{padding:24px 18px}.reports-heading,.reports-section-heading{flex-direction:column;align-items:flex-start}.reports-scope{white-space:normal}.report-form{grid-template-columns:1fr}.report-form-scope,.report-submit{grid-column:auto}.reports-summary{gap:10px}}
</style>
@endpush

@section('content')
<div class="reports-page">
    <header class="reports-heading">
        <div>
            <p class="reports-eyebrow">ANALYTICS &amp; OVERSIGHT</p>
            <h1>Reports</h1>
            <p>Generate operational summaries using the same permissions and facility boundaries that protect the underlying clinical, laboratory, pharmacy, billing, and inventory records.</p>
        </div>
        <span class="reports-scope">{{ $canRunOrganizationWide ? 'Organization-wide reporting' : ($facilities->first()?->name ?? 'Assigned facility') }}</span>
    </header>

    @if (session('status'))
        <div class="status reports-message" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="error reports-message" role="alert">
            <strong>The report could not be run.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="reports-summary" aria-label="Reporting summary">
        <div class="card reports-stat"><span>Available reports</span><strong>{{ number_format($definitions->count()) }}</strong></div>
        <div class="card reports-stat"><span>Recent runs</span><strong>{{ number_format($runs->count()) }}</strong></div>
        <div class="card reports-stat"><span>Reporting scope</span><strong>{{ $canRunOrganizationWide ? 'All facilities' : '1 facility' }}</strong></div>
    </section>

    <div class="reports-section-heading">
        <div>
            <h2>Available reports</h2>
            <p>Only reports backed by your operational permissions are listed.</p>
        </div>
    </div>

    <div class="report-grid">
        @forelse ($definitions as $definition)
            @php($supportedFilters = collect($definition->supported_filters ?? []))
            <section class="card report-card">
                <div class="report-card-top">
                    <div>
                        <h3>{{ $definition->name }}</h3>
                        <p>{{ $definition->description ?: 'Generate the latest authorized operational summary.' }}</p>
                    </div>
                    <span class="report-category">{{ str($definition->category)->headline() }}</span>
                </div>

                <form class="report-form" method="POST" action="{{ route('reports.run', $definition) }}">
                    @csrf

                    @if ($supportedFilters->contains('facility_id') && $canRunOrganizationWide)
                        <label class="report-form-scope">
                            Facility scope
                            <select name="facility_id">
                                <option value="">All active facilities</option>
                                @foreach ($facilities as $facility)
                                    <option value="{{ $facility->id }}" @selected((string) old('facility_id') === (string) $facility->id)>{{ $facility->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @elseif ($facilities->isNotEmpty())
                        <p class="report-form-scope"><strong>Facility scope:</strong> {{ $facilities->first()->name }}</p>
                    @endif

                    @if ($supportedFilters->contains('date_from'))
                        <label>
                            From date
                            <input type="date" name="date_from" value="{{ old('date_from') }}">
                        </label>
                    @endif

                    @if ($supportedFilters->contains('date_to'))
                        <label>
                            To date
                            <input type="date" name="date_to" value="{{ old('date_to') }}">
                        </label>
                    @endif

                    <button class="report-submit" type="submit">Run {{ strtolower($definition->name) }}</button>
                </form>
            </section>
        @empty
            <section class="card reports-empty">No reports are available for your current permissions.</section>
        @endforelse
    </div>

    <section class="card runs-card" aria-labelledby="recent-runs-heading">
        <div class="reports-section-heading">
            <div>
                <h2 id="recent-runs-heading">Recent report runs</h2>
                <p>Up to 20 runs from your authorized facility and report scope.</p>
            </div>
        </div>
        <div class="runs-table-wrap">
            <table class="runs-table">
                <thead><tr><th scope="col">Report</th><th scope="col">Status</th><th scope="col">Facility</th><th scope="col">Requested</th></tr></thead>
                <tbody>
                    @forelse ($runs as $run)
                        <tr>
                            <td><a class="run-link" href="{{ route('reports.show', $run) }}">{{ $run->definition->name }}</a><span class="run-secondary">Run #{{ $run->id }}</span></td>
                            <td><span class="run-status">{{ str($run->status)->headline() }}</span></td>
                            <td>{{ $run->facility?->name ?? 'All facilities' }}</td>
                            <td>{{ $run->created_at?->format('d M Y, H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td class="runs-empty" colspan="4">No authorized report runs are available yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
