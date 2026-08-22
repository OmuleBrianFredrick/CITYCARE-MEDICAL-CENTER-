@extends('layouts.app')
@section('title', 'Clinical Encounter · CityCare Medical Center')
@section('content')
<div style="padding:34px 24px;max-width:1100px">
    @if(session('status'))<div class="status" style="margin-bottom:18px">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="status" style="margin-bottom:18px;background:#fef2f2;color:#991b1b">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <div style="display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px">
        <div><div style="color:#2563eb;font-size:.74rem;font-weight:900;letter-spacing:.14em">CLINICAL ENCOUNTER</div><h1 style="margin:6px 0">{{ $encounter->encounter_number }}</h1><p style="color:#627d98">{{ $encounter->patient->full_name }} · {{ $encounter->patient->medical_record_number }}</p></div>
        <div style="padding:8px 12px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-weight:800">{{ ucfirst($encounter->status) }}</div>
    </div>
    <div class="card" style="padding:24px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px">
        <div><strong>Clinician</strong><div style="color:#627d98;margin-top:5px">{{ $encounter->clinician->name }}</div></div>
        <div><strong>Department</strong><div style="color:#627d98;margin-top:5px">{{ $encounter->department->name }}</div></div>
        <div><strong>Service point</strong><div style="color:#627d98;margin-top:5px">{{ $encounter->servicePoint->name }}</div></div>
        <div><strong>Started</strong><div style="color:#627d98;margin-top:5px">{{ $encounter->started_at?->format('d M Y H:i') }}</div></div>
        <div style="grid-column:1/-1"><strong>Summary</strong><div style="color:#627d98;margin-top:5px">{{ $encounter->summary ?: 'No summary recorded yet.' }}</div></div>
    </div>

    <div class="card" style="margin-top:18px;padding:24px">
        <h2 style="margin-top:0">Vitals</h2>
        @forelse($encounter->vitals as $vital)<div style="padding:12px 0;border-bottom:1px solid #e5e7eb"><strong>{{ $vital->created_at?->format('d M Y H:i') }}</strong><div style="color:#627d98;margin-top:5px">Temperature: {{ $vital->temperature_c ?? '—' }} °C · Pulse: {{ $vital->pulse_bpm ?? '—' }} bpm · Respiratory rate: {{ $vital->respiratory_rate ?? '—' }} · BP: {{ $vital->systolic_bp ?? '—' }}/{{ $vital->diastolic_bp ?? '—' }} · SpO₂: {{ $vital->oxygen_saturation ?? '—' }}%</div><div style="color:#627d98;margin-top:4px">Recorded by {{ $vital->recorder->name }}</div>@if($vital->notes)<div style="color:#627d98;margin-top:4px">{{ $vital->notes }}</div>@endif</div>@empty<p style="color:#627d98">No vitals recorded yet.</p>@endforelse
    </div>

    <div class="card" style="margin-top:18px;padding:24px">
        <h2 style="margin-top:0">Clinical notes</h2>
        @forelse($encounter->notes as $note)<div style="padding:12px 0;border-bottom:1px solid #e5e7eb"><div style="display:flex;justify-content:space-between;gap:12px"><strong>{{ $note->diagnosis ?: ($note->chief_complaint ?: 'Clinical note') }}</strong><span style="font-weight:800;color:#2563eb">{{ $note->isFinalized() ? 'Finalized' : 'Draft' }}</span></div>@if($note->chief_complaint)<div style="margin-top:6px"><strong>Chief complaint:</strong> {{ $note->chief_complaint }}</div>@endif @if($note->history_of_present_illness)<div style="margin-top:6px"><strong>History:</strong> {{ $note->history_of_present_illness }}</div>@endif @if($note->examination)<div style="margin-top:6px"><strong>Examination:</strong> {{ $note->examination }}</div>@endif @if($note->assessment)<div style="margin-top:6px"><strong>Assessment:</strong> {{ $note->assessment }}</div>@endif @if($note->treatment_plan)<div style="margin-top:6px"><strong>Treatment:</strong> {{ $note->treatment_plan }}</div>@endif<div style="color:#627d98;margin-top:6px">By {{ $note->author->name }}</div></div>@empty<p style="color:#627d98">No clinical notes recorded yet.</p>@endforelse
    </div>

    <div class="card" style="margin-top:18px;padding:24px">
        <h2 style="margin-top:0">Diagnoses</h2>
        @forelse($encounter->diagnoses as $diagnosis)<div style="padding:12px 0;border-bottom:1px solid #e5e7eb"><div style="display:flex;justify-content:space-between;gap:12px"><strong>{{ $diagnosis->diagnosis }}</strong><span style="font-weight:800;color:#2563eb">{{ ucfirst($diagnosis->type) }}</span></div><div style="color:#627d98;margin-top:4px">{{ $diagnosis->diagnosis_code ?: 'No diagnosis code' }} · Recorded by {{ $diagnosis->recorder->name }}</div>@if($diagnosis->notes)<div style="color:#627d98;margin-top:6px">{{ $diagnosis->notes }}</div>@endif</div>@empty<p style="color:#627d98">No diagnoses recorded yet.</p>@endforelse
    </div>

    <div class="card" style="margin-top:18px;padding:24px">
        <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start"><div><div style="color:#2563eb;font-size:.74rem;font-weight:900;letter-spacing:.14em">DIAGNOSTICS</div><h2 style="margin:6px 0 0">Laboratory workflow</h2><p style="color:#627d98;margin-bottom:0">Create orders, follow each test through its status, and review completed results.</p></div><div style="padding:7px 11px;border-radius:999px;background:#f1f5f9;font-weight:800">{{ $encounter->laboratoryOrders->count() }} {{ Str::plural('order', $encounter->laboratoryOrders->count()) }}</div></div>

        @if($encounter->isOpen() && auth()->user()?->hasPermissionTo('laboratory.orders.create'))
            <form method="POST" action="{{ route('encounters.laboratory-orders.store', $encounter) }}" style="margin-top:20px;padding:18px;border:1px solid #dbeafe;border-radius:12px;background:#f8fbff">
                @csrf
                <h3 style="margin-top:0">Create laboratory order</h3>
                @if($laboratoryTests->isNotEmpty())
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px">
                        @foreach($laboratoryTests as $test)
                            <label style="display:flex;gap:9px;align-items:flex-start;padding:10px;border:1px solid #e5e7eb;border-radius:9px;background:#fff;cursor:pointer"><input type="checkbox" name="test_ids[]" value="{{ $test->id }}" @checked(in_array($test->id, old('test_ids', [])))><span><strong>{{ $test->name }}</strong>@if($test->code)<small style="display:block;color:#627d98">{{ $test->code }}</small>@endif @if($test->specimen_type)<small style="display:block;color:#627d98">Specimen: {{ $test->specimen_type }}</small>@endif</span></label>
                        @endforeach
                    </div>
                    <textarea name="notes" rows="3" placeholder="Clinical notes or instructions for the laboratory…" style="width:100%;margin-top:12px">{{ old('notes') }}</textarea>
                    <button style="margin-top:10px;background:#2563eb;color:#fff;border:0;border-radius:10px;padding:11px 16px;font-weight:800">Create laboratory order</button>
                @else
                    <p style="color:#627d98;margin-bottom:0">No active laboratory tests are currently configured for this facility.</p>
                @endif
            </form>
        @endif

        <div style="margin-top:20px">
            @forelse($encounter->laboratoryOrders as $order)
                <div style="padding:18px 0;border-bottom:1px solid #e5e7eb">
                    <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start"><div><strong>{{ $order->order_number }}</strong><div style="color:#627d98;margin-top:4px">Ordered {{ $order->ordered_at?->format('d M Y H:i') }} by {{ $order->orderedBy?->name ?? 'Unknown user' }}</div>@if($order->notes)<div style="color:#627d98;margin-top:6px">{{ $order->notes }}</div>@endif</div><span style="padding:6px 10px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-weight:800">{{ str_replace('_', ' ', ucfirst($order->status)) }}</span></div>
                    <div style="margin-top:14px;display:grid;gap:10px">
                        @foreach($order->items as $item)
                            <div style="padding:12px;border:1px solid #e5e7eb;border-radius:10px"><div style="display:flex;justify-content:space-between;gap:12px"><div><strong>{{ $item->laboratoryTest?->name ?? 'Laboratory test' }}</strong>@if($item->laboratoryTest?->code)<span style="color:#627d98"> · {{ $item->laboratoryTest->code }}</span>@endif</div><span style="font-weight:800;color:#2563eb">{{ str_replace('_', ' ', ucfirst($item->status)) }}</span></div>
                                @if($item->result)
                                    <div style="margin-top:8px;padding:10px;background:#f0fdf4;border-radius:8px"><strong>Result:</strong> {{ $item->result->result_value }}@if($item->result->unit) {{ $item->result->unit }}@endif @if($item->result->reference_range)<div style="color:#627d98;margin-top:4px">Reference range: {{ $item->result->reference_range }}</div>@endif @if(!is_null($item->result->is_abnormal))<div style="margin-top:4px;font-weight:700">{{ $item->result->is_abnormal ? 'Abnormal result' : 'Within expected range' }}</div>@endif @if($item->result->comments)<div style="color:#627d98;margin-top:4px">{{ $item->result->comments }}</div>@endif<div style="color:#627d98;margin-top:4px">Recorded {{ $item->result->recorded_at?->format('d M Y H:i') }} by {{ $item->result->recordedBy?->name ?? 'Unknown user' }}</div></div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <p style="color:#627d98;margin-top:20px">No laboratory orders have been created for this encounter.</p>
            @endforelse
        </div>
    </div>

    <div class="card" style="margin-top:18px;padding:24px">
        <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start"><div><div style="color:#2563eb;font-size:.74rem;font-weight:900;letter-spacing:.14em">PHARMACY</div><h2 style="margin:6px 0 0">Pharmacy & medication</h2><p style="color:#627d98;margin-bottom:0">Create prescriptions, follow prescription status, and review dispensing progress.</p></div><div style="padding:7px 11px;border-radius:999px;background:#f1f5f9;font-weight:800">{{ $encounter->prescriptions->count() }} {{ Str::plural('prescription', $encounter->prescriptions->count()) }}</div></div>

        @if($encounter->isOpen() && auth()->user()?->hasPermissionTo('pharmacy.prescriptions.create'))
            <form method="POST" action="{{ route('encounters.prescriptions.store', $encounter) }}" style="margin-top:20px;padding:18px;border:1px solid #dbeafe;border-radius:12px;background:#f8fbff">
                @csrf
                <h3 style="margin-top:0">Create prescription</h3>
                @if($pharmacyMedications->isNotEmpty())
                    <div style="display:grid;gap:12px">
                        @foreach($pharmacyMedications as $medication)
                            <label style="display:grid;grid-template-columns:auto 1fr;gap:9px;align-items:start;padding:10px;border:1px solid #e5e7eb;border-radius:9px;background:#fff;cursor:pointer">
                                <input type="checkbox" name="medications[{{ $medication->id }}][selected]" value="1">
                                <span><strong>{{ $medication->name }}</strong><small style="display:block;color:#627d98">{{ $medication->generic_name }}{{ $medication->dosage_form ? ' · '.$medication->dosage_form : '' }}</small></span>
                            </label>
                            <input type="hidden" name="medications[{{ $medication->id }}][medication_id]" value="{{ $medication->id }}">
                            <input type="hidden" name="medications[{{ $medication->id }}][quantity]" value="1">
                        @endforeach
                    </div>
                    <textarea name="notes" rows="3" placeholder="Prescription notes or instructions…" style="width:100%;margin-top:12px">{{ old('notes') }}</textarea>
                    <button style="margin-top:10px;background:#2563eb;color:#fff;border:0;border-radius:10px;padding:11px 16px;font-weight:800">Create prescription</button>
                @else
                    <p style="color:#627d98;margin-bottom:0">No active medications are currently configured for this facility.</p>
                @endif
            </form>
        @endif

        <div style="margin-top:20px">
            @forelse($encounter->prescriptions as $prescription)
                <div style="padding:18px 0;border-bottom:1px solid #e5e7eb">
                    <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start">
                        <div><strong>{{ $prescription->prescription_number }}</strong><div style="color:#627d98;margin-top:4px">Prescribed {{ $prescription->prescribed_at?->format('d M Y H:i') }} by {{ $prescription->prescriber?->name ?? 'Unknown user' }}</div>@if($prescription->notes)<div style="color:#627d98;margin-top:6px">{{ $prescription->notes }}</div>@endif</div>
                        <span style="padding:6px 10px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-weight:800">{{ str_replace('_', ' ', ucfirst($prescription->status)) }}</span>
                    </div>
                    <div style="margin-top:14px;display:grid;gap:10px">
                        @foreach($prescription->items as $item)
                            <div style="padding:12px;border:1px solid #e5e7eb;border-radius:10px">
                                <div style="display:flex;justify-content:space-between;gap:12px"><div><strong>{{ $item->medication?->name ?? 'Medication' }}</strong>@if($item->formulation)<span style="color:#627d98"> · {{ $item->formulation->strength }} {{ $item->formulation->unit }}</span>@endif</div><span style="font-weight:800;color:#2563eb">{{ str_replace('_', ' ', ucfirst($item->status)) }}</span></div>
                                <div style="color:#627d98;margin-top:5px">Quantity: {{ $item->quantity }}{{ $item->dose ? ' · '.$item->dose : '' }}{{ $item->frequency ? ' · '.$item->frequency : '' }}{{ $item->duration ? ' · '.$item->duration : '' }}</div>
                                @php($dispensed = (float) $item->dispensingItems->sum('quantity_dispensed'))
                                <div style="margin-top:8px;padding:10px;background:#f8fafc;border-radius:8px"><strong>Dispensing status:</strong> {{ $dispensed }} / {{ $item->quantity }} dispensed @if($dispensed > 0 && $dispensed < (float) $item->quantity) <span style="font-weight:700;color:#2563eb">· Partially dispensed</span>@elseif($dispensed >= (float) $item->quantity) <span style="font-weight:700;color:#15803d">· Fully dispensed</span>@else <span style="color:#627d98">· Not yet dispensed</span>@endif</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <p style="color:#627d98;margin-top:20px">No prescriptions have been created for this encounter.</p>
            @endforelse
        </div>
    </div>

    @if($encounter->treatmentPlans->isNotEmpty())<div class="card" style="margin-top:18px;padding:24px"><h2 style="margin-top:0">Treatment plans</h2>@forelse($encounter->treatmentPlans as $plan)<div style="padding:12px 0;border-bottom:1px solid #e5e7eb"><strong>Plan #{{ $plan->id }}</strong><span style="margin-left:8px;font-weight:800;color:#2563eb">{{ ucfirst($plan->status) }}</span><div style="color:#627d98;margin-top:4px">{{ $plan->plan }}</div>@if($plan->follow_up_date)<div style="color:#627d98;margin-top:4px">Follow-up: {{ $plan->follow_up_date->format('d M Y') }}</div>@endif<div style="color:#627d98;margin-top:4px">Created by {{ $plan->author->name }}</div></div>@empty<p style="color:#627d98">No treatment plans recorded yet.</p>@endforelse</div>@endif

    @if($encounter->referrals->isNotEmpty())<div class="card" style="margin-top:18px;padding:24px"><h2 style="margin-top:0">Referrals</h2>@forelse($encounter->referrals as $referral)<div style="padding:12px 0;border-bottom:1px solid #e5e7eb"><strong>Referral #{{ $referral->id }} · {{ $referral->referred_to }}</strong><span style="margin-left:8px;font-weight:800;color:#2563eb">{{ ucfirst($referral->status) }}</span><div style="color:#627d98;margin-top:4px">{{ $referral->reason }}</div><div style="color:#627d98;margin-top:4px">Referred by {{ $referral->referrer->name }}</div>@if($referral->attachments->isNotEmpty())<div style="margin-top:8px"><strong>Attachments</strong>@foreach($referral->attachments as $attachment)<div style="margin-top:4px">{{ $attachment->file_name }}</div>@endforeach</div>@endif</div>@empty<p style="color:#627d98">No referrals recorded yet.</p>@endforelse</div>@endif

    @if($encounter->isOpen())<div class="card" style="margin-top:18px;padding:24px"><h2 style="margin-top:0">Close encounter</h2><form method="POST" action="{{ route('encounters.close', $encounter) }}" style="display:grid;gap:12px">@csrf<textarea name="summary" rows="5" placeholder="Enter consultation closing summary…">{{ $encounter->summary }}</textarea><div style="display:flex;gap:10px"><button style="background:#15803d;color:#fff;border:0;border-radius:10px;padding:12px 16px;font-weight:800">Close encounter</button><button type="submit" formmethod="POST" formaction="{{ route('encounters.cancel', $encounter) }}" style="background:#b91c1c;color:#fff;border:0;border-radius:10px;padding:12px 16px;font-weight:800">Cancel encounter</button></div></form></div>@endif
</div>
@endsection
