<?php

namespace App\Http\Requests;

use App\Models\SystemSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->isStaff() === true
            && $user->isActive()
            && $user->hasRole('super-admin')
            && $user->hasPermissionTo('organization.manage');
    }

    public function rules(): array
    {
        return [
            'value' => match ($this->setting()?->type) {
                'boolean' => ['required', Rule::in(['0', '1', 'true', 'false'])],
                'integer' => ['required', 'integer'],
                'float' => ['required', 'numeric'],
                'json' => ['required', 'string', 'json', 'max:5000'],
                default => ['nullable', 'string', 'max:5000'],
            },
        ];
    }

    public function setting(): ?SystemSetting
    {
        return SystemSetting::query()->where('key', $this->route('key'))->first();
    }
}
