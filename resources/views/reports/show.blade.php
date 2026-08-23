@extends('layouts.app')

@section('content')
<div class="container">
    <h1>{{ $run->definition->name }}</h1>
    <p>Status: {{ $run->status }}</p>
    <p>Requested by: {{ $run->requester->name }}</p>

    @if ($run->facility)
        <p>Facility: {{ $run->facility->name }}</p>
    @endif

    @if ($run->error_message)
        <div>{{ $run->error_message }}</div>
    @endif

    @if ($run->status === \App\Models\ReportRun::STATUS_COMPLETED)
        <form method="POST" action="{{ route('reports.export') }}">
            @csrf
            <input type="hidden" name="report_run" value="{{ $run->id }}">
            <input type="hidden" name="format" value="csv">
            <button type="submit">Export CSV</button>
        </form>
    @endif

    @if ($run->result_metadata)
        <pre>{{ json_encode($run->result_metadata, JSON_PRETTY_PRINT) }}</pre>
    @endif
</div>
@endsection
