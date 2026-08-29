@extends('layouts.app')

@section('title', $run->definition->name.' | CityCare reports')

@push('styles')
<style>
    .report-page{max-width:1180px;padding:clamp(24px,4vw,42px)}.report-back{display:inline-flex;margin-bottom:16px;color:var(--blue);font-size:.8rem;font-weight:850;text-decoration:none}.report-heading{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:20px}.report-eyebrow{margin:0 0 7px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.14em}.report-heading h1{margin:0;font-size:clamp(1.75rem,4vw,2.45rem);letter-spacing:-.04em}.report-heading p{margin:8px 0 0;color:var(--muted);line-height:1.5}.report-status{display:inline-block;padding:7px 10px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:.72rem;font-weight:850;white-space:nowrap}.report-notice{margin-bottom:18px}.report-meta{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:18px}.report-meta-card{padding:18px}.report-meta-card span{display:block;color:var(--muted);font-size:.68rem;font-weight:850;letter-spacing:.07em;text-transform:uppercase}.report-meta-card strong{display:block;margin-top:7px;font-size:.88rem;line-height:1.4}.report-results{padding:22px}.report-results-heading{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:16px}.report-results-heading h2{margin:0;font-size:1.1rem}.report-results-heading p{margin:5px 0 0;color:var(--muted);font-size:.82rem}.report-export{border:0;border-radius:9px;background:var(--blue);color:#fff;padding:10px 13px;font-size:.78rem;font-weight:850;cursor:pointer;white-space:nowrap}.metrics-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.metric-card{padding:16px;border:1px solid var(--line);border-radius:11px;background:#f8fafc}.metric-card>span{display:block;color:var(--muted);font-size:.7rem;font-weight:850;letter-spacing:.06em;text-transform:uppercase}.metric-card>strong{display:block;margin-top:8px;font-size:1.3rem}.metric-list{display:grid;gap:7px;margin:10px 0 0;padding:0;list-style:none}.metric-list li{display:flex;justify-content:space-between;gap:12px;padding-top:7px;border-top:1px solid #e2e8f0;color:var(--muted);font-size:.78rem}.metric-list b{color:var(--ink)}.report-no-results{padding:28px 8px;color:var(--muted);text-align:center}.report-filters{margin-top:16px;padding:18px;border:1px solid var(--line);border-radius:11px}.report-filters h3{margin:0 0 10px;font-size:.9rem}.report-filter-list{display:flex;gap:8px;flex-wrap:wrap}.report-filter{padding:6px 9px;border-radius:999px;background:#f1f5f9;color:#475569;font-size:.7rem;font-weight:750}@media(max-width:880px){.report-meta{grid-template-columns:repeat(2,minmax(0,1fr))}.metrics-grid{grid-template-columns:1fr 1fr}}@media(max-width:640px){.report-page{padding:24px 18px}.report-heading,.report-results-heading{flex-direction:column}.report-meta,.metrics-grid{grid-template-columns:1fr}.report-export{width:100%}}
</style>
@endpush

@section('content')
<div class="report-page">
    <a class="report-back" href="{{ route('reports.index') }}">← Back to reports</a>

    <header class="report-heading">
        <div>
            <p class="report-eyebrow">REPORT RUN #{{ $run->id }}</p>
            <h1>{{ $run->definition->name }}</h1>
            <p>{{ $run->definition->description ?: 'Authorized CityCare operational report.' }}</p>
        </div>
        <span class="report-status">{{ str($run->status)->headline() }}</span>
    </header>

    @if (session('status'))
        <div class="status report-notice" role="status">{{ session('status') }}</div>
    @endif

    @if ($run->status === \App\Models\ReportRun::STATUS_FAILED)
        <div class="error report-notice" role="alert">{{ \App\Services\ReportingService::FAILURE_MESSAGE }}</div>
    @endif

    <section class="report-meta" aria-label="Report details">
        <div class="card report-meta-card"><span>Requested by</span><strong>{{ $run->requester?->name ?? 'System' }}</strong></div>
        <div class="card report-meta-card"><span>Facility</span><strong>{{ $run->facility?->name ?? 'All facilities' }}</strong></div>
        <div class="card report-meta-card"><span>Started</span><strong>{{ $run->started_at?->format('d M Y, H:i') ?? 'Not started' }}</strong></div>
        <div class="card report-meta-card"><span>Completed</span><strong>{{ $run->completed_at?->format('d M Y, H:i') ?? 'In progress' }}</strong></div>
    </section>

    <section class="card report-results" aria-labelledby="report-results-heading">
        <div class="report-results-heading">
            <div>
                <h2 id="report-results-heading">Report results</h2>
                <p>Aggregated values only; underlying patient and transaction records remain in their protected workspaces.</p>
            </div>
            @if ($run->status === \App\Models\ReportRun::STATUS_COMPLETED)
                <form method="POST" action="{{ route('reports.export') }}">
                    @csrf
                    <input type="hidden" name="report_run" value="{{ $run->id }}">
                    <input type="hidden" name="format" value="csv">
                    <button class="report-export" type="submit">Export CSV</button>
                </form>
            @endif
        </div>

        @if ($run->status === \App\Models\ReportRun::STATUS_COMPLETED && $run->result_metadata)
            <div class="metrics-grid">
                @foreach ($run->result_metadata as $key => $value)
                    @continue($key === 'report')
                    <div class="metric-card">
                        <span>{{ str($key)->replace('_', ' ')->headline() }}</span>
                        @if (is_array($value))
                            <ul class="metric-list">
                                @forelse ($value as $itemKey => $itemValue)
                                    <li><span>{{ str((string) $itemKey)->replace('_', ' ')->headline() }}</span><b>{{ is_numeric($itemValue) ? number_format((float) $itemValue, 2) : $itemValue }}</b></li>
                                @empty
                                    <li><span>No grouped values</span></li>
                                @endforelse
                            </ul>
                        @else
                            <strong>{{ is_numeric($value) ? number_format((float) $value, 2) : $value }}</strong>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="report-no-results">No result data is available for this run.</div>
        @endif

        @if ($run->filters)
            <div class="report-filters">
                <h3>Applied filters</h3>
                <div class="report-filter-list">
                    @foreach ($run->filters as $key => $value)
                        <span class="report-filter">{{ str($key)->replace('_', ' ')->headline() }}: {{ $key === 'facility_id' ? ($run->facility?->name ?? $value) : $value }}</span>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
</div>
@endsection
