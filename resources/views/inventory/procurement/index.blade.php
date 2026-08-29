@extends('layouts.app')

@section('title', 'Inventory Workspace · CityCare Medical Center')

@push('styles')
<style>
    .inventory-page{max-width:1320px;padding:clamp(24px,4vw,42px)}.inventory-heading{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px}.inventory-eyebrow{margin:0 0 7px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.14em}.inventory-heading h1{margin:0;font-size:clamp(1.85rem,4vw,2.65rem);letter-spacing:-.045em}.inventory-heading p{max-width:760px;margin:9px 0 0;color:var(--muted);line-height:1.55}.inventory-create{display:inline-flex;padding:10px 14px;border-radius:10px;background:var(--blue);color:#fff;font-size:.8rem;font-weight:850;text-decoration:none;white-space:nowrap}.inventory-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:18px}.inventory-stat{padding:20px}.inventory-stat span{display:block;color:var(--muted);font-size:.7rem;font-weight:850;text-transform:uppercase;letter-spacing:.08em}.inventory-stat strong{display:block;margin-top:7px;font-size:1.35rem}.inventory-filter{display:grid;grid-template-columns:minmax(0,1fr) 170px 160px auto;gap:10px;margin-bottom:18px;padding:18px}.inventory-filter input,.inventory-filter select{min-width:0;padding:11px 12px;border:1px solid var(--line);border-radius:10px;background:#fff}.inventory-filter button{border:0;border-radius:10px;background:var(--blue);color:#fff;padding:11px 16px;font-weight:850;cursor:pointer}.inventory-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(360px,.9fr);gap:18px;align-items:start}.inventory-stack{display:grid;gap:18px}.inventory-panel{padding:22px}.inventory-panel-heading{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;margin-bottom:14px}.inventory-panel-heading h2{margin:0;font-size:1.08rem}.inventory-panel-heading p{margin:5px 0 0;color:var(--muted);font-size:.82rem;line-height:1.5}.inventory-count{padding:6px 9px;border-radius:999px;background:#f1f5f9;color:#475569;font-size:.7rem;font-weight:850;white-space:nowrap}.order-card{padding:15px 0;border-bottom:1px solid var(--line)}.order-top{display:flex;justify-content:space-between;gap:14px;align-items:flex-start}.order-card h3{margin:0;font-size:.95rem}.order-card h3 a{color:var(--blue);text-decoration:none}.order-card p{margin:5px 0 0;color:var(--muted);font-size:.79rem;line-height:1.48}.inventory-status{display:inline-block;padding:6px 9px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:.68rem;font-weight:850;white-space:nowrap}.order-total{margin-top:8px;font-size:.81rem;font-weight:850}.stock-table{width:100%;border-collapse:collapse;font-size:.79rem}.stock-table th,.stock-table td{padding:10px 8px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}.stock-table th{color:var(--muted);font-size:.68rem;text-transform:uppercase;letter-spacing:.06em}.stock-low{color:#b91c1c;font-weight:850}.stock-good{color:#15803d;font-weight:850}.movement{padding:12px 0;border-bottom:1px solid var(--line)}.movement strong{font-size:.84rem}.movement p{margin:4px 0 0;color:var(--muted);font-size:.76rem;line-height:1.45}.inventory-empty{padding:28px 8px;color:var(--muted);text-align:center}.inventory-pagination{margin-top:17px}@media(max-width:980px){.inventory-grid{grid-template-columns:1fr}.inventory-summary{grid-template-columns:1fr}}@media(max-width:760px){.inventory-page{padding:24px 18px}.inventory-heading{flex-direction:column}.inventory-filter{grid-template-columns:1fr}.inventory-filter button{width:100%}.order-top{flex-direction:column;gap:8px}.stock-table{display:block;overflow-x:auto;white-space:nowrap}}
</style>
@endpush

@section('content')
<section class="inventory-page">
    <div class="inventory-heading">
        <div>
            <p class="inventory-eyebrow">INVENTORY & PROCUREMENT</p>
            <h1>Stock and procurement workspace</h1>
            <p>Monitor reorder thresholds, review traceable stock movement, and move purchase orders from draft through receiving and completion.</p>
        </div>
        <div style="display:flex;gap:9px;flex-wrap:wrap;justify-content:flex-end">
            <a class="inventory-create" style="background:#fff;color:var(--blue);border:1px solid #bfdbfe" href="{{ route('inventory.catalogue.index') }}">Catalogue & adjustments</a>
            @if (auth()->user()->hasPermissionTo('inventory.manage'))<a class="inventory-create" href="{{ route('inventory.procurement.create') }}">Create purchase order</a>@endif
        </div>
    </div>

    @if (session('status'))<div class="status" style="margin-bottom:18px">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="error" style="margin-bottom:18px">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <div class="inventory-summary">
        <article class="card inventory-stat"><span>Active catalogue items</span><strong>{{ $catalogueCount }}</strong></article>
        <article class="card inventory-stat"><span>Low-stock lines</span><strong>{{ $lowStockCount }}</strong></article>
        <article class="card inventory-stat"><span>Open purchase orders</span><strong>{{ $openOrderCount }}</strong></article>
    </div>

    <form class="card inventory-filter" method="GET" action="{{ route('inventory.procurement.index') }}">
        <input name="search" value="{{ request('search') }}" placeholder="Search order, item, supplier, or store" aria-label="Search inventory">
        <select name="status" aria-label="Purchase order status">
            <option value="open" @selected($status === 'open')>Open orders</option>
            <option value="draft" @selected($status === 'draft')>Draft</option>
            <option value="ordered" @selected($status === 'ordered')>Ordered</option>
            <option value="partially_received" @selected($status === 'partially_received')>Partially received</option>
            <option value="completed" @selected($status === 'completed')>Completed</option>
            <option value="cancelled" @selected($status === 'cancelled')>Cancelled</option>
            <option value="all" @selected($status === 'all')>All orders</option>
        </select>
        <select name="stock" aria-label="Stock status">
            <option value="low" @selected($stockStatus === 'low')>Low stock</option>
            <option value="healthy" @selected($stockStatus === 'healthy')>Healthy stock</option>
            <option value="all" @selected($stockStatus === 'all')>All stock</option>
        </select>
        <button type="submit">Filter workspace</button>
    </form>

    <div class="inventory-grid">
        <div class="inventory-stack">
            <section class="card inventory-panel">
                <div class="inventory-panel-heading">
                    <div><h2>Purchase orders</h2><p>Open an order to add draft items, submit it, receive stock, or review goods receipts.</p></div>
                    <span class="inventory-count">{{ $orders->total() }} {{ Str::plural('order', $orders->total()) }}</span>
                </div>
                @forelse ($orders as $order)
                    <article class="order-card">
                        <div class="order-top">
                            <div><h3><a href="{{ route('inventory.procurement.show', $order) }}">{{ $order->order_number }}</a></h3><p>{{ $order->supplier->name }} · {{ $order->store->name }} · {{ $order->items->count() }} {{ Str::plural('item', $order->items->count()) }}@if ($order->ordered_at)<br>Submitted {{ $order->ordered_at->format('d M Y') }}@endif</p></div>
                            <span class="inventory-status">{{ str_replace('_', ' ', ucfirst($order->status)) }}</span>
                        </div>
                        <div class="order-total">Total {{ $facility->currency }} {{ number_format((float) $order->total, 2) }}</div>
                    </article>
                @empty
                    <div class="inventory-empty">No purchase orders match this filter.</div>
                @endforelse
                <div class="inventory-pagination">{{ $orders->links() }}</div>
            </section>

            <section class="card inventory-panel">
                <div class="inventory-panel-heading"><div><h2>Stock balances</h2><p>Available quantity is compared with each item’s configured reorder level.</p></div><span class="inventory-count">{{ $stockBalances->total() }} {{ Str::plural('line', $stockBalances->total()) }}</span></div>
                <table class="stock-table">
                    <thead><tr><th>Item</th><th>Store</th><th>On hand</th><th>Available</th><th>Reorder</th></tr></thead>
                    <tbody>
                    @forelse ($stockBalances as $balance)
                        @php($isLow = (float) $balance->quantity_available <= (float) $balance->inventoryItem->reorder_level)
                        <tr><td><strong>{{ $balance->inventoryItem->name }}</strong><br><span style="color:var(--muted)">{{ $balance->inventoryItem->code ?: $balance->inventoryItem->sku }}</span></td><td>{{ $balance->store->name }}</td><td>{{ $balance->quantity_on_hand }} {{ $balance->inventoryItem->unit }}</td><td class="{{ $isLow ? 'stock-low' : 'stock-good' }}">{{ $balance->quantity_available }}</td><td>{{ $balance->inventoryItem->reorder_level }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="inventory-empty">No stock balances match this filter.</td></tr>
                    @endforelse
                    </tbody>
                </table>
                <div class="inventory-pagination">{{ $stockBalances->links() }}</div>
            </section>
        </div>

        <aside class="card inventory-panel">
            <div class="inventory-panel-heading"><div><h2>Recent stock movement</h2><p>Receipts and issues remain traceable to their store, item, and staff member.</p></div></div>
            @forelse ($latestMovements as $movement)
                <article class="movement"><strong>{{ $movement->inventoryItem->name }} · {{ ucfirst($movement->movement_type) }}</strong><p>{{ $movement->store->name }} · {{ (float) $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }} · Balance {{ $movement->balance_after }}<br>{{ $movement->created_at->format('d M Y H:i') }} · {{ $movement->performedBy?->name ?? 'Unknown user' }}</p></article>
            @empty
                <div class="inventory-empty">No stock movements have been posted.</div>
            @endforelse
        </aside>
    </div>
</section>
@endsection
