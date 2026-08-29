<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\Facility;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\PharmacyService;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PharmacyInvalidStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_medication_cannot_be_prescribed(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$doctor, $encounter, $medication] = $this->context();
        $medication->update(['is_active' => false]);

        $this->expectException(ValidationException::class);
        app(PharmacyService::class)->prescribe($encounter, $doctor, [
            'items' => [['medication_id' => $medication->id, 'quantity' => 1]],
        ]);
    }

    public function test_cross_facility_medication_cannot_be_prescribed(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$doctor, $encounter] = $this->context();
        $otherFacility = Facility::factory()->create();
        $medication = Medication::factory()->create(['facility_id' => $otherFacility->id, 'is_active' => true]);

        $this->expectException(ValidationException::class);
        app(PharmacyService::class)->prescribe($encounter, $doctor, [
            'items' => [['medication_id' => $medication->id, 'quantity' => 1]],
        ]);
    }

    public function test_duplicate_dispensing_is_rejected(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$doctor, $encounter, $medication] = $this->context();
        $prescription = app(PharmacyService::class)->prescribe($encounter, $doctor, [
            'items' => [['medication_id' => $medication->id, 'quantity' => 10]],
        ]);
        $pharmacy = $this->userWithRole('pharmacy');
        $itemId = $prescription->items->first()->id;

        $this->expectException(ValidationException::class);
        app(PharmacyService::class)->dispense($prescription, $pharmacy, [
            ['prescription_item_id' => $itemId, 'quantity_dispensed' => 2],
            ['prescription_item_id' => $itemId, 'quantity_dispensed' => 1],
        ]);
    }

    public function test_completed_prescription_cannot_be_cancelled(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$doctor, $encounter, $medication] = $this->context();
        $prescription = app(PharmacyService::class)->prescribe($encounter, $doctor, [
            'items' => [['medication_id' => $medication->id, 'quantity' => 5]],
        ]);
        $pharmacy = $this->userWithRole('pharmacy');
        app(PharmacyService::class)->dispense($prescription, $pharmacy, [
            ['prescription_item_id' => $prescription->items->first()->id, 'quantity_dispensed' => 5],
        ]);

        $this->expectException(ValidationException::class);
        app(PharmacyService::class)->cancelPrescription($prescription->fresh(), $pharmacy);
    }

    private function context(): array
    {
        $doctor = $this->userWithRole('doctor');
        $facility = Facility::factory()->create();
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $encounter = ClinicalEncounter::factory()->create([
            'facility_id' => $facility->id,
            'patient_id' => $patient->id,
            'clinician_id' => $doctor->id,
            'status' => ClinicalEncounter::STATUS_OPEN,
        ]);
        $medication = Medication::factory()->create(['facility_id' => $facility->id, 'is_active' => true]);

        return [$doctor, $encounter, $medication];
    }

    private function userWithRole(string $roleSlug): User
    {
        $user = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', $roleSlug)->valueOrFail('id'));

        return $user;
    }
}
