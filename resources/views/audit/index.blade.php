@extends('layouts.app')

@section('title', 'Audit log | CityCare')

@push('styles')
<style>
    .audit-page{max-width:1380px;padding:clamp(24px,4vw,42px)}.audit-heading{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:22px}.audit-eyebrow{margin:0 0 7px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.14em}.audit-heading h1{margin:0;font-size:clamp(1.85rem,4vw,2.65rem);letter-spacing:-.045em}.audit-heading p{max-width:760px;margin:9px 0 0;color:var(--muted);line-height:1.55}.audit-scope{padding:9px 11px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--muted);font-size:.8rem;font-weight:750;white-space:nowrap}.audit-errors{margin-bottom:18px}.audit-errors ul{margin:6px 0 0;padding-left:20px}.audit-filter{margin-bottom:18px;padding:20px}.audit-filter-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.audit-filter label{display:grid;gap:6px;color:var(--ink);font-size:.76rem;font-weight:800}.audit-filter input,.audit-filter select{width:100%;min-width:0;padding:10px 11px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--ink)}.audit-filter-actions{display:flex;justify-content:flex-end;align-items:center;gap:10px;margin-top:15px}.audit-clear,.audit-submit{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:9px;font-size:.8rem;font-weight:850;text-decoration:none}.audit-clear{border:1px solid var(--line);background:#fff;color:var(--ink)}.audit-submit{border:1px solid var(--blue);background:var(--blue);color:#fff;cursor:pointer}.audit-card{padding:22px}.audit-card-heading{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:15px}.audit-card-heading h2{margin:0;font-size:1.08rem}.audit-card-heading p{margin:5px 0 0;color:var(--muted);font-size:.82rem;line-height:1.5}.audit-count{padding:6px 9px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:.7rem;font-weight:850;white-space:nowrap}.audit-table-wrap{overflow:auto}.audit-table{width:100%;border-collapse:collapse}.audit-table th,.audit-table td{padding:13px 10px;border-bottom:1px solid #edf2f7;text-align:left;vertical-align:top}.audit-table th{color:var(--muted);font-size:.68rem;font-weight:850;letter-spacing:.08em;text-transform:uppercase;white-space:nowrap}.audit-table td{font-size:.82rem}.audit-primary{display:block;color:var(--ink);font-weight:800}.audit-secondary{display:block;margin-top:4px;color:var(--muted);font-size:.74rem;line-height:1.4}.audit-action{display:inline-block;padding:5px 8px;border-radius:999px;background:#f1f5f9;color:#334155;font-size:.68rem;font-weight:850;white-space:nowrap}.audit-empty{padding:38px 18px!important;color:var(--muted);text-align:center!important}.audit-pagination{margin-top:18px}@media(max-width:1050px){.audit-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:720px){.audit-page{padding:24px 18px}.audit-heading,.audit-card-heading{flex-direction:column}.audit-scope{white-space:normal}.audit-filter-grid{grid-template-columns:1fr}.audit-filter-actions{align-items:stretch;flex-direction:column-reverse}.audit-clear,.audit-submit{width:100%}}
</style>
@endpush

@section('content')
<div class="audit-page">
    <header class="audit-heading">
        <div>
            <p class="audit-eyebrow">SECURITY &amp; COMPLIANCE</p>
            <h1>Audit log</h1>
            <p>Review recorded system activity without exposing request metadata or clinical change payloads. Filters remain limited to the facilities your account may supervise.</p>
        </div>
        <span class="audit-scope">{{ $isOrganizationWide ? 'Organization-wide access' : $availableFacilities->first()?->name }}</span>
    </header>

    @if ($errors->any())
        <div class="error audit-errors" role="alert">
            <strong>Review the selected filters.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form class="card audit-filter" method="GET" action="{{ route('audit.index') }}">
        <div class="audit-filter-grid">
            @if ($isOrganizationWide)
                <label>
                    Facility
                    <select name="facility_id">
                        <option value="">All active facilities</option>
                        @foreach ($availableFacilities as $facility)
                            <option value="{{ $facility->id }}" @selected((string) request('facility_id') === (string) $facility->id)>{{ $facility->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <label>
                Actor user ID
                <input type="number" name="actor_id" min="1" value="{{ request('actor_id') }}" placeholder="Any actor">
            </label>
            <label>
                Event type
                <input type="text" name="event_type" value="{{ request('event_type') }}" placeholder="e.g. billing.invoice">
            </label>
            <label>
                Action
                <input type="text" name="action" value="{{ request('action') }}" placeholder="e.g. updated">
            </label>
            <label>
                From date
                <input type="date" name="date_from" value="{{ request('date_from') }}">
            </label>
            <label>
                To date
                <input type="date" name="date_to" value="{{ request('date_to') }}">
            </label>
            <label>
                Rows per page
                <select name="per_page">
                    @foreach ([10, 25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected((int) request('per_page', 25) === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="audit-filter-actions">
            <a class="audit-clear" href="{{ route('audit.index') }}">Clear filters</a>
            <button class="audit-submit" type="submit">Apply filters</button>
        </div>
    </form>

    <section class="card audit-card" aria-labelledby="audit-results-heading">
        <div class="audit-card-heading">
            <div>
                <h2 id="audit-results-heading">Recorded activity</h2>
                <p>Newest events appear first. Sensitive before/after values, IP addresses, and user-agent data are intentionally excluded from this screen.</p>
            </div>
            <span class="audit-count">{{ number_format($events->total()) }} {{ str('event')->plural($events->total()) }}</span>
        </div>

        <div class="audit-table-wrap">
            <table class="audit-table">
                <thead>
                    <tr>
                        <th scope="col">When</th>
                        <th scope="col">Actor</th>
                        <th scope="col">Event</th>
                        <th scope="col">Action</th>
                        <th scope="col">Target</th>
                        <th scope="col">Facility</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td>
                                <span class="audit-primary">{{ $event->occurred_at?->format('d M Y') }}</span>
                                <span class="audit-secondary">{{ $event->occurred_at?->format('H:i:s') }}</span>
                            </td>
                            <td>
                                <span class="audit-primary">{{ $event->actor?->name ?? 'System process' }}</span>
                                <span class="audit-secondary">{{ $event->actor_id ? 'User #'.$event->actor_id : 'Automated event' }}</span>
                            </td>
                            <td>
                                <span class="audit-primary">{{ str($event->event_type)->replace(['.', '_'], ' ')->headline() }}</span>
                                <span class="audit-secondary">{{ $event->event_type }}</span>
                            </td>
                            <td><span class="audit-action">{{ str($event->action)->replace('_', ' ')->headline() }}</span></td>
                            <td>
                                <span class="audit-primary">{{ class_basename($event->auditable_type) }}</span>
                                <span class="audit-secondary">Record #{{ $event->auditable_id }}</span>
                            </td>
                            <td>{{ $event->facility?->name ?? 'Organization-wide' }}</td>
                        </tr>
                    @empty
                        <tr><td class="audit-empty" colspan="6">No audit events match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="audit-pagination">{{ $events->links() }}</div>
    </section>
</div>
@endsection
