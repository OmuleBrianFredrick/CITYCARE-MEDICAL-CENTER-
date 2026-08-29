<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\PatientPortalService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PatientPortalActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_patient_uses_secure_setup_link_to_choose_a_password_and_activate_portal(): void
    {
        $facility = Facility::query()->where('is_active', true)->firstOrFail();
        $patient = Patient::factory()->create([
            'facility_id' => $facility->id,
            'email' => 'setup.patient@citycare.test',
        ]);
        $staff = $this->staffWithRole('administrator', $facility);

        $response = $this->actingAs($staff)
            ->post(route('patients.portal.provision', $patient))
            ->assertRedirect()
            ->assertSessionHas('portal_activation_url');

        $activationUrl = $response->getSession()->get('portal_activation_url');
        $parts = parse_url($activationUrl);
        parse_str($parts['query'] ?? '', $query);
        $token = basename($parts['path']);

        $this->post(route('logout'));

        $this->get($activationUrl)
            ->assertOk()
            ->assertSee('Create your password')
            ->assertSee('setup.patient@citycare.test');

        $this->post(route('portal.activation.store'), [
            'token' => $token,
            'email' => $query['email'],
            'password' => 'PatientPortal123!',
            'password_confirmation' => 'PatientPortal123!',
        ])->assertRedirect(route('login'));

        $patient->refresh();
        $account = $patient->user->fresh();

        $this->assertTrue($account->is_active);
        $this->assertTrue(Hash::check('PatientPortal123!', $account->password));
        $this->assertNotNull($account->email_verified_at);
        $this->assertNotNull($patient->portal_activated_at);
        $this->assertNull($patient->portal_disabled_at);

        $this->post(route('login.store'), [
            'email' => $account->email,
            'password' => 'PatientPortal123!',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertRedirect(route('portal.index'));
        $this->get(route('portal.index'))->assertOk()->assertSee($patient->medical_record_number);
    }

    public function test_invalid_setup_token_does_not_activate_patient_account(): void
    {
        $patient = Patient::factory()->create(['email' => 'invalid.setup@citycare.test']);
        $account = app(PatientPortalService::class)->provision($patient);

        $this->post(route('portal.activation.store'), [
            'token' => 'not-a-valid-token',
            'email' => $account->email,
            'password' => 'PatientPortal123!',
            'password_confirmation' => 'PatientPortal123!',
        ])->assertSessionHasErrors('email');

        $this->assertFalse($account->fresh()->is_active);
        $this->assertNull($patient->fresh()->portal_activated_at);
    }

    private function staffWithRole(string $roleSlug, Facility $facility): User
    {
        $user = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $user->roles()->attach(Role::query()->where('slug', $roleSlug)->firstOrFail());
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        StaffProfile::create(['user_id' => $user->id, 'department_id' => $department->id]);

        return $user;
    }
}
