<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\PharmacyService;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyClinicalWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_doctor_can_view_pharmacy_workspace_data_on_open_encounter(): void
    {
        $this->seed(CityCareAccessSeeder::class);

        [$doctor, $encounter, $medication] = $this->workspaceContext('doctor');

        $prescription = app(PharmacyService::class)->prescribe($encounter, $doctor, [
            'items' => [[
                'medication_id' => $medication->id,
                'quantity' => 10,
                'dose' => '1 tablet',
                'frequency' => 'Twice daily',
                'duration' => '5 days',
                'instructions' => 'After meals',
            ]],
        ]);

        $this->actingAs($doctor)
            ->get(route('encounters.show', $encounter))
            ->assertOk()
            ->assertSee('Pharmacy & medication', false)
            ->assertSee($medication->name)
            ->assertSee($prescription->prescription_number)
            ->assertSee('Prescribed');
    }

    public function test_nurse_can_view_prescriptions_but_not_create_them_from_workspace(): void
    {
        $this->seed(CityCareAccessSeeder::class);

        [$doctor, $encounter, $medication] = $this->workspaceContext('doctor');
        app(PharmacyService::class)->prescribe($encounter, $doctor, [
            'items' => [[
                'medication_id' => $medication->id,
                'quantity' => 5,
                'dose' => '1 tablet',
            ]],
        ]);

        $nurse = $this->userWithRole('nurse');
        StaffProfile::query()->updateOrCreate(
            ['user_id' => $nurse->id],
            ['department_id' => $doctor->staffProfile->department_id]
        );

        $this->actingAs($nurse)
            ->get(route('encounters.show', $encounter))
            ->assertOk()
            ->assertSee('Pharmacy & medication', false)
            ->assertSee($medication->name)
            ->assertDontSee('>Create prescription<', false);
    }

    public function test_pharmacy_staff_can_see_dispensing_status_in_workspace(): void
    {
        $this->seed(CityCareAccessSeeder::class);

        [$doctor, $encounter, $medication] = $this->workspaceContext('doctor');
        $prescription = app(PharmacyService::class)->prescribe($encounter, $doctor, [
            'items' => [[
                'medication_id' => $medication->id,
                'quantity' => 10,
                'dose' => '1 tablet',
            ]],
        ]);

        $pharmacy = $this->userWithRole('pharmacy');
        StaffProfile::query()->updateOrCreate(
            ['user_id' => $pharmacy->id],
            ['department_id' => $doctor->staffProfile->department_id]
        );
        app(PharmacyService::class)->dispense(
            $prescription,
            $pharmacy,
            [[
                'prescription_item_id' => $prescription->items->first()->id,
                'quantity_dispensed' => 5,
            ]]
        );

        $this->actingAs($pharmacy)
            ->get(route('encounters.pharmacy.show', $encounter))
            ->assertOk()
            ->assertSee($medication->name)
            ->assertSee('Partially dispensed')
            ->assertSee('Dispensing status');
    }

    private function workspaceContext(string $roleSlug): array
    {
        $doctor = $this->userWithRole($roleSlug);
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        StaffProfile::query()->updateOrCreate(
            ['user_id' => $doctor->id],
            ['department_id' => $department->id]
        );
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);
        $encounter = ClinicalEncounter::factory()->create([
            'facility_id' => $facility->id,
            'patient_id' => $patient->id,
            'clinician_id' => $doctor->id,
            'department_id' => $department->id,
            'status' => ClinicalEncounter::STATUS_OPEN,
        ]);
        $medication = Medication::factory()->create([
            'facility_id' => $facility->id,
            'is_active' => true,
        ]);

        return [$doctor, $encounter, $medication];
    }

    private function userWithRole(string $roleSlug): User
    {
        $user = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $roleId = Role::where('slug', $roleSlug)->valueOrFail('id');
        $user->roles()->attach($roleId);

        return $user;
    }
}
