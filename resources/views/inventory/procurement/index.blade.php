@extends('layouts.app')
@section('title', 'Inventory Procurement · CityCare Medical Center')
@section('content')
<div style="padding:34px 24px;max-width:1100px">
    @if(session('status'))<div class="status" style="margin-bottom:18px">{{ session('status') }}</div>@endif
    <div style="display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px">
        <div><div style="color:#2563eb;font-size:.74rem;font-weight:900;letter-spacing:.14em">INVENTORY & PROCUREMENT</div><h1 style="margin:6px 0">Purchase orders</h1><p style="color:#627d98">Manage procurement and receiving workflows.</p></div>
        <a href="{{ route('inventory.procurement.create') }}" style="background:#2563eb;color:#fff;border-radius:10px;padding:11px 16px;font-weight:800;text-decoration:none">Create purchase order</a>
    </div>
    <div class="card" style="padding:24px">
        @forelse($orders as $order)
            <div style="padding:16px 0;border-bottom:1px solid #e5e7eb">
                <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start">
                    <div><a href="{{ route('inventory.procurement.show', $order) }}" style="font-weight:900;color:#1d4ed8;text-decoration:none">{{ $order->order_number }}</a><div style="color:#627d98;margin-top:4px">{{ $order->supplier->name }} · {{ $order->store->name }} · {{ $order->items->count() }} {{ Str::plural('item', $order->items->count()) }}</div></div>
                    <span style="padding:6px 10px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-weight:800">{{ str_replace('_', ' ', ucfirst($order->status)) }}</span>
                </div>
                <div style="color:#627d98;margin-top:6px">Total: {{ number_format((float) $order->total, 2) }}</div>
            </div>
        @empty
            <p style="color:#627d98">No purchase orders have been created.</p>
        @endforelse
        <div style="margin-top:18px">{{ $orders->links() }}</div>
    </div>
</div>
@endsection
