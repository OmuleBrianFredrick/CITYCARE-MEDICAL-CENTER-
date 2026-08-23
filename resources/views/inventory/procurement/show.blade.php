@extends('layouts.app')
@section('title', 'Purchase Order · CityCare Medical Center')
@section('content')
<div style="padding:34px 24px;max-width:1100px">
    @if(session('status'))<div class="status" style="margin-bottom:18px">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="status" style="margin-bottom:18px;background:#fef2f2;color:#991b1b">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <div style="display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px">
        <div><div style="color:#2563eb;font-size:.74rem;font-weight:900;letter-spacing:.14em">INVENTORY & PROCUREMENT</div><h1 style="margin:6px 0">{{ $purchaseOrder->order_number }}</h1><p style="color:#627d98">{{ $purchaseOrder->supplier->name }} · {{ $purchaseOrder->store->name }}</p></div>
        <div style="padding:8px 12px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-weight:800">{{ str_replace('_', ' ', ucfirst($purchaseOrder->status)) }}</div>
    </div>
    <div class="card" style="padding:24px">
        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px"><div><strong>Supplier</strong><div style="color:#627d98;margin-top:5px">{{ $purchaseOrder->supplier->name }}</div></div><div><strong>Store</strong><div style="color:#627d98;margin-top:5px">{{ $purchaseOrder->store->name }}</div></div><div><strong>Total</strong><div style="color:#627d98;margin-top:5px">{{ number_format((float) $purchaseOrder->total, 2) }}</div></div></div>
        <div style="margin-top:20px">
            @foreach($purchaseOrder->items as $item)
                <div style="padding:12px 0;border-bottom:1px solid #e5e7eb"><strong>{{ $item->inventoryItem->name }}</strong><div style="color:#627d98;margin-top:4px">Ordered: {{ $item->quantity_ordered }} · Unit cost: {{ number_format((float) $item->unit_cost, 2) }} · Line total: {{ number_format((float) $item->line_total, 2) }}</div></div>
            @endforeach
        </div>
    </div>
    <div class="card" style="margin-top:18px;padding:24px">
        <h2 style="margin-top:0">Receiving history</h2>
        @forelse($purchaseOrder->goodsReceipts as $receipt)
            <div style="padding:14px 0;border-bottom:1px solid #e5e7eb"><div style="display:flex;justify-content:space-between"><strong>{{ $receipt->receipt_number }}</strong><span style="font-weight:800;color:#2563eb">{{ ucfirst($receipt->status) }}</span></div><div style="color:#627d98;margin-top:4px">{{ $receipt->received_at?->format('d M Y H:i') }} · {{ $receipt->receivedBy->name }}</div>@foreach($receipt->items as $item)<div style="color:#627d98;margin-top:4px">{{ $item->inventoryItem->name }} · {{ $item->quantity_received }} received</div>@endforeach</div>
        @empty
            <p style="color:#627d98">No goods have been received yet.</p>
        @endforelse
    </div>
    @if(in_array($purchaseOrder->status, ['ordered', 'partially_received'], true))
        <div class="card" style="margin-top:18px;padding:24px">
            <h2 style="margin-top:0">Receive stock</h2>
            <form method="POST" action="{{ route('inventory.procurement.receive', $purchaseOrder) }}">
                @csrf
                <input type="hidden" name="store_id" value="{{ $purchaseOrder->store_id }}">
                <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px">
                    <label><strong>Purchase order item</strong><select name="items[0][purchase_order_item_id]" required style="width:100%;margin-top:6px">@foreach($purchaseOrder->items as $item)<option value="{{ $item->id }}">{{ $item->inventoryItem->name }} · ordered {{ $item->quantity_ordered }}</option>@endforeach</select></label>
                    <label><strong>Quantity received</strong><input type="number" name="items[0][quantity_received]" min="0.001" step="0.001" required style="width:100%;margin-top:6px"></label>
                </div>
                <button style="margin-top:14px;background:#15803d;color:#fff;border:0;border-radius:10px;padding:11px 16px;font-weight:800">Post receipt</button>
            </form>
        </div>
    @endif
    @if(!in_array($purchaseOrder->status, ['completed', 'cancelled'], true))
        <div class="card" style="margin-top:18px;padding:24px">
            <h2 style="margin-top:0">Cancel purchase order</h2>
            <form method="POST" action="{{ route('inventory.procurement.cancel', $purchaseOrder) }}"><div style="display:flex;gap:10px"><textarea name="reason" rows="2" required placeholder="Cancellation reason…" style="flex:1"></textarea><button style="background:#b91c1c;color:#fff;border:0;border-radius:10px;padding:11px 16px;font-weight:800">Cancel</button></div></form>
        </div>
    @endif
</div>
@endsection
