<?php

namespace App\Http\Controllers;

use App\Models\BillableService;
use App\Models\Facility;
use App\Models\ServicePrice;
use App\Services\FacilityAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BillingCatalogController extends Controller
{
    public function __construct(private readonly FacilityAccessService $facilities) {}

    public function index(Request $request): View
    {
        $facility = $this->facilities->currentFacility($request->user());
        $search = trim($request->string('search')->toString());

        $services = BillableService::query()
            ->where('facility_id', $facility->id)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")->orWhere('category', 'like', "%{$search}%")))
            ->with(['prices' => fn ($query) => $query->latest('effective_from')])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('billing.catalogue.index', compact('facility', 'services'));
    }

    public function storeService(Request $request): RedirectResponse
    {
        $facility = $this->facilities->currentFacility($request->user());
        BillableService::create($this->serviceData($request, $facility) + ['facility_id' => $facility->id]);

        return back()->with('status', 'Billable service created successfully.');
    }

    public function updateService(Request $request, BillableService $billableService): RedirectResponse
    {
        $this->facilities->assertFacilityAccessible($request->user(), $billableService->facility_id);
        $billableService->update($this->serviceData($request, $billableService->facility, $billableService));

        return back()->with('status', 'Billable service updated successfully.');
    }

    public function storePrice(Request $request, BillableService $billableService): RedirectResponse
    {
        $this->facilities->assertFacilityAccessible($request->user(), $billableService->facility_id);
        ServicePrice::create($this->priceData($request, $billableService) + [
            'facility_id' => $billableService->facility_id,
            'billable_service_id' => $billableService->id,
        ]);

        return back()->with('status', 'Service price created successfully.');
    }

    public function updatePrice(Request $request, ServicePrice $servicePrice): RedirectResponse
    {
        $this->facilities->assertFacilityAccessible($request->user(), $servicePrice->facility_id);
        $servicePrice->loadMissing('billableService');
        $servicePrice->update($this->priceData($request, $servicePrice->billableService, $servicePrice));

        return back()->with('status', 'Service price updated successfully.');
    }

    private function serviceData(Request $request, Facility $facility, ?BillableService $service = null): array
    {
        $codeRule = Rule::unique('billable_services', 'code')->where(fn ($query) => $query->where('facility_id', $facility->id));
        if ($service) {
            $codeRule->ignore($service->id);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', $codeRule],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:5000'],
            'unit' => ['required', 'string', 'max:40'],
            'is_active' => ['required', 'boolean'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));

        return $validated;
    }

    private function priceData(Request $request, BillableService $service, ?ServicePrice $price = null): array
    {
        $effectiveRule = Rule::unique('service_prices', 'effective_from')->where(fn ($query) => $query->where('billable_service_id', $service->id));
        if ($price) {
            $effectiveRule->ignore($price->id);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'size:3'],
            'effective_from' => ['required', 'date', $effectiveRule],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $validated['currency'] = strtoupper($validated['currency']);

        return $validated;
    }
}
