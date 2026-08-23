@extends('layouts.app')
@section('title', 'Create Purchase Order · CityCare Medical Center')
@section('content')
<div style="padding:34px 24px;max-width:1100px">
    <div style="margin-bottom:24px"><div style="color:#2563eb;font-size:.74rem;font-weight:900;letter-spacing:.14em">INVENTORY & PROCUREMENT</div><h1 style="margin:6px 0">Create purchase order</h1></div>
    <div class="card" style="padding:24px">
        <form method="POST" action="{{ route('inventory.procurement.store') }}">
            @csrf
            @if($errors->any())<div class="status" style="margin-bottom:18px;background:#fef2f2;color:#991b1b">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px">
                <label><strong>Supplier</strong><select name="supplier_id" required style="width:100%;margin-top:6px"><option value="">Select supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id')==$supplier->id)>{{ $supplier->name }}</option>@endforeach</select></label>
                <label><strong>Store</strong><select name="store_id" required style="width:100%;margin-top:6px"><option value="">Select store</option>@foreach($stores as $store)<option value="{{ $store->id }}" @selected(old('store_id')==$store->id)>{{ $store->name }}</option>@endforeach</select></label>
                <label><strong>Ordered at</strong><input type="date" name="ordered_at" value="{{ old('ordered_at') }}" style="width:100%;margin-top:6px"></label>
            </div>
            <div style="margin-top:18px;padding:16px;border:1px solid #e5e7eb;border-radius:10px">
                <h3 style="margin-top:0">First procurement item</h3>
                <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px">
                    <label><strong>Inventory item ID</strong><input type="number" name="items[0][inventory_item_id]" value="{{ old('items.0.inventory_item_id') }}" required style="width:100%;margin-top:6px"></label>
                    <label><strong>Quantity</strong><input type="number" min="0.001" step="0.001" name="items[0][quantity_ordered]" value="{{ old('items.0.quantity_ordered', 1) }}" required style="width:100%;margin-top:6px"></label>
                    <label><strong>Unit cost</strong><input type="number" min="0" step="0.01" name="items[0][unit_cost]" value="{{ old('items.0.unit_cost', 0) }}" required style="width:100%;margin-top:6px"></label>
                </div>
            </div>
            <label style="display:block;margin-top:16px"><strong>Notes</strong><textarea name="notes" rows="4" style="width:100%;margin-top:6px">{{ old('notes') }}</textarea></label>
            <button style="margin-top:14px;background:#2563eb;color:#fff;border:0;border-radius:10px;padding:11px 16px;font-weight:800">Create purchase order</button>
        </form>
    </div>
</div>
@endsection
