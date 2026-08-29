@extends('layouts.app')

@section('title', 'Billing Catalogue · CityCare Medical Center')

@push('styles')
<style>
    .price-page{max-width:1220px;padding:clamp(24px,4vw,42px)}.price-heading{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px}.price-eyebrow{margin:0 0 7px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.14em}.price-heading h1{margin:0;font-size:clamp(1.8rem,4vw,2.55rem);letter-spacing:-.045em}.price-heading p{max-width:740px;margin:8px 0 0;color:var(--muted);line-height:1.5}.price-back{display:inline-flex;padding:9px 12px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--blue);font-size:.78rem;font-weight:850;text-decoration:none}.price-search{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;padding:17px;margin-bottom:18px}.price-search input,.price-form input,.price-form select,.price-form textarea{width:100%;min-width:0;padding:9px 10px;border:1px solid var(--line);border-radius:8px;background:#fff}.price-button{border:0;border-radius:9px;background:var(--blue);color:#fff;padding:10px 13px;font-size:.78rem;font-weight:850;cursor:pointer}.price-layout{display:grid;grid-template-columns:minmax(300px,.72fr) minmax(0,1.35fr);gap:18px;align-items:start}.price-panel{padding:22px}.price-panel h2{margin:0;font-size:1.08rem}.price-panel>p{margin:6px 0 0;color:var(--muted);font-size:.8rem;line-height:1.5}.price-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:15px}.price-form label{display:grid;gap:5px;color:#334155;font-size:.73rem;font-weight:850}.price-form .full{grid-column:1/-1}.price-check{display:flex!important;grid-column:1/-1;flex-direction:row!important;align-items:center;gap:7px!important}.price-check input{width:auto}.service-card{margin-bottom:14px;padding:19px}.service-top{display:flex;justify-content:space-between;gap:14px;align-items:flex-start}.service-card h2{margin:0;font-size:1rem}.service-card p{margin:5px 0 0;color:var(--muted);font-size:.78rem;line-height:1.45}.price-badge{padding:5px 8px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:.66rem;font-weight:850;white-space:nowrap}.price-list{margin-top:13px;padding-top:11px;border-top:1px solid var(--line)}.price-row{padding:10px 0;border-bottom:1px solid var(--line)}.price-row strong{font-size:.84rem}.price-row p{margin:4px 0 0}.service-card details{margin-top:11px}.service-card summary{color:var(--blue);font-size:.74rem;font-weight:850;cursor:pointer}.price-empty{padding:28px;text-align:center;color:var(--muted)}.price-pagination{margin-top:17px}@media(max-width:900px){.price-layout{grid-template-columns:1fr}}@media(max-width:700px){.price-page{padding:24px 18px}.price-heading{flex-direction:column}.price-search,.price-form{grid-template-columns:1fr}.price-form .full,.price-check{grid-column:auto}.service-top{flex-direction:column}.price-button{width:100%}}
</style>
@endpush

@section('content')
<section class="price-page">
    <div class="price-heading">
        <div><p class="price-eyebrow">BILLING MASTER DATA</p><h1>Service and price catalogue</h1><p>Maintain billable services and time-bounded facility prices used by charge and invoice workflows.</p></div>
        <a class="price-back" href="{{ route('billing.index') }}">← Billing workspace</a>
    </div>

    @if (session('status'))<div class="status" style="margin-bottom:18px">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="error" style="margin-bottom:18px">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <form class="card price-search" method="GET" action="{{ route('billing.catalogue.index') }}"><input name="search" value="{{ request('search') }}" placeholder="Search service code, name, or category"><button class="price-button" type="submit">Search catalogue</button></form>

    <div class="price-layout">
        <aside class="card price-panel">
            <h2>Create billable service</h2><p>Add the service first, then attach one or more effective prices.</p>
            <form class="price-form" method="POST" action="{{ route('billing.catalogue.services.store') }}">@csrf
                <label>Code<input name="code" maxlength="50" value="{{ old('code') }}" required></label><label>Unit<input name="unit" maxlength="40" value="{{ old('unit', 'item') }}" required></label><label class="full">Name<input name="name" maxlength="150" value="{{ old('name') }}" required></label><label class="full">Category<input name="category" maxlength="80" value="{{ old('category') }}"></label><label class="full">Description<textarea name="description" rows="3" maxlength="5000">{{ old('description') }}</textarea></label><input type="hidden" name="is_active" value="1"><div class="full"><button class="price-button" type="submit">Create service</button></div>
            </form>
        </aside>

        <div>
            @forelse ($services as $service)
                <article class="card service-card">
                    <div class="service-top"><div><h2>{{ $service->name }} · {{ $service->code }}</h2><p>{{ $service->category ?: 'Uncategorized' }} · Charged per {{ $service->unit }}@if ($service->description)<br>{{ $service->description }}@endif</p></div><span class="price-badge">{{ $service->is_active ? 'Active' : 'Inactive' }}</span></div>
                    <details><summary>Edit service</summary><form class="price-form" method="POST" action="{{ route('billing.catalogue.services.update', $service) }}">@csrf @method('PUT')<label>Code<input name="code" maxlength="50" value="{{ $service->code }}" required></label><label>Unit<input name="unit" maxlength="40" value="{{ $service->unit }}" required></label><label class="full">Name<input name="name" maxlength="150" value="{{ $service->name }}" required></label><label class="full">Category<input name="category" maxlength="80" value="{{ $service->category }}"></label><label class="full">Description<textarea name="description" rows="2" maxlength="5000">{{ $service->description }}</textarea></label><input type="hidden" name="is_active" value="0"><label class="price-check"><input type="checkbox" name="is_active" value="1" @checked($service->is_active)> Active</label><div class="full"><button class="price-button" type="submit">Save service</button></div></form></details>
                    <details><summary>Add effective price</summary><form class="price-form" method="POST" action="{{ route('billing.catalogue.prices.store', $service) }}">@csrf<label>Amount<input type="number" name="amount" min="0.01" step="0.01" required></label><label>Currency<input name="currency" minlength="3" maxlength="3" value="{{ $facility->currency }}" required></label><label>Effective from<input type="date" name="effective_from" value="{{ today()->toDateString() }}" required></label><label>Effective to<input type="date" name="effective_to"></label><label class="full">Notes<input name="notes" maxlength="5000"></label><input type="hidden" name="is_active" value="1"><div class="full"><button class="price-button" type="submit">Add price</button></div></form></details>
                    <div class="price-list">
                        @forelse ($service->prices as $price)
                            <div class="price-row"><strong>{{ $price->currency }} {{ number_format((float) $price->amount, 2) }}</strong><span class="price-badge" style="margin-left:7px">{{ $price->is_active ? 'Active' : 'Inactive' }}</span><p>Effective {{ $price->effective_from->format('d M Y') }} to {{ $price->effective_to?->format('d M Y') ?? 'open-ended' }}</p><details><summary>Edit price</summary><form class="price-form" method="POST" action="{{ route('billing.catalogue.prices.update', $price) }}">@csrf @method('PUT')<label>Amount<input type="number" name="amount" min="0.01" step="0.01" value="{{ $price->amount }}" required></label><label>Currency<input name="currency" minlength="3" maxlength="3" value="{{ $price->currency }}" required></label><label>Effective from<input type="date" name="effective_from" value="{{ $price->effective_from->toDateString() }}" required></label><label>Effective to<input type="date" name="effective_to" value="{{ $price->effective_to?->toDateString() }}"></label><label class="full">Notes<input name="notes" maxlength="5000" value="{{ $price->notes }}"></label><input type="hidden" name="is_active" value="0"><label class="price-check"><input type="checkbox" name="is_active" value="1" @checked($price->is_active)> Active</label><div class="full"><button class="price-button" type="submit">Save price</button></div></form></details></div>
                        @empty
                            <div class="price-empty">No prices have been configured for this service.</div>
                        @endforelse
                    </div>
                </article>
            @empty
                <section class="card price-empty">No billable services match this search.</section>
            @endforelse
            <div class="price-pagination">{{ $services->links() }}</div>
        </div>
    </div>
</section>
@endsection
