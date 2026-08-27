<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffAccountRequest;
use App\Http\Requests\SyncStaffRolesRequest;
use App\Http\Requests\UpdateStaffAccountRequest;
use App\Models\EmployeeInvitation;
use App\Models\Facility;
use App\Models\User;
use App\Services\StaffAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffAdministrationController extends Controller
{
    public function __construct(private readonly StaffAdministrationService $staffAdministration) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'facility_id' => ['nullable', 'integer', Rule::exists('facilities', 'id')],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
        $actor = $request->user();
        $facility = $this->staffAdministration->facilityFor(
            $actor,
            isset($filters['facility_id']) ? (int) $filters['facility_id'] : null,
        );
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;

        $staffMembers = $this->staffAdministration
            ->staffQuery($actor, $facility)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('staffProfile', fn ($profile) => $profile
                            ->where('employee_number', 'like', "%{$search}%")
                            ->orWhere('job_title', 'like', "%{$search}%"));
                });
            })
            ->when($status !== null, fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('administration.staff.index', [
            'facility' => $facility,
            'facilities' => $this->staffAdministration->availableFacilities($actor),
            'staffMembers' => $staffMembers,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        $validated = $request->validate([
            'facility_id' => ['nullable', 'integer', Rule::exists('facilities', 'id')],
        ]);
        $actor = $request->user();
        $facility = $this->staffAdministration->facilityFor(
            $actor,
            isset($validated['facility_id']) ? (int) $validated['facility_id'] : null,
        );

        return view('administration.staff.create', $this->formContext($actor, $facility));
    }

    public function store(StoreStaffAccountRequest $request): RedirectResponse
    {
        [$staff, $invitation, $plainToken] = $this->staffAdministration->invite(
            $request->user(),
            $request->validated(),
        );
        $facilityId = $staff->staffProfile?->department?->facility_id;
        $invitationUrl = route('staff-invitations.accept.create', [
            'token' => $plainToken,
            'email' => $staff->email,
        ]);

        return redirect()
            ->route('staff.edit', ['staff' => $staff, 'facility_id' => $facilityId])
            ->with('status', "Staff invitation #{$invitation->id} created successfully.")
            ->with('invitation_url', $invitationUrl);
    }

    public function edit(Request $request, User $staff): View
    {
        $actor = $request->user();
        $facility = $this->staffAdministration->facilityForTarget($actor, $staff);
        $staff->load('roles', 'staffProfile.department', 'staffProfile.servicePoint');

        return view('administration.staff.edit', array_merge(
            $this->formContext($actor, $facility),
            [
                'staff' => $staff,
                'invitation' => $this->staffAdministration->latestInvitation($actor, $staff),
            ],
        ));
    }

    public function update(UpdateStaffAccountRequest $request, User $staff): RedirectResponse
    {
        $staff = $this->staffAdministration->update($request->user(), $staff, $request->validated());

        return redirect()
            ->route('staff.edit', $staff)
            ->with('status', 'Staff account details updated successfully.');
    }

    public function syncRoles(SyncStaffRolesRequest $request, User $staff): RedirectResponse
    {
        $staff = $this->staffAdministration->syncRoles(
            $request->user(),
            $staff,
            $request->validated('roles'),
        );

        return redirect()
            ->route('staff.edit', $staff)
            ->with('status', 'Staff role assignments updated successfully.');
    }

    public function deactivate(Request $request, User $staff): RedirectResponse
    {
        $staff = $this->staffAdministration->deactivate($request->user(), $staff);

        return redirect()
            ->route('staff.edit', $staff)
            ->with('status', 'Staff account deactivated successfully.');
    }

    public function reactivate(Request $request, User $staff): RedirectResponse
    {
        $staff = $this->staffAdministration->reactivate($request->user(), $staff);

        return redirect()
            ->route('staff.edit', $staff)
            ->with('status', 'Staff account reactivated successfully.');
    }

    public function reissueInvitation(Request $request, User $staff): RedirectResponse
    {
        [$invitation, $plainToken] = $this->staffAdministration->reissueInvitation($request->user(), $staff);
        $invitationUrl = route('staff-invitations.accept.create', [
            'token' => $plainToken,
            'email' => $staff->email,
        ]);

        return redirect()
            ->route('staff.edit', $staff)
            ->with('status', "Staff invitation #{$invitation->id} issued successfully.")
            ->with('invitation_url', $invitationUrl);
    }

    public function revokeInvitation(Request $request, User $staff, EmployeeInvitation $invitation): RedirectResponse
    {
        $this->staffAdministration->revokeInvitation($request->user(), $staff, $invitation);

        return redirect()
            ->route('staff.edit', $staff)
            ->with('status', 'The pending staff invitation was revoked.');
    }

    private function formContext(User $actor, Facility $facility): array
    {
        return [
            'facility' => $facility,
            'departments' => $this->staffAdministration->departments($facility),
            'servicePoints' => $this->staffAdministration->servicePoints($facility),
            'roles' => $this->staffAdministration->assignableRoles($actor),
        ];
    }
}
