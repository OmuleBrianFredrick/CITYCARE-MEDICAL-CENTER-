@extends('layouts.app')

@section('title', 'Create Purchase Order · CityCare Medical Center')

@push('styles')
<style>
    .po-create-page{max-width:1120px;padding:clamp(24px,4vw,42px)}.po-create-heading{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:24px}.po-create-eyebrow{margin:0 0 7px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.14em}.po-create-heading h1{margin:0;font-size:clamp(1.8rem,4vw,2.5rem);letter-spacing:-.045em}.po-create-heading p{margin:8px 0 0;color:var(--muted)}.po-create-back{display:inline-flex;padding:9px 12px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--blue);font-size:.79rem;font-weight:850;text-decoration:none}.po-create-card{padding:24px}.po-form{display:grid;gap:18px}.po-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.po-form label{display:grid;gap:6px;color:#334155;font-size:.78rem;font-weight:850}.po-form input,.po-form select,.po-form textarea{width:100%;min-width:0;padding:10px 11px;border:1px solid var(--line);border-radius:9px;background:#fff}.po-items-heading{display:flex;justify-content:space-between;gap:12px;align-items:center}.po-items-heading h2{margin:0;font-size:1.05rem}.po-add-row{border:1px solid #bfdbfe;border-radius:9px;background:#eff6ff;color:#1d4ed8;padding:8px 11px;font-size:.77rem;font-weight:850;cursor:pointer}.po-item-row{display:grid;grid-template-columns:minmax(220px,1.4fr) minmax(120px,.55fr) minmax(130px,.65fr) auto;gap:11px;align-items:end;margin-top:11px;padding:14px;border:1px solid var(--line);border-radius:11px}.po-remove-row{border:1px solid #fecaca;border-radius:9px;background:#fff;color:#b91c1c;padding:10px;font-weight:850;cursor:pointer}.po-submit{justify-self:start;border:0;border-radius:10px;background:var(--blue);color:#fff;padding:11px 16px;font-weight:850;cursor:pointer}.po-missing{padding:18px;border-radius:10px;background:#fffbeb;color:#92400e;line-height:1.5}@media(max-width:760px){.po-create-page{padding:24px 18px}.po-create-heading{flex-direction:column}.po-fields,.po-item-row{grid-template-columns:1fr}.po-submit{width:100%}}
</style>
@endpush

@section('content')
@php($orderItems = old('items', [['inventory_item_id' => '', 'quantity_ordered' => 1, 'unit_cost' => 0]]))
<section class="po-create-page">
    <div class="po-create-heading">
        <div><p class="po-create-eyebrow">INVENTORY & PROCUREMENT</p><h1>Create purchase order</h1><p>{{ $facility->name }} · Build the draft, then submit it from the order page.</p></div>
        <a class="po-create-back" href="{{ route('inventory.procurement.index') }}">← Inventory workspace</a>
    </div>

    @if ($errors->any())<div class="error" style="margin-bottom:18px">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <section class="card po-create-card">
        @if ($suppliers->isEmpty() || $stores->isEmpty() || $items->isEmpty())
            <div class="po-missing">A purchase order needs at least one active supplier, store, and catalogue item in this facility. Ask an administrator to complete the inventory master data before creating an order.</div>
        @else
            <form class="po-form" method="POST" action="{{ route('inventory.procurement.store') }}">
                @csrf
                <div class="po-fields">
                    <label>Supplier<select name="supplier_id" required><option value="">Select supplier</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></label>
                    <label>Receiving store<select name="store_id" required><option value="">Select store</option>@foreach ($stores as $store)<option value="{{ $store->id }}" @selected((string) old('store_id') === (string) $store->id)>{{ $store->name }} · {{ ucfirst($store->type) }}</option>@endforeach</select></label>
                </div>

                <div>
                    <div class="po-items-heading"><div><h2>Order items</h2><p style="margin:5px 0 0;color:var(--muted);font-size:.8rem">Choose catalogue items; duplicate items are rejected.</p></div><button class="po-add-row" type="button" data-add-item>Add another item</button></div>
                    <div data-item-list>
                        @foreach ($orderItems as $index => $oldItem)
                            <div class="po-item-row" data-item-row>
                                <label>Inventory item<select name="items[{{ $index }}][inventory_item_id]" required><option value="">Select item</option>@foreach ($items as $item)<option value="{{ $item->id }}" @selected((string) ($oldItem['inventory_item_id'] ?? '') === (string) $item->id)>{{ $item->name }} · {{ $item->code ?: ($item->sku ?: $item->unit) }}</option>@endforeach</select></label>
                                <label>Quantity<input type="number" min="0.001" step="0.001" name="items[{{ $index }}][quantity_ordered]" value="{{ $oldItem['quantity_ordered'] ?? 1 }}" required></label>
                                <label>Unit cost<input type="number" min="0" step="0.01" name="items[{{ $index }}][unit_cost]" value="{{ $oldItem['unit_cost'] ?? 0 }}" required></label>
                                <button class="po-remove-row" type="button" data-remove-item aria-label="Remove item">Remove</button>
                            </div>
                        @endforeach
                    </div>
                </div>

                <label>Procurement notes<textarea name="notes" rows="4" maxlength="5000" placeholder="Supplier terms, delivery instructions, or internal context">{{ old('notes') }}</textarea></label>
                <button class="po-submit" type="submit">Create draft purchase order</button>
            </form>
        @endif
    </section>
</section>

@if ($suppliers->isNotEmpty() && $stores->isNotEmpty() && $items->isNotEmpty())
<template data-item-template>
    <div class="po-item-row" data-item-row>
        <label>Inventory item<select name="items[__INDEX__][inventory_item_id]" required><option value="">Select item</option>@foreach ($items as $item)<option value="{{ $item->id }}">{{ $item->name }} · {{ $item->code ?: ($item->sku ?: $item->unit) }}</option>@endforeach</select></label>
        <label>Quantity<input type="number" min="0.001" step="0.001" name="items[__INDEX__][quantity_ordered]" value="1" required></label>
        <label>Unit cost<input type="number" min="0" step="0.01" name="items[__INDEX__][unit_cost]" value="0" required></label>
        <button class="po-remove-row" type="button" data-remove-item aria-label="Remove item">Remove</button>
    </div>
</template>
@push('scripts')
<script>
    (() => {
        const list = document.querySelector('[data-item-list]');
        const template = document.querySelector('[data-item-template]');
        const add = document.querySelector('[data-add-item]');
        if (!list || !template || !add) return;
        let nextIndex = Array.from(list.querySelectorAll('[name^="items["]')).reduce((max, input) => {
            const match = input.name.match(/^items\[(\d+)]/);
            return match ? Math.max(max, Number(match[1]) + 1) : max;
        }, 0);
        const refreshRemoveButtons = () => list.querySelectorAll('[data-remove-item]').forEach((button) => { button.disabled = list.children.length === 1; button.style.opacity = button.disabled ? '.45' : '1'; });
        add.addEventListener('click', () => {
            const index = nextIndex++;
            list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', index));
            refreshRemoveButtons();
        });
        list.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-item]');
            if (button && list.children.length > 1) { button.closest('[data-item-row]').remove(); refreshRemoveButtons(); }
        });
        refreshRemoveButtons();
    })();
</script>
@endpush
@endif
@endsection
