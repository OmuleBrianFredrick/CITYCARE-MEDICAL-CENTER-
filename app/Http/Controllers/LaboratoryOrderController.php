<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLaboratoryOrderRequest;
use App\Http\Requests\StoreLaboratoryResultRequest;
use App\Models\ClinicalEncounter;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryOrderItem;
use App\Services\LaboratoryOrderService;
use Illuminate\Http\RedirectResponse;

class LaboratoryOrderController extends Controller
{
    public function __construct(private readonly LaboratoryOrderService $laboratory)
    {
    }

    public function store(StoreLaboratoryOrderRequest $request, ClinicalEncounter $encounter): RedirectResponse
    {
        $order = $this->laboratory->create($encounter, $request->user(), $request->validated());

        return back()->with('status', "Laboratory order {$order->order_number} created successfully.");
    }

    public function recordResult(StoreLaboratoryResultRequest $request, LaboratoryOrderItem $item): RedirectResponse
    {
        $result = $this->laboratory->recordResult($item, $request->user(), $request->validated());

        return back()->with('status', "Laboratory result #{$result->id} recorded successfully.");
    }

    public function cancel(LaboratoryOrder $order, StoreLaboratoryOrderRequest $request): RedirectResponse
    {
        $order = $this->laboratory->cancelOrder($order, $request->user());

        return back()->with('status', "Laboratory order {$order->order_number} cancelled successfully.");
    }
}
