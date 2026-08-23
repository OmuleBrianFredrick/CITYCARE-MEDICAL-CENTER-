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

    @if ($run->result_metadata)
        <pre>{{ json_encode($run->result_metadata, JSON_PRETTY_PRINT) }}</pre>
    @endif
</div>
@endsection
