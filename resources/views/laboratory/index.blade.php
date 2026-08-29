@extends('layouts.app')

@section('title', 'Laboratory Workspace · CityCare Medical Center')

@push('styles')
<style>
    .diagnostic-page{max-width:1280px;padding:clamp(24px,4vw,42px)}.diagnostic-heading{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px}.diagnostic-eyebrow{margin:0 0 7px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.14em}.diagnostic-heading h1{margin:0;font-size:clamp(1.85rem,4vw,2.65rem);letter-spacing:-.045em}.diagnostic-heading p{max-width:760px;margin:9px 0 0;color:var(--muted);line-height:1.55}.diagnostic-meta{padding:9px 11px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--muted);font-size:.82rem;white-space:nowrap}.diagnostic-filter{display:grid;grid-template-columns:minmax(0,1fr) 170px auto;gap:10px;margin-bottom:18px;padding:18px}.diagnostic-filter input,.diagnostic-filter select{min-width:0;padding:11px 12px;border:1px solid var(--line);border-radius:10px;background:#fff}.diagnostic-filter button{border:0;border-radius:10px;background:var(--blue);color:#fff;padding:11px 16px;font-weight:800;cursor:pointer}.diagnostic-order{margin-bottom:16px;padding:22px}.diagnostic-order-header{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}.diagnostic-order-header h2{margin:0;font-size:1.05rem}.diagnostic-order-header p{margin:5px 0 0;color:var(--muted);font-size:.84rem;line-height:1.5}.diagnostic-status{display:inline-block;padding:6px 9px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:.72rem;font-weight:850;white-space:nowrap}.diagnostic-item{margin-top:14px;padding:16px;border:1px solid var(--line);border-radius:11px}.diagnostic-item-top{display:flex;justify-content:space-between;gap:14px;align-items:flex-start}.diagnostic-item h3{margin:0;font-size:.96rem}.diagnostic-item p{margin:5px 0 0;color:var(--muted);font-size:.82rem;line-height:1.45}.diagnostic-form{display:grid;grid-template-columns:1.15fr .65fr .95fr;gap:10px;margin-top:15px;padding-top:15px;border-top:1px solid #e5e7eb}.diagnostic-form label{display:grid;gap:6px;font-size:.78rem;font-weight:800}.diagnostic-form input,.diagnostic-form textarea{width:100%;min-width:0;padding:9px 10px;border:1px solid var(--line);border-radius:8px}.diagnostic-form .diagnostic-comments{grid-column:1/-1}.diagnostic-form-controls{grid-column:1/-1;display:flex;align-items:center;gap:12px;flex-wrap:wrap}.diagnostic-submit,.diagnostic-cancel{border:0;border-radius:9px;padding:10px 13px;background:var(--blue);color:#fff;font-size:.8rem;font-weight:800;cursor:pointer}.diagnostic-cancel{background:#fff;color:var(--red);border:1px solid #fecaca}.diagnostic-result{margin-top:14px;padding:12px;border-radius:9px;background:#f0fdf4;color:#14532d;font-size:.84rem;line-height:1.5}.diagnostic-empty{padding:34px 18px;text-align:center;color:var(--muted)}.diagnostic-pagination{margin-top:18px}@media(max-width:800px){.diagnostic-page{padding:24px 18px}.diagnostic-heading{flex-direction:column}.diagnostic-filter{grid-template-columns:1fr}.diagnostic-filter button{width:100%}.diagnostic-form{grid-template-columns:1fr}.diagnostic-form .diagnostic-comments,.diagnostic-form-controls{grid-column:auto}.diagnostic-order-header{flex-direction:column;gap:8px}}
</style>
@endpush

@section('content')
<section class="diagnostic-page">
    <div class="diagnostic-heading">
        <div>
            <p class="diagnostic-eyebrow">LABORATORY WORKSPACE</p>
            <h1>Laboratory work queue</h1>
            <p>Review pending diagnostic orders, record results against the requested test, and make resulting clinical state changes visible to the ordering team.</p>
        </div>
        <span class="diagnostic-meta">{{ $facility->name }}</span>
    </div>

    @if (session('status'))<div class="status" style="margin-bottom:18px">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="error" style="margin-bottom:18px">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <form class="card diagnostic-filter" method="GET" action="{{ route('laboratory.index') }}">
        <input name="search" value="{{ request('search') }}" placeholder="Search order, MRN, or patient name" aria-label="Search laboratory orders">
        <select name="status" aria-label="Filter laboratory order status">
            <option value="pending" @selected($status === 'pending')>Pending work</option>
            <option value="completed" @selected($status === 'completed')>Completed</option>
            <option value="cancelled" @selected($status === 'cancelled')>Cancelled</option>
        </select>
        <button type="submit">Filter orders</button>
    </form>

    @forelse ($orders as $order)
        <article class="card diagnostic-order">
            <div class="diagnostic-order-header">
                <div>
                    <h2>{{ $order->order_number }}</h2>
                    <p>
                        @if (auth()->user()->hasPermissionTo('patients.view'))
                            <a href="{{ route('patients.show', $order->patient) }}">{{ $order->patient->full_name }}</a>
                        @else
                            {{ $order->patient->full_name }}
                        @endif
                        · {{ $order->patient->medical_record_number }} · Ordered {{ $order->ordered_at?->format('d M Y H:i') }} by {{ $order->orderedBy?->name ?? 'Unknown user' }}
                    </p>
                    @if ($order->notes)<p>Clinical instructions: {{ $order->notes }}</p>@endif
                </div>
                <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
                    <span class="diagnostic-status">{{ str_replace('_', ' ', ucfirst($order->status)) }}</span>
                    @if (in_array($order->status, [\App\Models\LaboratoryOrder::STATUS_ORDERED, \App\Models\LaboratoryOrder::STATUS_IN_PROGRESS], true) && auth()->user()->hasPermissionTo('laboratory.work.manage'))
                        <form method="POST" action="{{ route('encounters.laboratory-orders.cancel', $order) }}">@csrf<button class="diagnostic-cancel" type="submit">Cancel order</button></form>
                    @endif
                </div>
            </div>

            @foreach ($order->items as $item)
                <section class="diagnostic-item">
                    <div class="diagnostic-item-top">
                        <div>
                            <h3>{{ $item->laboratoryTest?->name ?? 'Laboratory test' }}</h3>
                            <p>{{ $item->laboratoryTest?->code ?: 'No test code' }}@if ($item->laboratoryTest?->specimen_type) · Specimen: {{ $item->laboratoryTest->specimen_type }}@endif</p>
                        </div>
                        <span class="diagnostic-status">{{ str_replace('_', ' ', ucfirst($item->status)) }}</span>
                    </div>

                    @if ($item->result)
                        <div class="diagnostic-result">
                            <strong>Result:</strong> {{ $item->result->result_value }}@if ($item->result->unit) {{ $item->result->unit }}@endif
                            @if ($item->result->reference_range)<span> · Reference: {{ $item->result->reference_range }}</span>@endif
                            @if (! is_null($item->result->is_abnormal))<span> · {{ $item->result->is_abnormal ? 'Abnormal' : 'Within expected range' }}</span>@endif
                            @if ($item->result->comments)<div style="margin-top:4px">{{ $item->result->comments }}</div>@endif
                            <div style="margin-top:4px">Recorded {{ $item->result->recorded_at?->format('d M Y H:i') }} by {{ $item->result->recordedBy?->name ?? 'Unknown user' }}</div>
                        </div>
                    @elseif ($order->encounter?->isOpen() && auth()->user()->hasPermissionTo('laboratory.results.record') && ! $item->isCancelled())
                        <form class="diagnostic-form" method="POST" action="{{ route('encounters.laboratory-order-items.result.store', $item) }}">
                            @csrf
                            <label>Result value<input name="result_value" required value="{{ old('result_value') }}"></label>
                            <label>Unit<input name="unit" value="{{ old('unit', $item->laboratoryTest?->unit) }}"></label>
                            <label>Reference range<input name="reference_range" value="{{ old('reference_range', $item->laboratoryTest?->reference_range) }}"></label>
                            <label class="diagnostic-comments">Comments<textarea name="comments" rows="2">{{ old('comments') }}</textarea></label>
                            <div class="diagnostic-form-controls"><label style="display:flex;align-items:center;gap:7px"><input type="checkbox" name="is_abnormal" value="1" @checked(old('is_abnormal'))> Mark as abnormal</label><button class="diagnostic-submit" type="submit">Record result</button></div>
                        </form>
                    @elseif (! $item->isCancelled())
                        <p style="margin-top:14px">This item is awaiting an eligible result-entry workflow.</p>
                    @endif
                </section>
            @endforeach
        </article>
    @empty
        <section class="card diagnostic-empty">No laboratory orders match this filter. Pending work appears only for open clinical encounters.</section>
    @endforelse

    <div class="diagnostic-pagination">{{ $orders->links() }}</div>
</section>
@endsection
