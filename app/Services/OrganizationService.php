<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Facility;
use App\Models\ServicePoint;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationService
{
    public function facility(): ?Facility
    {
        return Facility::query()->orderBy('id')->first();
    }

    public function saveFacility(array $attributes): Facility
    {
        return DB::transaction(function () use ($attributes) {
            $facility = $this->facility() ?? new Facility();
            $facility->fill($attributes);
            $facility->save();

            return $facility->fresh();
        });
    }

    public function createDepartment(array $attributes): Department
    {
        $facility = $this->facility();

        if (! $facility) {
            throw ValidationException::withMessages([
                'facility' => 'Configure the medical center facility before creating departments.',
            ]);
        }

        $attributes['facility_id'] = $facility->id;
        $attributes['sort_order'] = $attributes['sort_order'] ?? 0;
        $attributes['is_active'] = $attributes['is_active'] ?? true;

        return Department::create($attributes);
    }

    public function createServicePoint(array $attributes): ServicePoint
    {
        if (! empty($attributes['department_id'])) {
            Department::query()->findOrFail($attributes['department_id']);
        }

        $attributes['type'] = $attributes['type'] ?? 'service';
        $attributes['is_active'] = $attributes['is_active'] ?? true;
        $attributes['sort_order'] = $attributes['sort_order'] ?? 0;

        return ServicePoint::create($attributes);
    }

    public function setSetting(
        string $key,
        mixed $value,
        string $group = 'general',
        string $type = 'string',
        ?string $description = null,
        bool $isPublic = false,
    ): SystemSetting {
        $serialized = match ($type) {
            'json' => json_encode($value, JSON_THROW_ON_ERROR),
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };

        return SystemSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $serialized,
                'type' => $type,
                'group' => $group,
                'description' => $description,
                'is_public' => $isPublic,
            ]
        );
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        $setting = SystemSetting::query()->where('key', $key)->first();

        return $setting?->typedValue() ?? $default;
    }
}
