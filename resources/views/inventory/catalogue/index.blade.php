@extends('layouts.app')

@section('title', 'Inventory Catalogue · CityCare Medical Center')

@push('styles')
<style>
    .catalogue-page{max-width:1320px;padding:clamp(24px,4vw,42px)}.catalogue-heading{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px}.catalogue-eyebrow{margin:0 0 7px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.14em}.catalogue-heading h1{margin:0;font-size:clamp(1.8rem,4vw,2.55rem);letter-spacing:-.045em}.catalogue-heading p{max-width:760px;margin:8px 0 0;color:var(--muted);line-height:1.5}.catalogue-back{display:inline-flex;padding:9px 12px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--blue);font-size:.79rem;font-weight:850;text-decoration:none}.catalogue-search{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;margin-bottom:18px;padding:17px}.catalogue-search input,.catalogue-form input,.catalogue-form select,.catalogue-form textarea{width:100%;min-width:0;padding:9px 10px;border:1px solid var(--line);border-radius:8px;background:#fff}.catalogue-search button,.catalogue-button{border:0;border-radius:9px;background:var(--blue);color:#fff;padding:10px 13px;font-size:.78rem;font-weight:850;cursor:pointer}.catalogue-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;align-items:start}.catalogue-panel{padding:21px}.catalogue-panel h2{margin:0;font-size:1.06rem}.catalogue-panel>p{margin:6px 0 0;color:var(--muted);font-size:.8rem;line-height:1.45}.catalogue-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:15px}.catalogue-form label{display:grid;gap:5px;color:#334155;font-size:.73rem;font-weight:850}.catalogue-form .full{grid-column:1/-1}.catalogue-check{display:flex!important;grid-column:1/-1;flex-direction:row!important;align-items:center;gap:7px!important}.catalogue-check input{width:auto}.catalogue-record{padding:13px 0;border-bottom:1px solid var(--line)}.catalogue-record h3{margin:0;font-size:.89rem}.catalogue-record p{margin:4px 0 0;color:var(--muted);font-size:.75rem;line-height:1.45}.catalogue-badge{display:inline-block;margin-left:6px;padding:4px 7px;border-radius:999px;background:#f1f5f9;color:#475569;font-size:.63rem;font-weight:850}.catalogue-record details{margin-top:9px}.catalogue-record summary{color:var(--blue);font-size:.73rem;font-weight:850;cursor:pointer}.adjustment-panel{margin-bottom:18px;padding:21px}.adjustment-panel .catalogue-form{grid-template-columns:1.1fr 1.3fr .7fr .7fr 1.5fr auto;align-items:end}.catalogue-pagination{margin-top:14px}.catalogue-empty{padding:22px 4px;color:var(--muted);text-align:center;font-size:.8rem}@media(max-width:1050px){.catalogue-grid{grid-template-columns:1fr}.adjustment-panel .catalogue-form{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:700px){.catalogue-page{padding:24px 18px}.catalogue-heading{flex-direction:column}.catalogue-search,.catalogue-form,.adjustment-panel .catalogue-form{grid-template-columns:1fr}.catalogue-form .full,.catalogue-check{grid-column:auto}.catalogue-button{width:100%}}
</style>
@endpush

@section('content')
<section class="catalogue-page">
    <div class="catalogue-heading">
        <div><p class="catalogue-eyebrow">INVENTORY MASTER DATA</p><h1>Catalogue, stores, and suppliers</h1><p>Maintain the facility inventory foundation and post controlled stock corrections with a mandatory audit reason.</p></div>
        <a class="catalogue-back" href="{{ route('inventory.procurement.index') }}">← Stock workspace</a>
    </div>

    @if (session('status'))<div class="status" style="margin-bottom:18px">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="error" style="margin-bottom:18px">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <form class="card catalogue-search" method="GET" action="{{ route('inventory.catalogue.index') }}"><input name="search" value="{{ request('search') }}" placeholder="Search item, SKU, store, or supplier"><button type="submit">Search catalogue</button></form>

    @if (auth()->user()->hasPermissionTo('inventory.manage'))
        <section class="card adjustment-panel">
            <h2 style="margin:0;font-size:1.06rem">Controlled stock adjustment</h2><p style="margin:6px 0 0;color:var(--muted);font-size:.8rem">Use this only for verified counts, damage, write-offs, or corrections. The signed movement and resulting balance are retained.</p>
            <form class="catalogue-form" method="POST" action="{{ route('inventory.adjustments.store') }}">
                @csrf
                <label>Store<select name="store_id" required><option value="">Select store</option>@foreach ($adjustmentStores as $store)<option value="{{ $store->id }}" @selected((string) old('store_id') === (string) $store->id)>{{ $store->name }}</option>@endforeach</select></label>
                <label>Inventory item<select name="inventory_item_id" required><option value="">Select item</option>@foreach ($adjustmentItems as $item)<option value="{{ $item->id }}" @selected((string) old('inventory_item_id') === (string) $item->id)>{{ $item->name }} · {{ $item->code ?: $item->sku }}</option>@endforeach</select></label>
                <label>Direction<select name="direction" required><option value="increase" @selected(old('direction') === 'increase')>Increase</option><option value="decrease" @selected(old('direction') === 'decrease')>Decrease</option></select></label>
                <label>Quantity<input type="number" name="quantity" min="0.001" step="0.001" value="{{ old('quantity') }}" required></label>
                <label>Audit reason<input name="reason" maxlength="2000" value="{{ old('reason') }}" required placeholder="Count correction, damage, write-off…"></label>
                <button class="catalogue-button" type="submit">Post adjustment</button>
            </form>
        </section>
    @endif

    <div class="catalogue-grid">
        <section class="card catalogue-panel">
            <h2>Inventory items</h2><p>Codes, units, categories, and reorder thresholds.</p>
            @if (auth()->user()->hasPermissionTo('inventory.manage'))
                <details style="margin-top:14px"><summary style="color:var(--blue);font-weight:850;cursor:pointer">Create inventory item</summary>
                    <form class="catalogue-form" method="POST" action="{{ route('inventory.catalogue.items.store') }}">@csrf
                        <label class="full">Name<input name="name" maxlength="255" required></label><label>Code<input name="code" maxlength="100"></label><label>SKU<input name="sku" maxlength="100"></label><label>Category<input name="category" maxlength="100"></label><label>Unit<input name="unit" maxlength="50" value="unit" required></label><label>Reorder level<input type="number" name="reorder_level" min="0" step="0.001" value="0" required></label><input type="hidden" name="is_active" value="1"><div class="full"><button class="catalogue-button" type="submit">Create item</button></div>
                    </form>
                </details>
            @endif
            @forelse ($items as $item)
                <article class="catalogue-record"><h3>{{ $item->name }} <span class="catalogue-badge">{{ $item->is_active ? 'Active' : 'Inactive' }}</span></h3><p>{{ $item->code ?: 'No code' }}@if ($item->sku) · SKU {{ $item->sku }}@endif · {{ $item->unit }}<br>Reorder {{ $item->reorder_level }} · Available {{ number_format((float) $item->stockBalances->sum('quantity_available'), 3) }}</p>
                    @if (auth()->user()->hasPermissionTo('inventory.manage'))<details><summary>Edit item</summary><form class="catalogue-form" method="POST" action="{{ route('inventory.catalogue.items.update', $item) }}">@csrf @method('PUT')<label class="full">Name<input name="name" maxlength="255" value="{{ $item->name }}" required></label><label>Code<input name="code" maxlength="100" value="{{ $item->code }}"></label><label>SKU<input name="sku" maxlength="100" value="{{ $item->sku }}"></label><label>Category<input name="category" maxlength="100" value="{{ $item->category }}"></label><label>Unit<input name="unit" maxlength="50" value="{{ $item->unit }}" required></label><label>Reorder level<input type="number" name="reorder_level" min="0" step="0.001" value="{{ $item->reorder_level }}" required></label><input type="hidden" name="is_active" value="0"><label class="catalogue-check"><input type="checkbox" name="is_active" value="1" @checked($item->is_active)> Active</label><div class="full"><button class="catalogue-button" type="submit">Save item</button></div></form></details>@endif
                </article>
            @empty<div class="catalogue-empty">No inventory items match this search.</div>@endforelse
            <div class="catalogue-pagination">{{ $items->links() }}</div>
        </section>

        <section class="card catalogue-panel">
            <h2>Stores</h2><p>Physical stock locations and service-point links.</p>
            @if (auth()->user()->hasPermissionTo('inventory.manage'))
                <details style="margin-top:14px"><summary style="color:var(--blue);font-weight:850;cursor:pointer">Create inventory store</summary><form class="catalogue-form" method="POST" action="{{ route('inventory.catalogue.stores.store') }}">@csrf<label class="full">Name<input name="name" maxlength="255" required></label><label>Code<input name="code" maxlength="100"></label><label>Type<input name="type" maxlength="100" value="store" required></label><label class="full">Service point<select name="service_point_id"><option value="">No service-point link</option>@foreach ($servicePoints as $point)<option value="{{ $point->id }}">{{ $point->department->name }} · {{ $point->name }}</option>@endforeach</select></label><input type="hidden" name="is_active" value="1"><div class="full"><button class="catalogue-button" type="submit">Create store</button></div></form></details>
            @endif
            @forelse ($stores as $store)
                <article class="catalogue-record"><h3>{{ $store->name }} <span class="catalogue-badge">{{ $store->is_active ? 'Active' : 'Inactive' }}</span></h3><p>{{ $store->code ?: 'No code' }} · {{ ucfirst($store->type) }} · {{ $store->stock_balances_count }} stock {{ Str::plural('line', $store->stock_balances_count) }}@if ($store->servicePoint)<br>{{ $store->servicePoint->department->name }} · {{ $store->servicePoint->name }}@endif</p>
                    @if (auth()->user()->hasPermissionTo('inventory.manage'))<details><summary>Edit store</summary><form class="catalogue-form" method="POST" action="{{ route('inventory.catalogue.stores.update', $store) }}">@csrf @method('PUT')<label class="full">Name<input name="name" maxlength="255" value="{{ $store->name }}" required></label><label>Code<input name="code" maxlength="100" value="{{ $store->code }}"></label><label>Type<input name="type" maxlength="100" value="{{ $store->type }}" required></label><label class="full">Service point<select name="service_point_id"><option value="">No service-point link</option>@foreach ($servicePoints as $point)<option value="{{ $point->id }}" @selected($store->service_point_id === $point->id)>{{ $point->department->name }} · {{ $point->name }}</option>@endforeach</select></label><input type="hidden" name="is_active" value="0"><label class="catalogue-check"><input type="checkbox" name="is_active" value="1" @checked($store->is_active)> Active</label><div class="full"><button class="catalogue-button" type="submit">Save store</button></div></form></details>@endif
                </article>
            @empty<div class="catalogue-empty">No stores match this search.</div>@endforelse
            <div class="catalogue-pagination">{{ $stores->links() }}</div>
        </section>

        <section class="card catalogue-panel">
            <h2>Suppliers</h2><p>Approved procurement contacts and availability.</p>
            @if (auth()->user()->hasPermissionTo('inventory.manage'))
                <details style="margin-top:14px"><summary style="color:var(--blue);font-weight:850;cursor:pointer">Create supplier</summary><form class="catalogue-form" method="POST" action="{{ route('inventory.catalogue.suppliers.store') }}">@csrf<label class="full">Name<input name="name" maxlength="255" required></label><label>Code<input name="code" maxlength="100"></label><label>Phone<input name="phone" maxlength="50"></label><label class="full">Email<input type="email" name="email" maxlength="255"></label><label class="full">Address<textarea name="address" rows="2" maxlength="1000"></textarea></label><input type="hidden" name="is_active" value="1"><div class="full"><button class="catalogue-button" type="submit">Create supplier</button></div></form></details>
            @endif
            @forelse ($suppliers as $supplier)
                <article class="catalogue-record"><h3>{{ $supplier->name }} <span class="catalogue-badge">{{ $supplier->is_active ? 'Active' : 'Inactive' }}</span></h3><p>{{ $supplier->code ?: 'No code' }}@if ($supplier->phone) · {{ $supplier->phone }}@endif @if ($supplier->email)<br>{{ $supplier->email }}@endif</p>
                    @if (auth()->user()->hasPermissionTo('inventory.manage'))<details><summary>Edit supplier</summary><form class="catalogue-form" method="POST" action="{{ route('inventory.catalogue.suppliers.update', $supplier) }}">@csrf @method('PUT')<label class="full">Name<input name="name" maxlength="255" value="{{ $supplier->name }}" required></label><label>Code<input name="code" maxlength="100" value="{{ $supplier->code }}"></label><label>Phone<input name="phone" maxlength="50" value="{{ $supplier->phone }}"></label><label class="full">Email<input type="email" name="email" maxlength="255" value="{{ $supplier->email }}"></label><label class="full">Address<textarea name="address" rows="2" maxlength="1000">{{ $supplier->address }}</textarea></label><input type="hidden" name="is_active" value="0"><label class="catalogue-check"><input type="checkbox" name="is_active" value="1" @checked($supplier->is_active)> Active</label><div class="full"><button class="catalogue-button" type="submit">Save supplier</button></div></form></details>@endif
                </article>
            @empty<div class="catalogue-empty">No suppliers match this search.</div>@endforelse
            <div class="catalogue-pagination">{{ $suppliers->links() }}</div>
        </section>
    </div>
</section>
@endsection
