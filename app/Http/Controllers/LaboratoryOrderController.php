<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelLaboratoryOrderRequest;
use App\Http\Requests\StoreLaboratoryOrderRequest;
use App\Http\Requests\StoreLaboratoryResultRequest;
use App\Models\ClinicalEncounter;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryOrderItem;
use App\Services\FacilityAccessService;
use App\Services\LaboratoryOrderService;
use Illuminate\Http\RedirectResponse;

class LaboratoryOrderController extends Controller
{
    public function __construct(
        private readonly LaboratoryOrderService $laboratory,
        private readonly FacilityAccessService $facilityAccess,
    ) {}

    public function store(StoreLaboratoryOrderRequest $request, ClinicalEncounter $encounter): RedirectResponse
    {
        $this->facilityAccess->assertEncounterAccessible($request->user(), $encounter);
        $order = $this->laboratory->create($encounter, $request->user(), $request->validated());

        return back()->with('status', "Laboratory order {$order->order_number} created successfully.");
    }

    public function recordResult(StoreLaboratoryResultRequest $request, LaboratoryOrderItem $item): RedirectResponse
    {
        $item->loadMissing('order.encounter');
        $this->facilityAccess->assertEncounterAccessible($request->user(), $item->order->encounter);
        $result = $this->laboratory->recordResult($item, $request->user(), $request->validated());

        return back()->with('status', "Laboratory result #{$result->id} recorded successfully.");
    }

    public function cancel(CancelLaboratoryOrderRequest $request, LaboratoryOrder $order): RedirectResponse
    {
        $order->loadMissing('encounter');
        $this->facilityAccess->assertEncounterAccessible($request->user(), $order->encounter);
        $order = $this->laboratory->cancelOrder($order, $request->user());

        return back()->with('status', "Laboratory order {$order->order_number} cancelled successfully.");
    }
}
