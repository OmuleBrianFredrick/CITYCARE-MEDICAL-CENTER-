<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelPurchaseOrderRequest;
use App\Http\Requests\StoreGoodsReceiptRequest;
use App\Http\Requests\StorePurchaseOrderItemRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\InventoryItem;
use App\Models\InventoryStockBalance;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\InventorySupplier;
use App\Models\PurchaseOrder;
use App\Services\FacilityAccessService;
use App\Services\InventoryProcurementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventoryProcurementController extends Controller
{
    public function __construct(
        private readonly InventoryProcurementService $inventory,
        private readonly FacilityAccessService $facilities,
    ) {}

    public function index(Request $request): View
    {
        $facility = $this->facilities->currentFacility($request->user());
        $status = strtolower($request->string('status', 'open')->toString());
        $status = in_array($status, ['open', 'draft', 'ordered', 'partially_received', 'completed', 'cancelled', 'all'], true) ? $status : 'open';
        $stockStatus = strtolower($request->string('stock', 'low')->toString());
        $stockStatus = in_array($stockStatus, ['low', 'healthy', 'all'], true) ? $stockStatus : 'low';
        $search = trim($request->string('search')->toString());

        $orders = PurchaseOrder::query()
            ->where('facility_id', $facility->id)
            ->with(['supplier', 'store', 'items.inventoryItem'])
            ->when($status === 'open', fn ($query) => $query->whereIn('status', [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED]))
            ->when(in_array($status, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED, PurchaseOrder::STATUS_COMPLETED, PurchaseOrder::STATUS_CANCELLED], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('store', fn ($storeQuery) => $storeQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('items.inventoryItem', function ($itemQuery) use ($search) {
                            $itemQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(12, ['*'], 'order_page')
            ->withQueryString();

        $stockBalances = InventoryStockBalance::query()
            ->select('inventory_stock_balances.*')
            ->join('inventory_stores as scoped_stores', 'scoped_stores.id', '=', 'inventory_stock_balances.store_id')
            ->join('inventory_items as scoped_items', 'scoped_items.id', '=', 'inventory_stock_balances.inventory_item_id')
            ->where('scoped_stores.facility_id', $facility->id)
            ->where('scoped_stores.is_active', true)
            ->where('scoped_items.is_active', true)
            ->where('inventory_stock_balances.status', 'active')
            ->when($stockStatus === 'low', fn ($query) => $query->whereColumn('inventory_stock_balances.quantity_available', '<=', 'scoped_items.reorder_level'))
            ->when($stockStatus === 'healthy', fn ($query) => $query->whereColumn('inventory_stock_balances.quantity_available', '>', 'scoped_items.reorder_level'))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('scoped_items.name', 'like', "%{$search}%")
                        ->orWhere('scoped_items.code', 'like', "%{$search}%")
                        ->orWhere('scoped_items.sku', 'like', "%{$search}%")
                        ->orWhere('scoped_stores.name', 'like', "%{$search}%");
                });
            })
            ->with(['store', 'inventoryItem'])
            ->orderBy('scoped_items.name')
            ->paginate(15, ['inventory_stock_balances.*'], 'stock_page')
            ->withQueryString();

        $lowStockCount = DB::table('inventory_stock_balances as balances')
            ->join('inventory_items as items', 'items.id', '=', 'balances.inventory_item_id')
            ->join('inventory_stores as stores', 'stores.id', '=', 'balances.store_id')
            ->where('stores.facility_id', $facility->id)
            ->where('stores.is_active', true)
            ->where('items.is_active', true)
            ->where('balances.status', 'active')
            ->whereColumn('balances.quantity_available', '<=', 'items.reorder_level')
            ->count();

        $latestMovements = InventoryStockMovement::query()
            ->where('facility_id', $facility->id)
            ->with(['store', 'inventoryItem', 'performedBy'])
            ->latest()
            ->limit(10)
            ->get();

        $catalogueCount = InventoryItem::query()->where('facility_id', $facility->id)->where('is_active', true)->count();
        $openOrderCount = PurchaseOrder::query()->where('facility_id', $facility->id)->whereIn('status', [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED])->count();

        return view('inventory.procurement.index', compact(
            'facility',
            'status',
            'stockStatus',
            'orders',
            'stockBalances',
            'latestMovements',
            'lowStockCount',
            'catalogueCount',
            'openOrderCount',
        ));
    }

    public function create(Request $request): View
    {
        $facility = $this->facilities->currentFacility($request->user());
        $suppliers = InventorySupplier::query()->where('facility_id', $facility->id)->where('is_active', true)->orderBy('name')->get();
        $stores = InventoryStore::query()->where('facility_id', $facility->id)->where('is_active', true)->orderBy('name')->get();
        $items = InventoryItem::query()->where('facility_id', $facility->id)->where('is_active', true)->orderBy('name')->get();

        return view('inventory.procurement.create', compact('facility', 'suppliers', 'stores', 'items'));
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $facility = $this->facilities->currentFacility($request->user());
        $supplier = InventorySupplier::query()->where('facility_id', $facility->id)->findOrFail($request->integer('supplier_id'));
        $store = InventoryStore::query()->where('facility_id', $facility->id)->findOrFail($request->integer('store_id'));

        $order = $this->inventory->createPurchaseOrder(
            $request->user(),
            $supplier,
            $store,
            $request->validated()
        );

        return redirect()->route('inventory.procurement.show', $order)->with('status', "Purchase order {$order->order_number} created.");
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): View
    {
        $this->facilities->assertFacilityAccessible($request->user(), $purchaseOrder->facility_id);
        $purchaseOrder->load([
            'facility',
            'supplier',
            'store',
            'createdBy',
            'items.inventoryItem',
            'items.goodsReceiptItems',
            'goodsReceipts.receivedBy',
            'goodsReceipts.items.inventoryItem',
        ]);

        $inventoryItems = InventoryItem::query()
            ->where('facility_id', $purchaseOrder->facility_id)
            ->where('is_active', true)
            ->whereNotIn('id', $purchaseOrder->items->pluck('inventory_item_id'))
            ->orderBy('name')
            ->get();

        return view('inventory.procurement.show', compact('purchaseOrder', 'inventoryItems'));
    }

    public function addItem(StorePurchaseOrderItemRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->facilities->assertFacilityAccessible($request->user(), $purchaseOrder->facility_id);
        $this->inventory->addPurchaseOrderItem($request->user(), $purchaseOrder, $request->validated());

        return back()->with('status', "Item added to {$purchaseOrder->order_number}.");
    }

    public function submit(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->facilities->assertFacilityAccessible($request->user(), $purchaseOrder->facility_id);
        $this->inventory->submitPurchaseOrder($request->user(), $purchaseOrder);

        return back()->with('status', "Purchase order {$purchaseOrder->order_number} submitted for receiving.");
    }

    public function receive(StoreGoodsReceiptRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->facilities->assertFacilityAccessible($request->user(), $purchaseOrder->facility_id);
        $store = InventoryStore::query()->where('facility_id', $purchaseOrder->facility_id)->findOrFail($request->integer('store_id'));
        $this->inventory->receiveStock($request->user(), $purchaseOrder, $store, $request->validated());

        return back()->with('status', "Stock received against {$purchaseOrder->order_number}.");
    }

    public function cancel(CancelPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->facilities->assertFacilityAccessible($request->user(), $purchaseOrder->facility_id);
        $this->inventory->cancelPurchaseOrder($request->user(), $purchaseOrder, $request->validated('reason'));

        return back()->with('status', "Purchase order {$purchaseOrder->order_number} cancelled.");
    }
}
