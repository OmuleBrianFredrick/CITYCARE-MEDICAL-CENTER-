<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Facility;
use App\Models\ServicePoint;
use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;

class OrganizationService
{
    public function facility(?int $facilityId = null): ?Facility
    {
        return Facility::query()
            ->when($facilityId !== null, fn ($query) => $query->whereKey($facilityId))
            ->orderBy('id')
            ->first();
    }

    public function activeFacilities(): Collection
    {
        return Facility::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function saveFacility(array $attributes, ?Facility $facility = null): Facility
    {
        return DB::transaction(function () use ($attributes, $facility) {
            $facility ??= $this->facility() ?? new Facility;
            $facility->fill($attributes);
            $facility->save();

            return $facility->fresh();
        });
    }

    public function createDepartment(array $attributes, ?Facility $facility = null): Department
    {
        $facility ??= $this->facility();

        if (! $facility?->is_active) {
            throw ValidationException::withMessages([
                'facility' => 'Select an active medical center facility before creating departments.',
            ]);
        }

        $attributes['code'] = strtoupper(trim((string) $attributes['code']));

        if (Department::query()
            ->where('facility_id', $facility->id)
            ->where('code', $attributes['code'])
            ->exists()) {
            throw ValidationException::withMessages([
                'code' => 'That department code is already in use at this facility.',
            ]);
        }

        $attributes['facility_id'] = $facility->getKey();
        $attributes['sort_order'] = $attributes['sort_order'] ?? 0;
        $attributes['is_active'] = $attributes['is_active'] ?? true;

        return Department::create($attributes);
    }

    public function createServicePoint(array $attributes, ?Facility $facility = null): ServicePoint
    {
        $facility ??= $this->facility();

        if (! $facility?->is_active) {
            throw ValidationException::withMessages([
                'facility' => 'Select an active medical center facility before creating service points.',
            ]);
        }

        if (empty($attributes['department_id'])) {
            throw ValidationException::withMessages([
                'department_id' => 'Every service point must belong to a department.',
            ]);
        }

        $department = Department::query()
            ->where('facility_id', $facility->getKey())
            ->where('is_active', true)
            ->find($attributes['department_id']);

        if (! $department) {
            throw ValidationException::withMessages([
                'department_id' => 'Select a department from the active facility.',
            ]);
        }

        $attributes['code'] = strtoupper(trim((string) $attributes['code']));

        if (ServicePoint::query()->where('code', $attributes['code'])->exists()) {
            throw ValidationException::withMessages([
                'code' => 'That service-point code is already in use.',
            ]);
        }

        $attributes['department_id'] = $department->getKey();
        $attributes['type'] = strtolower(trim((string) ($attributes['type'] ?? 'service')));
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
        $serialized = $this->serializeSettingValue($value, $type);

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

    public function updateSettingValue(SystemSetting $setting, mixed $value): SystemSetting
    {
        $setting->value = $this->serializeSettingValue($value, $setting->type);
        $setting->save();

        return $setting->fresh();
    }

    private function serializeSettingValue(mixed $value, string $type): ?string
    {
        return match ($type) {
            'boolean' => $this->serializeBoolean($value),
            'integer' => $this->serializeInteger($value),
            'float' => is_numeric($value) ? (string) (float) $value : throw ValidationException::withMessages([
                'value' => 'The setting value must be a number.',
            ]),
            'json' => $this->serializeJson($value),
            default => $value === null ? null : (string) $value,
        };
    }

    private function serializeInteger(mixed $value): string
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        if ($integer === null) {
            throw ValidationException::withMessages([
                'value' => 'The setting value must be an integer.',
            ]);
        }

        return (string) $integer;
    }

    private function serializeJson(mixed $value): string
    {
        try {
            $decoded = is_string($value)
                ? json_decode($value, true, flags: JSON_THROW_ON_ERROR)
                : $value;

            return json_encode($decoded, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'value' => 'The setting value must be valid JSON.',
            ]);
        }
    }

    private function serializeBoolean(mixed $value): string
    {
        $boolean = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($boolean === null) {
            throw ValidationException::withMessages([
                'value' => 'The setting value must be true or false.',
            ]);
        }

        return $boolean ? '1' : '0';
    }
}
