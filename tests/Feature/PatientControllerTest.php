<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PatientControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_staff_can_view_registry_and_registration_form(): void
    {
        $this->seed();
        $user = $this->staffWithRole('receptionist');
        Facility::create(['name' => 'CityCare Medical Center', 'country' => 'Uganda', 'timezone' => 'Africa/Kampala', 'currency' => 'UGX', 'is_active' => true]);

        $this->actingAs($user)->get(route('patients.index'))->assertOk();
        $this->actingAs($user)->get(route('patients.create'))->assertOk();
    }

    public function test_staff_can_register_patient_through_http_workflow(): void
    {
        $this->seed();
        $user = $this->staffWithRole('receptionist');
        $facility = Facility::create(['name' => 'CityCare Medical Center', 'country' => 'Uganda', 'timezone' => 'Africa/Kampala', 'currency' => 'UGX', 'is_active' => true]);

        $response = $this->actingAs($user)->post(route('patients.store'), [
            'facility_id' => $facility->id,
            'first_name' => 'Amina',
            'middle_name' => 'N.',
            'last_name' => 'Kato',
            'sex' => 'female',
            'date_of_birth' => '1995-03-18',
            'phone' => '+256700555666',
            'email' => 'amina@example.test',
            'country' => 'Uganda',
            'district' => 'Kampala',
            'emergency_contact_name' => 'Moses Kato',
            'emergency_contact_relationship' => 'Brother',
            'emergency_contact_phone' => '+256700777888',
        ]);

        $patient = Patient::firstOrFail();

        $response->assertRedirect(route('patients.show', $patient));
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'first_name' => 'Amina', 'facility_id' => $facility->id]);
        $this->actingAs($user)->get(route('patients.show', $patient))->assertOk()->assertSee('Amina N. Kato');
    }

    public function test_user_without_patient_create_permission_cannot_register_patient(): void
    {
        $this->seed();
        $user = $this->staffWithRole('laboratory');
        $facility = Facility::create(['name' => 'CityCare Medical Center', 'country' => 'Uganda', 'timezone' => 'Africa/Kampala', 'currency' => 'UGX', 'is_active' => true]);

        $this->actingAs($user)->get(route('patients.create'))->assertForbidden();
        $this->actingAs($user)->post(route('patients.store'), [
            'facility_id' => $facility->id,
            'first_name' => 'Denied',
            'last_name' => 'User',
            'country' => 'Uganda',
        ])->assertForbidden();
    }

    public function test_patient_registry_can_search_by_name_and_mrn(): void
    {
        $this->seed();
        $user = $this->staffWithRole('receptionist');
        $facility = Facility::create(['name' => 'CityCare Medical Center', 'country' => 'Uganda', 'timezone' => 'Africa/Kampala', 'currency' => 'UGX', 'is_active' => true]);
        $patient = Patient::create([
            'facility_id' => $facility->id,
            'medical_record_number' => 'CCMC-2026-ABC1234',
            'first_name' => 'Grace',
            'last_name' => 'Namukasa',
            'country' => 'Uganda',
            'status' => Patient::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)->get(route('patients.index', ['search' => 'Grace']))
            ->assertOk()->assertSee('Grace Namukasa');
        $this->actingAs($user)->get(route('patients.index', ['search' => $patient->medical_record_number]))
            ->assertOk()->assertSee($patient->medical_record_number);
    }

    private function staffWithRole(string $roleSlug): User
    {
        $user = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => true,
            'password' => Hash::make('Password123!'),
        ]);

        $user->roles()->attach(Role::where('slug', $roleSlug)->firstOrFail());

        return $user;
    }
}
