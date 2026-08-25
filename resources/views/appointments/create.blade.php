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
            <label for="patient-search" class="mb-1 block text-sm font-medium">Patient</label>
            <input id="patient-search" type="search" autocomplete="off" placeholder="Search patient by name, MRN, phone, or national ID" class="mb-2 w-full rounded border px-3 py-2" aria-describedby="patient-search-status">
            <p id="patient-search-status" class="mb-2 text-sm text-gray-600" aria-live="polite">Type at least two characters to search the full patient registry.</p>
            <select id="patient_id" name="patient_id" required aria-label="Selected patient" class="w-full rounded border px-3 py-2">
                <option value="">Select patient</option>
                @if ($selectedPatient)
                    <option value="{{ $selectedPatient->id }}" selected>{{ $selectedPatient->full_name }} — {{ $selectedPatient->medical_record_number }}</option>
                @endif
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

@push('scripts')
<script>
    (() => {
        const input = document.getElementById('patient-search');
        const select = document.getElementById('patient_id');
        const status = document.getElementById('patient-search-status');
        const endpoint = @json(route('patients.search'));
        let delay;
        let activeRequest;

        input.addEventListener('input', () => {
            const query = input.value.trim();
            window.clearTimeout(delay);

            if (query.length < 2) {
                status.textContent = 'Type at least two characters to search the full patient registry.';
                return;
            }

            delay = window.setTimeout(async () => {
                activeRequest?.abort();
                activeRequest = new AbortController();
                status.textContent = 'Searching patients…';

                try {
                    const response = await fetch(`${endpoint}?q=${encodeURIComponent(query)}`, {
                        headers: {'Accept': 'application/json'},
                        signal: activeRequest.signal,
                    });
                    const payload = await response.json();

                    if (! response.ok) {
                        throw new Error(payload.message ?? 'The patient search could not be completed.');
                    }

                    const selectedId = select.value;
                    select.replaceChildren(new Option('Select patient', ''));

                    payload.data.forEach((patient) => {
                        const detail = patient.phone ? ` · ${patient.phone}` : '';
                        select.add(new Option(`${patient.full_name} — ${patient.medical_record_number}${detail}`, patient.id, false, patient.id === selectedId));
                    });

                    status.textContent = payload.data.length
                        ? `${payload.data.length} matching ${payload.data.length === 1 ? 'patient' : 'patients'} found. Select one to schedule the appointment.`
                        : 'No active patients match that search.';
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        status.textContent = error.message;
                    }
                }
            }, 250);
        });
    })();
</script>
@endpush
