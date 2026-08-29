@extends('layouts.app')

@section('title', 'Pharmacy Workspace · CityCare Medical Center')

@push('styles')
<style>
    .pharmacy-page{max-width:1280px;padding:clamp(24px,4vw,42px)}.pharmacy-heading{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px}.pharmacy-eyebrow{margin:0 0 7px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.14em}.pharmacy-heading h1{margin:0;font-size:clamp(1.85rem,4vw,2.65rem);letter-spacing:-.045em}.pharmacy-heading p{max-width:760px;margin:9px 0 0;color:var(--muted);line-height:1.55}.pharmacy-meta{padding:9px 11px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--muted);font-size:.82rem;white-space:nowrap}.pharmacy-filter{display:grid;grid-template-columns:minmax(0,1fr) 170px auto;gap:10px;margin-bottom:18px;padding:18px}.pharmacy-filter input,.pharmacy-filter select{min-width:0;padding:11px 12px;border:1px solid var(--line);border-radius:10px;background:#fff}.pharmacy-filter button{border:0;border-radius:10px;background:var(--blue);color:#fff;padding:11px 16px;font-weight:800;cursor:pointer}.prescription-card{margin-bottom:16px;padding:22px}.prescription-header{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}.prescription-header h2{margin:0;font-size:1.05rem}.prescription-header p{margin:5px 0 0;color:var(--muted);font-size:.84rem;line-height:1.5}.prescription-status{display:inline-block;padding:6px 9px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:.72rem;font-weight:850;white-space:nowrap}.prescription-cancel{border:1px solid #fecaca;border-radius:9px;background:#fff;color:var(--red);padding:9px 11px;font-size:.78rem;font-weight:800;cursor:pointer}.medicine-item{margin-top:14px;padding:16px;border:1px solid var(--line);border-radius:11px}.medicine-item-top{display:flex;justify-content:space-between;gap:14px;align-items:flex-start}.medicine-item h3{margin:0;font-size:.96rem}.medicine-item p{margin:5px 0 0;color:var(--muted);font-size:.82rem;line-height:1.45}.dispensing-progress{margin-top:12px;padding:11px;border-radius:9px;background:#f8fafc;color:var(--muted);font-size:.82rem}.dispensing-form{display:grid;grid-template-columns:1.5fr .75fr .75fr .9fr;gap:10px;margin-top:15px;padding-top:15px;border-top:1px solid #e5e7eb}.dispensing-form label{display:grid;gap:6px;font-size:.78rem;font-weight:800}.dispensing-form input,.dispensing-form select,.dispensing-form textarea{width:100%;min-width:0;padding:9px 10px;border:1px solid var(--line);border-radius:8px}.dispensing-form .dispensing-notes{grid-column:1/-1}.dispensing-submit{border:0;border-radius:9px;padding:10px 13px;background:var(--blue);color:#fff;font-size:.8rem;font-weight:800;cursor:pointer}.pharmacy-note{margin-top:12px;color:var(--muted);font-size:.78rem;line-height:1.45}.pharmacy-empty{padding:34px 18px;text-align:center;color:var(--muted)}.pharmacy-pagination{margin-top:18px}@media(max-width:900px){.pharmacy-page{padding:24px 18px}.pharmacy-heading{flex-direction:column}.pharmacy-filter{grid-template-columns:1fr}.pharmacy-filter button{width:100%}.dispensing-form{grid-template-columns:1fr 1fr}.dispensing-form .dispensing-notes{grid-column:1/-1}.prescription-header{flex-direction:column;gap:8px}}@media(max-width:540px){.dispensing-form{grid-template-columns:1fr}.dispensing-form .dispensing-notes{grid-column:auto}}
</style>
@endpush

@section('content')
<section class="pharmacy-page">
    <div class="pharmacy-heading">
        <div>
            <p class="pharmacy-eyebrow">PHARMACY WORKSPACE</p>
            <h1>Prescription queue</h1>
            <p>Process pending prescriptions one item at a time, select the issuing store, and let the existing inventory service post the controlled stock movement.</p>
        </div>
        <span class="pharmacy-meta">{{ $facility->name }}</span>
    </div>

    @if (session('status'))<div class="status" style="margin-bottom:18px">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="error" style="margin-bottom:18px">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <form class="card pharmacy-filter" method="GET" action="{{ route('pharmacy.index') }}">
        <input name="search" value="{{ request('search') }}" placeholder="Search prescription, MRN, or patient name" aria-label="Search prescriptions">
        <select name="status" aria-label="Filter prescription status">
            <option value="pending" @selected($status === 'pending')>Pending work</option>
            <option value="completed" @selected($status === 'completed')>Completed</option>
            <option value="cancelled" @selected($status === 'cancelled')>Cancelled</option>
        </select>
        <button type="submit">Filter prescriptions</button>
    </form>

    @forelse ($prescriptions as $prescription)
        <article class="card prescription-card">
            <div class="prescription-header">
                <div>
                    <h2>{{ $prescription->prescription_number }}</h2>
                    <p>
                        @if (auth()->user()->hasPermissionTo('patients.view'))
                            <a href="{{ route('patients.show', $prescription->patient) }}">{{ $prescription->patient->full_name }}</a>
                        @else
                            {{ $prescription->patient->full_name }}
                        @endif
                        · {{ $prescription->patient->medical_record_number }} · Prescribed {{ $prescription->prescribed_at?->format('d M Y H:i') }} by {{ $prescription->prescriber?->name ?? 'Unknown user' }}
                    </p>
                    @if ($prescription->notes)<p>Prescription notes: {{ $prescription->notes }}</p>@endif
                </div>
                <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
                    <span class="prescription-status">{{ str_replace('_', ' ', ucfirst($prescription->status)) }}</span>
                    @if (in_array($prescription->status, [\App\Models\Prescription::STATUS_PRESCRIBED, \App\Models\Prescription::STATUS_PARTIALLY_DISPENSED], true) && auth()->user()->hasPermissionTo('pharmacy.work.manage'))
                        <form method="POST" action="{{ route('encounters.prescriptions.cancel', $prescription) }}">@csrf<button class="prescription-cancel" type="submit">Cancel prescription</button></form>
                    @endif
                </div>
            </div>

            @foreach ($prescription->items as $item)
                @php($dispensed = (float) $item->dispensingItems->sum('quantity_dispensed'))
                @php($remaining = max(0, round((float) $item->quantity - $dispensed, 3)))
                <section class="medicine-item">
                    <div class="medicine-item-top">
                        <div>
                            <h3>{{ $item->medication?->name ?? 'Medication' }}</h3>
                            <p>{{ $item->medication?->generic_name }}@if ($item->formulation) · {{ $item->formulation->strength }} {{ $item->formulation->unit }}@endif</p>
                            <p>Prescribed: {{ $item->quantity }}{{ $item->dose ? ' · '.$item->dose : '' }}{{ $item->route ? ' · '.$item->route : '' }}{{ $item->frequency ? ' · '.$item->frequency : '' }}{{ $item->duration ? ' · '.$item->duration : '' }}</p>
                            @if ($item->instructions)<p>Instructions: {{ $item->instructions }}</p>@endif
                        </div>
                        <span class="prescription-status">{{ str_replace('_', ' ', ucfirst($item->status)) }}</span>
                    </div>
                    <div class="dispensing-progress"><strong>Dispensing progress:</strong> {{ $dispensed }} / {{ $item->quantity }} issued · {{ $remaining }} remaining</div>

                    @if ($remaining > 0 && ! $prescription->isCancelled() && auth()->user()->hasPermissionTo('pharmacy.dispensing.manage'))
                        @if ($stores->isNotEmpty())
                            <form class="dispensing-form" method="POST" action="{{ route('encounters.prescriptions.dispense', $prescription) }}">
                                @csrf
                                <input type="hidden" name="items[0][prescription_item_id]" value="{{ $item->id }}">
                                <label>Issuing store<select name="store_id" required><option value="">Select store</option>@foreach ($stores as $store)<option value="{{ $store->id }}">{{ $store->name }} · {{ $store->code }}</option>@endforeach</select></label>
                                <label>Quantity to issue<input name="items[0][quantity_dispensed]" type="number" min="0.001" max="{{ $remaining }}" step="0.001" value="{{ old('items.0.quantity_dispensed', $remaining) }}" required></label>
                                <label>Batch number<input name="items[0][batch_number]" value="{{ old('items.0.batch_number') }}"></label>
                                <label>Expiry date<input name="items[0][expiry_date]" type="date" value="{{ old('items.0.expiry_date') }}"></label>
                                <label class="dispensing-notes">Dispensing notes<textarea name="notes" rows="2">{{ old('notes') }}</textarea></label>
                                <div><button class="dispensing-submit" type="submit">Issue medication</button></div>
                            </form>
                        @else
                            <p class="pharmacy-note">No active inventory store is configured for this facility. A store must be available before medication can be issued.</p>
                        @endif
                    @endif
                </section>
            @endforeach
        </article>
    @empty
        <section class="card pharmacy-empty">No prescriptions match this filter. Pending prescriptions appear here until all items are issued or the prescription is cancelled.</section>
    @endforelse

    <div class="pharmacy-pagination">{{ $prescriptions->links() }}</div>
</section>
@endsection
