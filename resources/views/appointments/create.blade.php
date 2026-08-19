@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-4xl space-y-6 p-6">
    <div>
        <h1 class="text-2xl font-semibold">Schedule appointment</h1>
        <p class="text-sm text-gray-600">{{ $facility->name }}</p>
    </div>

    @if ($errors->any())
        <div class="rounded border border-red-200 bg-red-50 p-3 text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('appointments.store') }}" class="space-y-5 rounded border bg-white p-6">
        @csrf
        <input type="hidden" name="facility_id" value="{{ $facility->id }}">

        <div>
            <label class="mb-1 block text-sm font-medium">Patient</label>
            <select name="patient_id" required class="w-full rounded border px-3 py-2">
                <option value="">Select patient</option>
                @foreach ($patients as $patient)
                    <option value="{{ $patient->id }}" @selected(old('patient_id') == $patient->id)>{{ $patient->full_name }} — {{ $patient->medical_record_number }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Department</label>
                <select name="department_id" required class="w-full rounded border px-3 py-2">
                    <option value="">Select department</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>{{ $department->name }} ({{ $department->code }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Service point</label>
                <select name="service_point_id" required class="w-full rounded border px-3 py-2">
                    <option value="">Select service point</option>
                    @foreach ($departments as $department)
                        @foreach ($department->servicePoints as $servicePoint)
                            <option value="{{ $servicePoint->id }}" data-department="{{ $department->id }}" @selected(old('service_point_id') == $servicePoint->id)>{{ $servicePoint->name }} — {{ $department->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Provider</label>
            <select name="provider_id" class="w-full rounded border px-3 py-2">
                <option value="">Unassigned</option>
                @foreach ($providers as $provider)
                    <option value="{{ $provider->id }}" @selected(old('provider_id') == $provider->id)>{{ $provider->name }} — {{ $provider->email }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Start</label>
                <input type="datetime-local" name="scheduled_start" value="{{ old('scheduled_start') }}" required class="w-full rounded border px-3 py-2">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">End</label>
                <input type="datetime-local" name="scheduled_end" value="{{ old('scheduled_end') }}" required class="w-full rounded border px-3 py-2">
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Reason</label>
            <input name="reason" value="{{ old('reason') }}" maxlength="255" class="w-full rounded border px-3 py-2">
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Notes</label>
            <textarea name="notes" rows="4" class="w-full rounded border px-3 py-2">{{ old('notes') }}</textarea>
        </div>

        <div class="flex gap-3">
            <button class="rounded bg-blue-600 px-4 py-2 text-white">Schedule appointment</button>
            <a href="{{ route('appointments.index') }}" class="rounded border px-4 py-2">Cancel</a>
        </div>
    </form>
</div>
@endsection
