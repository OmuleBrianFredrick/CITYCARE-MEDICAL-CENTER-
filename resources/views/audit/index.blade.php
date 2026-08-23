@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Audit Log</h1>

    <form method="GET" action="{{ route('audit.index') }}">
        <label>Actor ID <input type="number" name="actor_id" min="1" value="{{ request('actor_id') }}"></label>
        <label>Facility ID <input type="number" name="facility_id" min="1" value="{{ request('facility_id') }}"></label>
        <label>Event type <input type="text" name="event_type" value="{{ request('event_type') }}"></label>
        <label>Action <input type="text" name="action" value="{{ request('action') }}"></label>
        <label>From <input type="date" name="date_from" value="{{ request('date_from') }}"></label>
        <label>To <input type="date" name="date_to" value="{{ request('date_to') }}"></label>
        <button type="submit">Filter</button>
    </form>

    <table>
        <thead><tr><th>When</th><th>Actor</th><th>Event</th><th>Action</th><th>Target</th><th>Facility</th></tr></thead>
        <tbody>
        @forelse ($events as $event)
            <tr>
                <td>{{ $event->occurred_at }}</td>
                <td>{{ $event->actor?->name ?? 'System' }}</td>
                <td>{{ $event->event_type }}</td>
                <td>{{ $event->action }}</td>
                <td>{{ $event->auditable_type }} #{{ $event->auditable_id }}</td>
                <td>{{ $event->facility?->name ?? 'N/A' }}</td>
            </tr>
        @empty
            <tr><td colspan="6">No audit events found.</td></tr>
        @endforelse
        </tbody>
    </table>

    {{ $events->links() }}
</div>
@endsection
