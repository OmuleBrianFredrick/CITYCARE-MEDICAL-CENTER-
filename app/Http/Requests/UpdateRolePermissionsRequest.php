<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->isStaff() === true
            && $user->isActive()
            && $user->hasRole('super-admin')
            && $user->hasPermissionTo('access.manage');
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('permissions')) {
            $this->merge(['permissions' => []]);
        }
    }

    public function rules(): array
    {
        $role = $this->route('role');
        $roleId = $role instanceof Role ? $role->id : (int) $role;

        return [
            'role_id' => ['required', 'integer', Rule::in([$roleId])],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['required', 'integer', 'distinct', Rule::exists('permissions', 'id')],
        ];
    }
}
