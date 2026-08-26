<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientSearchJsonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_authorized_staff_can_search_active_patients_as_json(): void
    {
        $facility = Facility::query()->where('is_active', true)->firstOrFail();
        $staff = $this->userWithRole('receptionist');
        $matching = Patient::factory()->create([
            'facility_id' => $facility->id,
            'first_name' => 'Asha',
            'middle_name' => null,
            'last_name' => 'Nabirye',
            'medical_record_number' => 'CCMC-SEARCH-001',
        ]);
        Patient::factory()->create([
            'facility_id' => $facility->id,
            'first_name' => 'Asha',
            'last_name' => 'Inactive',
            'status' => Patient::STATUS_INACTIVE,
        ]);

        $this->actingAs($staff)
            ->getJson(route('patients.search', ['q' => 'Asha']))
            ->assertOk()
            ->assertJsonPath('meta.query', 'Asha')
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('data.0.full_name', 'Asha Nabirye')
            ->assertJsonPath('data.0.medical_record_number', 'CCMC-SEARCH-001');
    }

    public function test_patient_search_requires_an_authorized_role_and_valid_query(): void
    {
        $patientUser = $this->userWithRole('patient', 'patient');
        $receptionist = $this->userWithRole('receptionist');

        $this->actingAs($patientUser)
            ->getJson(route('patients.search', ['q' => 'Asha']))
            ->assertForbidden();

        $this->actingAs($receptionist)
            ->getJson(route('patients.search', ['q' => 'A']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    }

    public function test_patient_search_never_returns_a_matching_patient_from_another_facility(): void
    {
        $staff = $this->userWithRole('receptionist');
        $otherFacility = Facility::factory()->create(['is_active' => true]);
        $hidden = Patient::factory()->create([
            'facility_id' => $otherFacility->id,
            'first_name' => 'Boundary',
            'last_name' => 'Hidden',
        ]);

        $this->actingAs($staff)
            ->getJson(route('patients.search', ['q' => 'Boundary']))
            ->assertOk()
            ->assertJsonPath('meta.count', 0)
            ->assertJsonMissing(['id' => $hidden->id]);
    }

    private function userWithRole(string $roleSlug, string $userType = 'staff'): User
    {
        $user = User::factory()->create(['user_type' => $userType, 'is_active' => true]);
        $user->roles()->attach(Role::query()->where('slug', $roleSlug)->value('id'));

        if ($userType === 'staff') {
            $facility = Facility::query()->where('name', 'CityCare Medical Center')->firstOrFail();
            $department = Department::factory()->create(['facility_id' => $facility->id]);
            StaffProfile::create([
                'user_id' => $user->id,
                'department_id' => $department->id,
                'employee_number' => 'SEARCH-'.$user->id,
                'employment_status' => 'active',
            ]);
        }

        return $user;
    }
}
