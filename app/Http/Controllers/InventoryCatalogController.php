<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\InventorySupplier;
use App\Models\ServicePoint;
use App\Services\FacilityAccessService;
use App\Services\InventoryStockAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryCatalogController extends Controller
{
    public function __construct(
        private readonly FacilityAccessService $facilities,
        private readonly InventoryStockAdjustmentService $stockAdjustments,
    ) {}

    public function index(Request $request): View
    {
        $facility = $this->facilities->currentFacility($request->user());
        $search = trim($request->string('search')->toString());

        $items = InventoryItem::query()
            ->where('facility_id', $facility->id)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")))
            ->with(['stockBalances' => fn ($query) => $query->whereHas('store', fn ($storeQuery) => $storeQuery->where('facility_id', $facility->id))->with('store')])
            ->orderBy('name')
            ->paginate(20, ['*'], 'item_page')
            ->withQueryString();

        $suppliers = InventorySupplier::query()
            ->where('facility_id', $facility->id)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(15, ['*'], 'supplier_page')
            ->withQueryString();

        $stores = InventoryStore::query()
            ->where('facility_id', $facility->id)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->with('servicePoint.department')
            ->withCount('stockBalances')
            ->orderBy('name')
            ->paginate(15, ['*'], 'store_page')
            ->withQueryString();

        $servicePoints = ServicePoint::query()
            ->where('is_active', true)
            ->whereHas('department', fn ($query) => $query->where('facility_id', $facility->id))
            ->with('department')
            ->orderBy('name')
            ->get();

        $adjustmentStores = InventoryStore::query()->where('facility_id', $facility->id)->where('is_active', true)->orderBy('name')->get();
        $adjustmentItems = InventoryItem::query()->where('facility_id', $facility->id)->where('is_active', true)->orderBy('name')->get();

        return view('inventory.catalogue.index', compact(
            'facility',
            'items',
            'suppliers',
            'stores',
            'servicePoints',
            'adjustmentStores',
            'adjustmentItems',
        ));
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $facility = $this->facilities->currentFacility($request->user());
        InventoryItem::create($this->itemData($request, $facility) + ['facility_id' => $facility->id]);

        return back()->with('status', 'Inventory item created successfully.');
    }

    public function updateItem(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $this->facilities->assertFacilityAccessible($request->user(), $inventoryItem->facility_id);
        $inventoryItem->update($this->itemData($request, $inventoryItem->facility, $inventoryItem));

        return back()->with('status', 'Inventory item updated successfully.');
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        $facility = $this->facilities->currentFacility($request->user());
        InventorySupplier::create($this->supplierData($request, $facility) + ['facility_id' => $facility->id]);

        return back()->with('status', 'Inventory supplier created successfully.');
    }

    public function updateSupplier(Request $request, InventorySupplier $inventorySupplier): RedirectResponse
    {
        $this->facilities->assertFacilityAccessible($request->user(), $inventorySupplier->facility_id);
        $inventorySupplier->update($this->supplierData($request, $inventorySupplier->facility, $inventorySupplier));

        return back()->with('status', 'Inventory supplier updated successfully.');
    }

    public function storeStore(Request $request): RedirectResponse
    {
        $facility = $this->facilities->currentFacility($request->user());
        InventoryStore::create($this->storeData($request, $facility) + ['facility_id' => $facility->id]);

        return back()->with('status', 'Inventory store created successfully.');
    }

    public function updateStore(Request $request, InventoryStore $inventoryStore): RedirectResponse
    {
        $this->facilities->assertFacilityAccessible($request->user(), $inventoryStore->facility_id);
        $inventoryStore->update($this->storeData($request, $inventoryStore->facility, $inventoryStore));

        return back()->with('status', 'Inventory store updated successfully.');
    }

    public function adjust(Request $request): RedirectResponse
    {
        $facility = $this->facilities->currentFacility($request->user());
        $validated = $request->validate([
            'store_id' => ['required', 'integer', 'exists:inventory_stores,id'],
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'direction' => ['required', Rule::in(['increase', 'decrease'])],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $store = InventoryStore::query()->where('facility_id', $facility->id)->findOrFail($validated['store_id']);
        $item = InventoryItem::query()->where('facility_id', $facility->id)->findOrFail($validated['inventory_item_id']);

        $this->stockAdjustments->adjust(
            $request->user(),
            $store,
            $item,
            $validated['direction'],
            (float) $validated['quantity'],
            $validated['reason'],
        );

        return back()->with('status', 'Stock adjustment posted successfully.');
    }

    private function itemData(Request $request, Facility $facility, ?InventoryItem $item = null): array
    {
        $codeRule = Rule::unique('inventory_items', 'code')->where(fn ($query) => $query->where('facility_id', $facility->id));
        $skuRule = Rule::unique('inventory_items', 'sku')->where(fn ($query) => $query->where('facility_id', $facility->id));
        if ($item) {
            $codeRule->ignore($item->id);
            $skuRule->ignore($item->id);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', $codeRule],
            'sku' => ['nullable', 'string', 'max:100', $skuRule],
            'category' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:50'],
            'reorder_level' => ['required', 'numeric', 'gte:0'],
            'is_active' => ['required', 'boolean'],
        ]);

        return array_merge($validated, [
            'code' => $validated['code'] ?: null,
            'sku' => $validated['sku'] ?: null,
        ]);
    }

    private function supplierData(Request $request, Facility $facility, ?InventorySupplier $supplier = null): array
    {
        $codeRule = Rule::unique('inventory_suppliers', 'code')->where(fn ($query) => $query->where('facility_id', $facility->id));
        if ($supplier) {
            $codeRule->ignore($supplier->id);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', $codeRule],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ]);

        return array_merge($validated, ['code' => $validated['code'] ?: null]);
    }

    private function storeData(Request $request, Facility $facility, ?InventoryStore $store = null): array
    {
        $codeRule = Rule::unique('inventory_stores', 'code')->where(fn ($query) => $query->where('facility_id', $facility->id));
        if ($store) {
            $codeRule->ignore($store->id);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', $codeRule],
            'type' => ['required', 'string', 'max:100'],
            'service_point_id' => ['nullable', 'integer', 'exists:service_points,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        if (! empty($validated['service_point_id'])) {
            ServicePoint::query()
                ->whereKey($validated['service_point_id'])
                ->whereHas('department', fn ($query) => $query->where('facility_id', $facility->id))
                ->firstOrFail();
        }

        return array_merge($validated, ['code' => $validated['code'] ?: null]);
    }
}
