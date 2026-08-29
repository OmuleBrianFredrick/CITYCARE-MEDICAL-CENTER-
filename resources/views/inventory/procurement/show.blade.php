@extends('layouts.app')

@section('title', 'Purchase Order · CityCare Medical Center')

@push('styles')
<style>
    .po-page{max-width:1240px;padding:clamp(24px,4vw,42px)}.po-heading{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px}.po-eyebrow{margin:0 0 7px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.14em}.po-heading h1{margin:0;font-size:clamp(1.8rem,4vw,2.55rem);letter-spacing:-.045em}.po-heading p{margin:8px 0 0;color:var(--muted)}.po-heading-actions{display:flex;gap:9px;align-items:center;flex-wrap:wrap;justify-content:flex-end}.po-back{display:inline-flex;padding:9px 12px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--blue);font-size:.78rem;font-weight:850;text-decoration:none}.po-status{display:inline-flex;padding:8px 11px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:.72rem;font-weight:850}.po-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(320px,.75fr);gap:18px;align-items:start}.po-stack{display:grid;gap:18px}.po-panel{padding:22px}.po-panel h2{margin:0;font-size:1.08rem}.po-panel>p{margin:6px 0 0;color:var(--muted);font-size:.81rem;line-height:1.5}.po-meta{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.po-meta div{padding:11px;border-radius:9px;background:#f8fafc}.po-meta span{display:block;color:var(--muted);font-size:.66rem;font-weight:850;text-transform:uppercase}.po-meta strong{display:block;margin-top:4px;font-size:.82rem}.po-items{width:100%;border-collapse:collapse;margin-top:15px;font-size:.79rem}.po-items th,.po-items td{padding:10px 8px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}.po-items th{color:var(--muted);font-size:.66rem;text-transform:uppercase;letter-spacing:.06em}.po-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px;margin-top:15px}.po-form label{display:grid;gap:6px;color:#334155;font-size:.75rem;font-weight:850}.po-form input,.po-form select,.po-form textarea{width:100%;min-width:0;padding:9px 10px;border:1px solid var(--line);border-radius:8px;background:#fff}.po-form .full{grid-column:1/-1}.po-button{border:0;border-radius:9px;background:var(--blue);color:#fff;padding:10px 13px;font-size:.78rem;font-weight:850;cursor:pointer}.po-button.success{background:#15803d}.po-button.danger{background:#fff;color:#b91c1c;border:1px solid #fecaca}.po-submit-card{padding:16px;border:1px solid #bfdbfe;border-radius:11px;background:#eff6ff}.po-submit-card p{margin:0 0 11px;color:#1e3a8a;font-size:.8rem;line-height:1.5}.receive-item{margin-top:14px;padding:16px;border:1px solid var(--line);border-radius:11px}.receive-item h3{margin:0;font-size:.94rem}.receive-item>p{margin:5px 0 0;color:var(--muted);font-size:.78rem}.receipt{padding:14px 0;border-bottom:1px solid var(--line)}.receipt-top{display:flex;justify-content:space-between;gap:12px}.receipt h3{margin:0;font-size:.9rem}.receipt p{margin:5px 0 0;color:var(--muted);font-size:.77rem;line-height:1.5}.po-empty{padding:22px 4px;text-align:center;color:var(--muted);font-size:.82rem}@media(max-width:950px){.po-grid{grid-template-columns:1fr}.po-meta{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:700px){.po-page{padding:24px 18px}.po-heading{flex-direction:column}.po-heading-actions{justify-content:flex-start}.po-meta,.po-form{grid-template-columns:1fr}.po-form .full{grid-column:auto}.po-items{display:block;overflow-x:auto;white-space:nowrap}}
</style>
@endpush

@section('content')
<section class="po-page">
    <div class="po-heading">
        <div><p class="po-eyebrow">PURCHASE ORDER</p><h1>{{ $purchaseOrder->order_number }}</h1><p>{{ $purchaseOrder->supplier->name }} · {{ $purchaseOrder->store->name }}</p></div>
        <div class="po-heading-actions"><a class="po-back" href="{{ route('inventory.procurement.index') }}">← Inventory workspace</a><span class="po-status">{{ str_replace('_', ' ', ucfirst($purchaseOrder->status)) }}</span></div>
    </div>

    @if (session('status'))<div class="status" style="margin-bottom:18px">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="error" style="margin-bottom:18px">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <div class="po-grid">
        <div class="po-stack">
            <section class="card po-panel">
                <div class="po-meta">
                    <div><span>Supplier</span><strong>{{ $purchaseOrder->supplier->name }}</strong></div>
                    <div><span>Receiving store</span><strong>{{ $purchaseOrder->store->name }}</strong></div>
                    <div><span>Order total</span><strong>{{ $purchaseOrder->facility->currency ?? '' }} {{ number_format((float) $purchaseOrder->total, 2) }}</strong></div>
                    <div><span>Submitted</span><strong>{{ $purchaseOrder->ordered_at?->format('d M Y') ?? 'Draft' }}</strong></div>
                </div>
                @if ($purchaseOrder->notes)<p style="margin:15px 0 0;color:var(--muted)">{{ $purchaseOrder->notes }}</p>@endif
                <table class="po-items">
                    <thead><tr><th>Item</th><th>Ordered</th><th>Received</th><th>Remaining</th><th>Unit cost</th><th>Line total</th></tr></thead>
                    <tbody>
                    @foreach ($purchaseOrder->items as $item)
                        @php($received = (float) $item->goodsReceiptItems->sum('quantity_received'))
                        @php($remaining = max(0, (float) $item->quantity_ordered - $received))
                        <tr><td><strong>{{ $item->inventoryItem->name }}</strong><br><span style="color:var(--muted)">{{ $item->inventoryItem->code ?: $item->inventoryItem->sku }}</span></td><td>{{ $item->quantity_ordered }} {{ $item->inventoryItem->unit }}</td><td>{{ number_format($received, 3) }}</td><td><strong>{{ number_format($remaining, 3) }}</strong></td><td>{{ number_format((float) $item->unit_cost, 2) }}</td><td>{{ number_format((float) $item->line_total, 2) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </section>

            @if ($purchaseOrder->isDraft() && auth()->user()->hasPermissionTo('inventory.manage'))
                <section class="card po-panel">
                    <h2>Add catalogue item</h2><p>Draft contents can be changed until the order is submitted.</p>
                    @if ($inventoryItems->isNotEmpty())
                        <form class="po-form" method="POST" action="{{ route('inventory.procurement.items.store', $purchaseOrder) }}">
                            @csrf
                            <label class="full">Inventory item<select name="inventory_item_id" required><option value="">Select another item</option>@foreach ($inventoryItems as $inventoryItem)<option value="{{ $inventoryItem->id }}" @selected((string) old('inventory_item_id') === (string) $inventoryItem->id)>{{ $inventoryItem->name }} · {{ $inventoryItem->code ?: ($inventoryItem->sku ?: $inventoryItem->unit) }}</option>@endforeach</select></label>
                            <label>Quantity<input type="number" name="quantity_ordered" min="0.001" step="0.001" value="{{ old('quantity_ordered', 1) }}" required></label>
                            <label>Unit cost<input type="number" name="unit_cost" min="0" step="0.01" value="{{ old('unit_cost', 0) }}" required></label>
                            <div class="full"><button class="po-button" type="submit">Add item</button></div>
                        </form>
                    @else
                        <div class="po-empty">Every active facility item is already on this order.</div>
                    @endif
                </section>
            @endif

            @if (in_array($purchaseOrder->status, [\App\Models\PurchaseOrder::STATUS_ORDERED, \App\Models\PurchaseOrder::STATUS_PARTIALLY_RECEIVED], true) && auth()->user()->hasPermissionTo('inventory.manage'))
                <section class="card po-panel">
                    <h2>Receive stock</h2><p>Post only the quantity physically received. Each receipt updates stock balances and creates a traceable movement.</p>
                    @php($receivableItems = $purchaseOrder->items->filter(fn ($item) => (float) $item->goodsReceiptItems->sum('quantity_received') < (float) $item->quantity_ordered))
                    @forelse ($receivableItems as $item)
                        @php($received = (float) $item->goodsReceiptItems->sum('quantity_received'))
                        @php($remaining = max(0, (float) $item->quantity_ordered - $received))
                        <article class="receive-item">
                            <h3>{{ $item->inventoryItem->name }}</h3><p>{{ number_format($remaining, 3) }} {{ $item->inventoryItem->unit }} remaining from {{ $item->quantity_ordered }} ordered.</p>
                            <form class="po-form" method="POST" action="{{ route('inventory.procurement.receive', $purchaseOrder) }}">
                                @csrf
                                <input type="hidden" name="store_id" value="{{ $purchaseOrder->store_id }}">
                                <input type="hidden" name="items[0][purchase_order_item_id]" value="{{ $item->id }}">
                                <label>Quantity received<input type="number" name="items[0][quantity_received]" min="0.001" max="{{ $remaining }}" step="0.001" value="{{ $remaining }}" required></label>
                                <label>Unit cost<input type="number" name="items[0][unit_cost]" min="0" step="0.01" value="{{ $item->unit_cost }}"></label>
                                <label>Received at<input type="datetime-local" name="received_at" value="{{ now()->format('Y-m-d\TH:i') }}"></label>
                                <label>Receipt notes<input name="notes" maxlength="5000" placeholder="Batch, delivery, or condition notes"></label>
                                <div class="full"><button class="po-button success" type="submit">Post stock receipt</button></div>
                            </form>
                        </article>
                    @empty
                        <div class="po-empty">Every order item has been fully received.</div>
                    @endforelse
                </section>
            @endif
        </div>

        <aside class="po-stack">
            @if ($purchaseOrder->isDraft() && auth()->user()->hasPermissionTo('inventory.manage'))
                <section class="card po-panel">
                    <h2>Submit purchase order</h2><p>Submission locks the draft contents into an ordered workflow and enables stock receiving.</p>
                    <div class="po-submit-card"><p>Confirm supplier, store, quantities, and costs before submission.</p><form method="POST" action="{{ route('inventory.procurement.submit', $purchaseOrder) }}">@csrf<button class="po-button" type="submit">Submit for receiving</button></form></div>
                </section>
            @endif

            <section class="card po-panel">
                <h2>Receiving history</h2><p>Posted goods receipts are immutable operational evidence.</p>
                @forelse ($purchaseOrder->goodsReceipts as $receipt)
                    <article class="receipt"><div class="receipt-top"><h3>{{ $receipt->receipt_number }}</h3><span class="po-status">{{ ucfirst($receipt->status) }}</span></div><p>{{ $receipt->received_at?->format('d M Y H:i') }} · {{ $receipt->receivedBy?->name ?? 'Unknown user' }}@if ($receipt->notes)<br>{{ $receipt->notes }}@endif</p>@foreach ($receipt->items as $receiptItem)<p>{{ $receiptItem->inventoryItem->name }} · {{ $receiptItem->quantity_received }} received</p>@endforeach</article>
                @empty
                    <div class="po-empty">No goods have been received yet.</div>
                @endforelse
            </section>

            @if (in_array($purchaseOrder->status, [\App\Models\PurchaseOrder::STATUS_DRAFT, \App\Models\PurchaseOrder::STATUS_ORDERED], true) && $purchaseOrder->goodsReceipts->isEmpty() && auth()->user()->hasPermissionTo('inventory.manage'))
                <section class="card po-panel">
                    <h2>Cancel purchase order</h2><p>Cancellation is available only before any stock has been received.</p>
                    <form class="po-form" method="POST" action="{{ route('inventory.procurement.cancel', $purchaseOrder) }}">@csrf<label class="full">Cancellation reason<textarea name="reason" rows="3" maxlength="2000" required>{{ old('reason') }}</textarea></label><div class="full"><button class="po-button danger" type="submit">Cancel purchase order</button></div></form>
                </section>
            @endif
        </aside>
    </div>
</section>
@endsection
