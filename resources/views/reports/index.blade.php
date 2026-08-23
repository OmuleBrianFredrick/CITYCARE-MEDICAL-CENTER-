@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Reports</h1>

    @if (session('status'))
        <div>{{ session('status') }}</div>
    @endif

    @foreach ($definitions as $definition)
        <section>
            <h2>{{ $definition->name }}</h2>
            <p>{{ $definition->description }}</p>

            <form method="POST" action="{{ route('reports.run', $definition) }}">
                @csrf
                <label>
                    Facility ID
                    <input type="number" name="facility_id" min="1">
                </label>
                <label>
                    From
                    <input type="date" name="date_from">
                </label>
                <label>
                    To
                    <input type="date" name="date_to">
                </label>
                <button type="submit">Run report</button>
            </form>
        </section>
    @endforeach
</div>
@endsection
