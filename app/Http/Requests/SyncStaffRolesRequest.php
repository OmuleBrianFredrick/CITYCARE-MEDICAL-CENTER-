<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncStaffRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->isStaff() === true
            && $user->isActive()
            && $user->hasPermissionTo('staff.manage');
    }

    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'integer', 'distinct', Rule::exists('roles', 'id')],
        ];
    }
}
