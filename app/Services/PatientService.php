<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PatientService
{
    public function create(array $data): Patient
    {
        return DB::transaction(function () use ($data): Patient {
            $this->guardAgainstDuplicates($data);

            $data['medical_record_number'] ??= $this->generateMedicalRecordNumber();
            $data['status'] ??= Patient::STATUS_ACTIVE;
            $data['registered_at'] ??= now();

            return Patient::create($data);
        });
    }

    public function findForSearch(int $facilityId, ?string $search = null): Builder
    {
        $query = Patient::query()->where('facility_id', $facilityId);

        if ($search !== null && trim($search) !== '') {
            $term = trim($search);

            $query->where(function (Builder $query) use ($term): void {
                $query->where('medical_record_number', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('middle_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('national_id', 'like', "%{$term}%");
            });
        }

        return $query->orderBy('last_name')->orderBy('first_name');
    }

    private function guardAgainstDuplicates(array $data): void
    {
        if (! empty($data['national_id']) && Patient::where('national_id', $data['national_id'])->exists()) {
            throw ValidationException::withMessages([
                'national_id' => 'A patient with this national ID already exists.',
            ]);
        }

        if (! empty($data['phone']) && ! empty($data['first_name']) && ! empty($data['last_name'])) {
            $exists = Patient::query()
                ->where('phone', $data['phone'])
                ->whereRaw('LOWER(first_name) = ?', [Str::lower($data['first_name'])])
                ->whereRaw('LOWER(last_name) = ?', [Str::lower($data['last_name'])])
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'phone' => 'A patient with this name and phone number already exists.',
                ]);
            }
        }
    }

    private function generateMedicalRecordNumber(): string
    {
        do {
            $number = 'CCMC-'.now()->format('Y').'-'.strtoupper(Str::random(7));
        } while (Patient::where('medical_record_number', $number)->exists());

        return $number;
    }
}
