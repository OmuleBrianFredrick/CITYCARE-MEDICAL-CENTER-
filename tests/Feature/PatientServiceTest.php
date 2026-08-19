<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Patient;
use App\Services\PatientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PatientServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_be_registered_with_an_automatic_mrn(): void
    {
        $facility = Facility::create(['name' => 'CityCare Medical Center']);

        $patient = app(PatientService::class)->create([
            'facility_id' => $facility->id,
            'first_name' => 'Sarah',
            'middle_name' => 'Nabirye',
            'last_name' => 'Nakato',
            'sex' => 'female',
            'date_of_birth' => '1992-04-12',
            'phone' => '+256700111222',
            'district' => 'Kampala',
            'emergency_contact_name' => 'John Nakato',
            'emergency_contact_relationship' => 'Spouse',
            'emergency_contact_phone' => '+256700333444',
            'next_of_kin_name' => 'John Nakato',
            'next_of_kin_relationship' => 'Spouse',
            'next_of_kin_phone' => '+256700333444',
        ]);

        $this->assertInstanceOf(Patient::class, $patient);
        $this->assertMatchesRegularExpression('/^CCMC-\d{4}-[A-Z0-9]{7}$/', $patient->medical_record_number);
        $this->assertTrue($patient->isActive());
        $this->assertSame('Sarah Nabirye Nakato', $patient->full_name);
        $this->assertNotNull($patient->registered_at);
        $this->assertSame($facility->id, $patient->facility_id);
    }

    public function test_duplicate_national_id_is_rejected(): void
    {
        $facility = Facility::create(['name' => 'CityCare Medical Center']);
        $service = app(PatientService::class);

        $service->create([
            'facility_id' => $facility->id,
            'first_name' => 'John',
            'last_name' => 'Kato',
            'national_id' => 'CM90001122AB',
        ]);

        $this->expectException(ValidationException::class);

        $service->create([
            'facility_id' => $facility->id,
            'first_name' => 'Peter',
            'last_name' => 'Kato',
            'national_id' => 'CM90001122AB',
        ]);
    }

    public function test_duplicate_name_and_phone_combination_is_rejected(): void
    {
        $facility = Facility::create(['name' => 'CityCare Medical Center']);
        $service = app(PatientService::class);

        $service->create([
            'facility_id' => $facility->id,
            'first_name' => 'Mary',
            'last_name' => 'Atim',
            'phone' => '+256701234567',
        ]);

        $this->expectException(ValidationException::class);

        $service->create([
            'facility_id' => $facility->id,
            'first_name' => 'mary',
            'last_name' => 'ATIM',
            'phone' => '+256701234567',
        ]);
    }

    public function test_patient_search_covers_mrn_name_phone_and_national_id(): void
    {
        $facility = Facility::create(['name' => 'CityCare Medical Center']);
        $service = app(PatientService::class);

        $patient = $service->create([
            'facility_id' => $facility->id,
            'first_name' => 'Grace',
            'last_name' => 'Namukasa',
            'phone' => '+256702222333',
            'national_id' => 'CM80002233CD',
        ]);

        $this->assertTrue($service->findForSearch($facility->id, 'Grace')->whereKey($patient->id)->exists());
        $this->assertTrue($service->findForSearch($facility->id, $patient->medical_record_number)->whereKey($patient->id)->exists());
        $this->assertTrue($service->findForSearch($facility->id, '+256702222333')->whereKey($patient->id)->exists());
        $this->assertTrue($service->findForSearch($facility->id, 'CM80002233CD')->whereKey($patient->id)->exists());
    }

    public function test_patient_status_lifecycle_is_explicit(): void
    {
        $facility = Facility::create(['name' => 'CityCare Medical Center']);
        $patient = app(PatientService::class)->create([
            'facility_id' => $facility->id,
            'first_name' => 'David',
            'last_name' => 'Ochieng',
            'status' => Patient::STATUS_INACTIVE,
        ]);

        $this->assertFalse($patient->isActive());
        $this->assertSame(Patient::STATUS_INACTIVE, $patient->status);

        $patient->update(['status' => Patient::STATUS_ACTIVE]);
        $this->assertTrue($patient->fresh()->isActive());
    }
}
