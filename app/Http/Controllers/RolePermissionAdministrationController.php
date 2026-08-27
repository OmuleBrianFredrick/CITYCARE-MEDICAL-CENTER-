<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Models\Role;
use App\Services\RolePermissionAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RolePermissionAdministrationController extends Controller
{
    public function __construct(private readonly RolePermissionAdministrationService $roleAdministration) {}

    public function index(Request $request): View
    {
        $actor = $request->user();

        return view('administration.roles.index', [
            'roles' => $this->roleAdministration->roles($actor),
            'permissionGroups' => $this->roleAdministration->permissionsByGroup($actor),
        ]);
    }

    public function update(UpdateRolePermissionsRequest $request, Role $role): RedirectResponse
    {
        $this->roleAdministration->sync(
            $request->user(),
            $role,
            $request->validated('permissions'),
        );

        return redirect()
            ->to(route('access.roles.index').'#role-'.$role->id)
            ->with('status', "Permissions for {$role->name} updated successfully.");
    }
}
