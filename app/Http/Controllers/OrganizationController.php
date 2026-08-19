<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\ServicePoint;
use App\Services\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function __construct(private readonly OrganizationService $organization)
    {
    }

    public function index(): View
    {
        return view('organization.index', [
            'facility' => $this->organization->facility(),
            'departments' => Department::with('servicePoints')->orderBy('sort_order')->orderBy('name')->get(),
            'settings' => \App\Models\SystemSetting::query()->orderBy('group')->orderBy('key')->get(),
        ]);
    }

    public function updateFacility(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'string', 'size:3'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $this->organization->saveFacility($data);

        return back()->with('status', 'Facility configuration updated successfully.');
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->organization->createDepartment($data);

        return back()->with('status', 'Department created successfully.');
    }

    public function storeServicePoint(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'type' => ['required', 'string', 'max:60'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $this->organization->createServicePoint($data);

        return back()->with('status', 'Service point created successfully.');
    }

    public function updateSetting(Request $request, string $key): RedirectResponse
    {
        $data = $request->validate([
            'value' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', 'in:string,boolean,integer,float,json'],
            'group' => ['required', 'string', 'max:60'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        $this->organization->setSetting(
            $key,
            $data['value'] ?? null,
            $data['group'],
            $data['type'],
            null,
            (bool) ($data['is_public'] ?? false),
        );

        return back()->with('status', 'System setting updated successfully.');
    }
}
