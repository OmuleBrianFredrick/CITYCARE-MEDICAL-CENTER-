<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelPurchaseOrderRequest;
use App\Http\Requests\StoreGoodsReceiptRequest;
use App\Http\Requests\StorePurchaseOrderItemRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\InventoryStore;
use App\Models\InventorySupplier;
use App\Models\PurchaseOrder;
use App\Services\InventoryProcurementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InventoryProcurementController extends Controller
{
    public function __construct(private readonly InventoryProcurementService $inventory)
    {
    }

    public function index(): View
    {
        $orders = PurchaseOrder::query()
            ->with(['supplier', 'store', 'items.inventoryItem'])
            ->latest()
            ->paginate(20);

        return view('inventory.procurement.index', compact('orders'));
    }

    public function create(): View
    {
        $suppliers = InventorySupplier::query()->where('is_active', true)->orderBy('name')->get();
        $stores = InventoryStore::query()->where('is_active', true)->orderBy('name')->get();

        return view('inventory.procurement.create', compact('suppliers', 'stores'));
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $supplier = InventorySupplier::query()->findOrFail($request->integer('supplier_id'));
        $store = InventoryStore::query()->findOrFail($request->integer('store_id'));

        $order = $this->inventory->createPurchaseOrder(
            $request->user(),
            $supplier,
            $store,
            $request->validated()
        );

        return redirect()->route('inventory.procurement.show', $order)->with('status', "Purchase order {$order->order_number} created.");
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load([
            'supplier',
            'store',
            'createdBy',
            'items.inventoryItem',
            'goodsReceipts.receivedBy',
            'goodsReceipts.items.inventoryItem',
        ]);

        return view('inventory.procurement.show', compact('purchaseOrder'));
    }

    public function addItem(StorePurchaseOrderItemRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->inventory->addPurchaseOrderItem($request->user(), $purchaseOrder, $request->validated());

        return back()->with('status', "Item added to {$purchaseOrder->order_number}.");
    }

    public function receive(StoreGoodsReceiptRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $store = InventoryStore::query()->findOrFail($request->integer('store_id'));
        $this->inventory->receiveStock($request->user(), $purchaseOrder, $store, $request->validated());

        return back()->with('status', "Stock received against {$purchaseOrder->order_number}.");
    }

    public function cancel(CancelPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->inventory->cancelPurchaseOrder($request->user(), $purchaseOrder, $request->validated('reason'));

        return back()->with('status', "Purchase order {$purchaseOrder->order_number} cancelled.");
    }
}
