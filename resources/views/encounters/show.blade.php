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
        <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start"><div><div style="color:#2563eb;font-size:.74rem;font-weight:900;letter-spacing:.14em">NURSING</div><h2 style="margin:6px 0 0">Vitals and triage</h2><p style="color:#627d98;margin-bottom:0">Capture the latest observations before or during clinical assessment.</p></div><div style="padding:7px 11px;border-radius:999px;background:#f1f5f9;font-weight:800">{{ $encounter->vitals->count() }} {{ Str::plural('entry', $encounter->vitals->count()) }}</div></div>
        @if($encounter->isOpen() && auth()->user()?->hasPermissionTo('clinical.vitals.manage'))
            <form method="POST" action="{{ route('encounters.vitals.store', $encounter) }}" style="margin-top:20px;padding:18px;border:1px solid #dbeafe;border-radius:12px;background:#f8fbff">@csrf
                <h3 style="margin-top:0">Record vitals</h3>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:12px">
                    <label><strong>Temperature °C</strong><input name="temperature_c" type="number" min="25" max="45" step="0.1" value="{{ old('temperature_c') }}" style="width:100%;margin-top:6px"></label>
                    <label><strong>Pulse bpm</strong><input name="pulse_bpm" type="number" min="20" max="250" value="{{ old('pulse_bpm') }}" style="width:100%;margin-top:6px"></label>
                    <label><strong>Respiratory rate</strong><input name="respiratory_rate" type="number" min="5" max="80" value="{{ old('respiratory_rate') }}" style="width:100%;margin-top:6px"></label>
                    <label><strong>Oxygen saturation %</strong><input name="oxygen_saturation" type="number" min="50" max="100" step="0.1" value="{{ old('oxygen_saturation') }}" style="width:100%;margin-top:6px"></label>
                    <label><strong>Systolic BP</strong><input name="systolic_bp" type="number" min="50" max="300" value="{{ old('systolic_bp') }}" style="width:100%;margin-top:6px"></label>
                    <label><strong>Diastolic BP</strong><input name="diastolic_bp" type="number" min="20" max="200" value="{{ old('diastolic_bp') }}" style="width:100%;margin-top:6px"></label>
                    <label><strong>Weight kg</strong><input name="weight_kg" type="number" min="0.1" max="500" step="0.01" value="{{ old('weight_kg') }}" style="width:100%;margin-top:6px"></label>
                    <label><strong>Height cm</strong><input name="height_cm" type="number" min="20" max="250" step="0.01" value="{{ old('height_cm') }}" style="width:100%;margin-top:6px"></label>
                    <label><strong>Pain score (0-10)</strong><input name="pain_score" type="number" min="0" max="10" value="{{ old('pain_score') }}" style="width:100%;margin-top:6px"></label>
                    <label><strong>Glucose mmol/L</strong><input name="capillary_glucose_mmol_l" type="number" min="0.1" max="100" step="0.1" value="{{ old('capillary_glucose_mmol_l') }}" style="width:100%;margin-top:6px"></label>
                </div>
                <label style="display:block;margin-top:12px"><strong>Nursing notes</strong><textarea name="notes" rows="3" style="display:block;width:100%;margin-top:6px">{{ old('notes') }}</textarea></label>
                <button style="margin-top:12px;background:#2563eb;color:#fff;border:0;border-radius:10px;padding:11px 16px;font-weight:800">Save vitals</button>
            </form>
        @endif
        <div style="margin-top:18px">@forelse($encounter->vitals as $vital)<div style="padding:12px 0;border-bottom:1px solid #e5e7eb"><strong>{{ $vital->created_at?->format('d M Y H:i') }}</strong><div style="color:#627d98;margin-top:5px">Temperature: {{ $vital->temperature_c ?? '—' }} °C · Pulse: {{ $vital->pulse_bpm ?? '—' }} bpm · Respiratory rate: {{ $vital->respiratory_rate ?? '—' }} · BP: {{ $vital->systolic_bp ?? '—' }}/{{ $vital->diastolic_bp ?? '—' }} · SpO₂: {{ $vital->oxygen_saturation ?? '—' }}%</div><div style="color:#627d98;margin-top:4px">Recorded by {{ $vital->recorder->name }}</div>@if($vital->notes)<div style="color:#627d98;margin-top:4px">{{ $vital->notes }}</div>@endif</div>@empty<p style="color:#627d98">No vitals recorded yet.</p>@endforelse</div>
    </div>

    <div class="card" style="margin-top:18px;padding:24px">
        <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start"><div><div style="color:#2563eb;font-size:.74rem;font-weight:900;letter-spacing:.14em">CLINICAL DOCUMENTATION</div><h2 style="margin:6px 0 0">Clinical notes</h2><p style="color:#627d98;margin-bottom:0">Draft structured consultation notes, then finalize them when the entry is complete.</p></div><div style="padding:7px 11px;border-radius:999px;background:#f1f5f9;font-weight:800">{{ $encounter->notes->count() }} {{ Str::plural('note', $encounter->notes->count()) }}</div></div>
        @if($encounter->isOpen() && auth()->user()?->hasPermissionTo('clinical.notes.manage'))
            <details style="margin-top:20px;padding:0 18px 18px;border:1px solid #dbeafe;border-radius:12px;background:#f8fbff"><summary style="padding:18px 0;cursor:pointer;font-weight:800">Add clinical note</summary>
                <form method="POST" action="{{ route('encounters.notes.store', $encounter) }}" style="display:grid;gap:12px">@csrf
                    <label><strong>Chief complaint</strong><textarea name="chief_complaint" rows="2" style="display:block;width:100%;margin-top:6px">{{ old('chief_complaint') }}</textarea></label>
                    <label><strong>History of present illness</strong><textarea name="history_of_present_illness" rows="3" style="display:block;width:100%;margin-top:6px">{{ old('history_of_present_illness') }}</textarea></label>
                    <label><strong>Medical history</strong><textarea name="medical_history" rows="3" style="display:block;width:100%;margin-top:6px">{{ old('medical_history') }}</textarea></label>
                    <label><strong>Examination</strong><textarea name="examination" rows="3" style="display:block;width:100%;margin-top:6px">{{ old('examination') }}</textarea></label>
                    <label><strong>Assessment</strong><textarea name="assessment" rows="3" style="display:block;width:100%;margin-top:6px">{{ old('assessment') }}</textarea></label>
                    <label><strong>Clinical diagnosis summary</strong><textarea name="diagnosis" rows="2" style="display:block;width:100%;margin-top:6px">{{ old('diagnosis') }}</textarea></label>
                    <label><strong>Treatment plan summary</strong><textarea name="treatment_plan" rows="3" style="display:block;width:100%;margin-top:6px">{{ old('treatment_plan') }}</textarea></label>
                    <label><strong>Follow-up plan</strong><textarea name="follow_up_plan" rows="2" style="display:block;width:100%;margin-top:6px">{{ old('follow_up_plan') }}</textarea></label>
                    <button style="background:#2563eb;color:#fff;border:0;border-radius:10px;padding:11px 16px;font-weight:800">Save clinical note</button>
                </form>
            </details>
        @endif
        <div style="margin-top:18px">@forelse($encounter->notes as $note)<div style="padding:12px 0;border-bottom:1px solid #e5e7eb"><div style="display:flex;justify-content:space-between;gap:12px"><strong>{{ $note->diagnosis ?: ($note->chief_complaint ?: 'Clinical note') }}</strong><span style="font-weight:800;color:#2563eb">{{ $note->isFinalized() ? 'Finalized' : 'Draft' }}</span></div>@if($note->chief_complaint)<div style="margin-top:6px"><strong>Chief complaint:</strong> {{ $note->chief_complaint }}</div>@endif @if($note->history_of_present_illness)<div style="margin-top:6px"><strong>History:</strong> {{ $note->history_of_present_illness }}</div>@endif @if($note->examination)<div style="margin-top:6px"><strong>Examination:</strong> {{ $note->examination }}</div>@endif @if($note->assessment)<div style="margin-top:6px"><strong>Assessment:</strong> {{ $note->assessment }}</div>@endif @if($note->treatment_plan)<div style="margin-top:6px"><strong>Treatment:</strong> {{ $note->treatment_plan }}</div>@endif<div style="color:#627d98;margin-top:6px">By {{ $note->author->name }}</div>@if(!$note->isFinalized() && auth()->user()?->hasPermissionTo('clinical.notes.manage'))<form method="POST" action="{{ route('encounters.notes.finalize', $note) }}" style="margin-top:9px">@csrf<button style="border:1px solid #2563eb;border-radius:8px;background:#fff;color:#2563eb;padding:7px 10px;font-weight:800">Finalize note</button></form>@endif</div>@empty<p style="color:#627d98">No clinical notes recorded yet.</p>@endforelse</div>
    </div>

    <div class="card" style="margin-top:18px;padding:24px">
        <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start"><div><div style="color:#2563eb;font-size:.74rem;font-weight:900;letter-spacing:.14em">ASSESSMENT</div><h2 style="margin:6px 0 0">Diagnoses</h2><p style="color:#627d98;margin-bottom:0">Record primary and secondary diagnoses separately from the narrative clinical note.</p></div><div style="padding:7px 11px;border-radius:999px;background:#f1f5f9;font-weight:800">{{ $encounter->diagnoses->count() }} {{ Str::plural('diagnosis', $encounter->diagnoses->count()) }}</div></div>
        @if($encounter->isOpen() && auth()->user()?->hasPermissionTo('clinical.diagnoses.manage'))
            <form method="POST" action="{{ route('encounters.diagnoses.store', $encounter) }}" style="margin-top:20px;padding:18px;border:1px solid #dbeafe;border-radius:12px;background:#f8fbff">@csrf
                <h3 style="margin-top:0">Record diagnosis</h3>
                <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px"><label><strong>Diagnosis</strong><input name="diagnosis" required value="{{ old('diagnosis') }}" style="width:100%;margin-top:6px"></label><label><strong>Code</strong><input name="diagnosis_code" value="{{ old('diagnosis_code') }}" style="width:100%;margin-top:6px"></label><label><strong>Type</strong><select name="type" style="width:100%;margin-top:6px"><option value="primary" @selected(old('type') === 'primary')>Primary</option><option value="secondary" @selected(old('type') === 'secondary')>Secondary</option></select></label></div>
                <label style="display:block;margin-top:12px"><strong>Notes</strong><textarea name="notes" rows="2" style="display:block;width:100%;margin-top:6px">{{ old('notes') }}</textarea></label>
                <button style="margin-top:12px;background:#2563eb;color:#fff;border:0;border-radius:10px;padding:11px 16px;font-weight:800">Save diagnosis</button>
            </form>
        @endif
        <div style="margin-top:18px">@forelse($encounter->diagnoses as $diagnosis)<div style="padding:12px 0;border-bottom:1px solid #e5e7eb"><div style="display:flex;justify-content:space-between;gap:12px"><strong>{{ $diagnosis->diagnosis }}</strong><span style="font-weight:800;color:#2563eb">{{ ucfirst($diagnosis->type) }}</span></div><div style="color:#627d98;margin-top:4px">{{ $diagnosis->diagnosis_code ?: 'No diagnosis code' }} · Recorded by {{ $diagnosis->recorder->name }}</div>@if($diagnosis->notes)<div style="color:#627d98;margin-top:6px">{{ $diagnosis->notes }}</div>@endif</div>@empty<p style="color:#627d98">No diagnoses recorded yet.</p>@endforelse</div>
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

    @if(auth()->user()?->hasPermissionTo('billing.view'))
        <div class="card" style="margin-top:18px;padding:24px">
            <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start"><div><div style="color:#2563eb;font-size:.74rem;font-weight:900;letter-spacing:.14em">BILLING</div><h2 style="margin:6px 0 0">Encounter billing</h2><p style="color:#627d98;margin-bottom:0">View charges, invoice status, totals, payments, and outstanding balance without exposing payment-management authority.</p></div><div style="padding:7px 11px;border-radius:999px;background:#f1f5f9;font-weight:800">{{ $encounter->charges->count() }} {{ Str::plural('charge', $encounter->charges->count()) }}</div></div>

            @if($encounter->isOpen() && auth()->user()?->hasPermissionTo('billing.charges.manage'))
                <form method="POST" action="{{ route('billing.charges.store', $encounter->patient) }}" style="margin-top:20px;padding:18px;border:1px solid #dbeafe;border-radius:12px;background:#f8fbff">
                    @csrf
                    <input type="hidden" name="encounter_id" value="{{ $encounter->id }}">
                    <h3 style="margin-top:0">Link clinical activity to a charge</h3>
                    @if($billableServices->isNotEmpty())
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
                            <label><strong>Billable service</strong><select name="billable_service_id" required style="width:100%;margin-top:6px"><option value="">Select service</option>@foreach($billableServices as $service)<option value="{{ $service->id }}">{{ $service->name }}{{ $service->code ? ' · '.$service->code : '' }}</option>@endforeach</select></label>
                            <label><strong>Current price</strong><select name="service_price_id" required style="width:100%;margin-top:6px"><option value="">Select price</option>@foreach($billableServices as $service)@foreach($service->prices as $price)<option value="{{ $price->id }}">{{ $service->name }} · {{ number_format((float) $price->amount, 2) }} {{ $price->currency }}</option>@endforeach @endforeach</select></label>
                            <label><strong>Quantity</strong><input name="quantity" type="number" min="0.001" step="0.001" value="1" required style="width:100%;margin-top:6px"></label>
                        </div>
                        <textarea name="description" rows="2" placeholder="Clinical service or billing description…" style="width:100%;margin-top:12px"></textarea>
                        <button style="margin-top:10px;background:#2563eb;color:#fff;border:0;border-radius:10px;padding:11px 16px;font-weight:800">Create linked charge</button>
                    @else
                        <p style="color:#627d98;margin-bottom:0">No active billable services with current prices are configured for this facility.</p>
                    @endif
                </form>
            @endif

            <div style="margin-top:20px">
                @forelse($encounter->charges as $charge)
                    <div style="padding:14px 0;border-bottom:1px solid #e5e7eb">
                        <div style="display:flex;justify-content:space-between;gap:12px"><div><strong>{{ $charge->billableService?->name ?? 'Clinical charge' }}</strong><div style="color:#627d98;margin-top:4px">{{ $charge->description }} · Qty {{ $charge->quantity }} · {{ number_format((float) $charge->total, 2) }} {{ $charge->currency }}</div></div><span style="font-weight:800;color:#2563eb">{{ ucfirst($charge->status) }}</span></div>
                    </div>
                @empty
                    <p style="color:#627d98;margin-top:20px">No charges have been linked to this encounter.</p>
                @endforelse
            </div>

            <div style="margin-top:20px">
                @forelse($encounter->invoices as $invoice)
                    <div style="padding:16px;border:1px solid #e5e7eb;border-radius:10px;margin-top:10px">
                        <div style="display:flex;justify-content:space-between;gap:12px"><div><strong>{{ $invoice->invoice_number }}</strong><div style="color:#627d98;margin-top:4px">{{ $invoice->lineItems->count() }} {{ Str::plural('line item', $invoice->lineItems->count()) }}</div></div><span style="font-weight:800;color:#2563eb">{{ str_replace('_', ' ', ucfirst($invoice->status)) }}</span></div>
                        <div style="margin-top:12px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px"><div><strong>Invoice total</strong><div style="color:#627d98;margin-top:4px">{{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency }}</div></div><div><strong>Amount paid</strong><div style="color:#627d98;margin-top:4px">{{ number_format((float) $invoice->paid_amount, 2) }} {{ $invoice->currency }}</div></div><div><strong>Outstanding balance</strong><div style="color:#627d98;margin-top:4px">{{ number_format((float) $invoice->balance_due, 2) }} {{ $invoice->currency }}</div></div></div>
                    </div>
                @empty
                    <p style="color:#627d98;margin-top:20px">No invoices have been issued for this encounter.</p>
                @endforelse
            </div>
        </div>
    @endif

    @if($encounter->treatmentPlans->isNotEmpty() || auth()->user()?->hasPermissionTo('clinical.treatment-plans.manage'))
        <div class="card" style="margin-top:18px;padding:24px">
            <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start"><div><div style="color:#2563eb;font-size:.74rem;font-weight:900;letter-spacing:.14em">CARE PLAN</div><h2 style="margin:6px 0 0">Treatment plans</h2><p style="color:#627d98;margin-bottom:0">Set the active plan and follow-up date, then mark it complete or cancelled as care progresses.</p></div><div style="padding:7px 11px;border-radius:999px;background:#f1f5f9;font-weight:800">{{ $encounter->treatmentPlans->count() }} {{ Str::plural('plan', $encounter->treatmentPlans->count()) }}</div></div>
            @if($encounter->isOpen() && auth()->user()?->hasPermissionTo('clinical.treatment-plans.manage'))
                <form method="POST" action="{{ route('encounters.treatment-plans.store', $encounter) }}" style="margin-top:20px;padding:18px;border:1px solid #dbeafe;border-radius:12px;background:#f8fbff">@csrf
                    <label><strong>Plan</strong><textarea name="plan" rows="3" required style="display:block;width:100%;margin-top:6px">{{ old('plan') }}</textarea></label>
                    <label style="display:block;margin-top:12px"><strong>Follow-up date</strong><input type="date" name="follow_up_date" value="{{ old('follow_up_date') }}" style="display:block;margin-top:6px"></label>
                    <button style="margin-top:12px;background:#2563eb;color:#fff;border:0;border-radius:10px;padding:11px 16px;font-weight:800">Create treatment plan</button>
                </form>
            @endif
            <div style="margin-top:18px">@forelse($encounter->treatmentPlans as $plan)<div style="padding:12px 0;border-bottom:1px solid #e5e7eb"><div style="display:flex;justify-content:space-between;gap:12px"><strong>Plan #{{ $plan->id }}</strong><span style="font-weight:800;color:#2563eb">{{ ucfirst($plan->status) }}</span></div><div style="color:#627d98;margin-top:4px">{{ $plan->plan }}</div>@if($plan->follow_up_date)<div style="color:#627d98;margin-top:4px">Follow-up: {{ $plan->follow_up_date->format('d M Y') }}</div>@endif<div style="color:#627d98;margin-top:4px">Created by {{ $plan->author->name }}</div>@if($plan->isActive() && auth()->user()?->hasPermissionTo('clinical.treatment-plans.manage'))<div style="display:flex;gap:8px;margin-top:10px"><form method="POST" action="{{ route('encounters.treatment-plans.complete', $plan) }}">@csrf<button style="border:1px solid #15803d;border-radius:8px;background:#fff;color:#15803d;padding:7px 10px;font-weight:800">Mark complete</button></form><form method="POST" action="{{ route('encounters.treatment-plans.cancel', $plan) }}">@csrf<button style="border:1px solid #b91c1c;border-radius:8px;background:#fff;color:#b91c1c;padding:7px 10px;font-weight:800">Cancel plan</button></form></div>@endif</div>@empty<p style="color:#627d98">No treatment plans recorded yet.</p>@endforelse</div>
        </div>
    @endif

    @if($encounter->referrals->isNotEmpty() || auth()->user()?->hasPermissionTo('clinical.referrals.manage'))
        <div class="card" style="margin-top:18px;padding:24px">
            <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start"><div><div style="color:#2563eb;font-size:.74rem;font-weight:900;letter-spacing:.14em">CARE COORDINATION</div><h2 style="margin:6px 0 0">Referrals</h2><p style="color:#627d98;margin-bottom:0">Create and track referrals through acceptance and completion.</p></div><div style="padding:7px 11px;border-radius:999px;background:#f1f5f9;font-weight:800">{{ $encounter->referrals->count() }} {{ Str::plural('referral', $encounter->referrals->count()) }}</div></div>
            @if($encounter->isOpen() && auth()->user()?->hasPermissionTo('clinical.referrals.manage'))
                <form method="POST" action="{{ route('encounters.referrals.store', $encounter) }}" style="margin-top:20px;padding:18px;border:1px solid #dbeafe;border-radius:12px;background:#f8fbff">@csrf
                    <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px"><label><strong>Referred to</strong><input name="referred_to" required value="{{ old('referred_to') }}" style="width:100%;margin-top:6px"></label><label><strong>Priority</strong><select name="priority" style="width:100%;margin-top:6px"><option value="routine" @selected(old('priority') === 'routine')>Routine</option><option value="urgent" @selected(old('priority') === 'urgent')>Urgent</option><option value="emergency" @selected(old('priority') === 'emergency')>Emergency</option></select></label></div>
                    <label style="display:block;margin-top:12px"><strong>Reason</strong><textarea name="reason" required rows="3" style="display:block;width:100%;margin-top:6px">{{ old('reason') }}</textarea></label>
                    <label style="display:block;margin-top:12px"><strong>Notes</strong><textarea name="notes" rows="2" style="display:block;width:100%;margin-top:6px">{{ old('notes') }}</textarea></label>
                    <button style="margin-top:12px;background:#2563eb;color:#fff;border:0;border-radius:10px;padding:11px 16px;font-weight:800">Create referral</button>
                </form>
            @endif
            <div style="margin-top:18px">@forelse($encounter->referrals as $referral)<div style="padding:14px 0;border-bottom:1px solid #e5e7eb"><div style="display:flex;justify-content:space-between;gap:12px"><strong>Referral #{{ $referral->id }} · {{ $referral->referred_to }}</strong><span style="font-weight:800;color:#2563eb">{{ ucfirst($referral->status) }} · {{ ucfirst($referral->priority) }}</span></div><div style="color:#627d98;margin-top:4px">{{ $referral->reason }}</div>@if($referral->notes)<div style="color:#627d98;margin-top:4px">{{ $referral->notes }}</div>@endif <div style="color:#627d98;margin-top:4px">Referred by {{ $referral->referrer->name }}</div>@if($referral->attachments->isNotEmpty())<div style="margin-top:8px"><strong>Attachments</strong>@foreach($referral->attachments as $attachment)<div style="margin-top:4px;color:#627d98">{{ $attachment->file_name }}</div>@endforeach</div>@endif @if(auth()->user()?->hasPermissionTo('clinical.referrals.manage'))<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">@if($referral->isPending())<form method="POST" action="{{ route('encounters.referrals.accept', $referral) }}">@csrf<button style="border:1px solid #2563eb;border-radius:8px;background:#fff;color:#2563eb;padding:7px 10px;font-weight:800">Accept referral</button></form>@endif @if($referral->isPending() || $referral->isAccepted())<form method="POST" action="{{ route('encounters.referrals.complete', $referral) }}">@csrf<button style="border:1px solid #15803d;border-radius:8px;background:#fff;color:#15803d;padding:7px 10px;font-weight:800">Complete referral</button></form><form method="POST" action="{{ route('encounters.referrals.cancel', $referral) }}">@csrf<button style="border:1px solid #b91c1c;border-radius:8px;background:#fff;color:#b91c1c;padding:7px 10px;font-weight:800">Cancel referral</button></form>@endif</div>@endif</div>@empty<p style="color:#627d98">No referrals recorded yet.</p>@endforelse</div>
        </div>
    @endif

    @if($encounter->isOpen() && auth()->user()?->hasPermissionTo('clinical.encounters.update'))<div class="card" style="margin-top:18px;padding:24px"><h2 style="margin-top:0">Close encounter</h2><p style="color:#627d98">Confirm the consultation summary before closing the encounter. Closed encounters can no longer accept clinical documentation.</p><form method="POST" action="{{ route('encounters.close', $encounter) }}" style="display:grid;gap:12px">@csrf<textarea name="summary" rows="5" placeholder="Enter consultation closing summary…">{{ $encounter->summary }}</textarea><div style="display:flex;gap:10px"><button style="background:#15803d;color:#fff;border:0;border-radius:10px;padding:12px 16px;font-weight:800">Close encounter</button><button type="submit" formmethod="POST" formaction="{{ route('encounters.cancel', $encounter) }}" style="background:#b91c1c;color:#fff;border:0;border-radius:10px;padding:12px 16px;font-weight:800">Cancel encounter</button></div></form></div>@endif
</div>
@endsection
