@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">Appointments</h1>
            <p class="text-sm text-gray-600">Schedule, monitor, check in, complete, and cancel patient appointments.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if (auth()->user()->hasPermissionTo('patients.create'))
                <a href="{{ route('patients.create') }}" class="rounded border border-gray-300 bg-white px-4 py-2 text-gray-800">Register patient</a>
            @endif
            <a href="{{ route('appointments.create') }}" class="rounded bg-blue-600 px-4 py-2 text-white">Schedule appointment</a>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded border border-green-200 bg-green-50 p-3 text-green-800">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="rounded border border-red-200 bg-red-50 p-3 text-red-800">{{ $errors->first() }}</div>
    @endif

    <form method="GET" class="grid gap-3 rounded border bg-white p-4 md:grid-cols-4">
        <input name="search" value="{{ request('search') }}" placeholder="Appointment no., MRN or patient" class="rounded border px-3 py-2">
        <input type="date" name="date" value="{{ request('date') }}" class="rounded border px-3 py-2">
        <select name="status" class="rounded border px-3 py-2">
            <option value="">All statuses</option>
            @foreach ([\App\Models\Appointment::STATUS_SCHEDULED, \App\Models\Appointment::STATUS_CHECKED_IN, \App\Models\Appointment::STATUS_COMPLETED, \App\Models\Appointment::STATUS_CANCELLED, \App\Models\Appointment::STATUS_NO_SHOW] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
            @endforeach
        </select>
        <button class="rounded bg-gray-900 px-4 py-2 text-white">Filter</button>
    </form>

    <div class="overflow-x-auto rounded border bg-white">
        <table class="min-w-full text-sm">
            <thead class="border-b bg-gray-50 text-left">
                <tr><th class="p-3">Appointment</th><th class="p-3">Patient</th><th class="p-3">Department</th><th class="p-3">Service point</th><th class="p-3">Provider</th><th class="p-3">Time</th><th class="p-3">Status</th><th class="p-3">Actions</th></tr>
            </thead>
            <tbody>
            @forelse ($appointments as $appointment)
                <tr class="border-b">
                    <td class="p-3 font-medium">{{ $appointment->appointment_number }}</td>
                    <td class="p-3"><a class="font-medium text-blue-700 hover:underline" href="{{ route('patients.show', $appointment->patient) }}">{{ $appointment->patient->full_name }}</a><span class="mt-1 block text-xs text-gray-500">{{ $appointment->patient->medical_record_number }}</span></td>
                    <td class="p-3">{{ $appointment->department->name }}</td>
                    <td class="p-3">{{ $appointment->servicePoint->name }}</td>
                    <td class="p-3">{{ $appointment->provider?->name ?? 'Unassigned' }}</td>
                    <td class="p-3">{{ $appointment->scheduled_start->format('d M Y H:i') }} - {{ $appointment->scheduled_end->format('H:i') }}</td>
                    <td class="p-3">{{ str_replace('_', ' ', ucfirst($appointment->status)) }}</td>
                    <td class="space-x-1 p-3">
                        @if ($appointment->isScheduled())
                            <form method="POST" action="{{ route('appointments.check-in', $appointment) }}" class="inline">@csrf<button class="rounded border px-2 py-1">Check in</button></form>
                            <form method="POST" action="{{ route('appointments.cancel', $appointment) }}" class="inline">@csrf<button class="rounded border border-red-300 px-2 py-1 text-red-700">Cancel</button></form>
                        @elseif ($appointment->isCheckedIn())
                            <form method="POST" action="{{ route('appointments.complete', $appointment) }}" class="inline">@csrf<button class="rounded border px-2 py-1">Complete</button></form>
                            <form method="POST" action="{{ route('appointments.cancel', $appointment) }}" class="inline">@csrf<button class="rounded border border-red-300 px-2 py-1 text-red-700">Cancel</button></form>
                        @else
                            <span class="text-gray-500">No actions</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="p-6 text-center text-gray-500">No appointments found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $appointments->links() }}
</div>
@endsection
