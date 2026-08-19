<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_active_staff_can_sign_in_and_last_login_is_recorded(): void
    {
        $user = User::factory()->create([
            'name' => 'Dr. CityCare',
            'email' => 'doctor@citycare.test',
            'password' => Hash::make('SecurePass123!'),
            'user_type' => 'staff',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('slug', 'doctor')->firstOrFail());

        $response = $this->post(route('login.store'), [
            'email' => 'doctor@citycare.test',
            'password' => 'SecurePass123!',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_inactive_account_cannot_sign_in(): void
    {
        User::factory()->create([
            'email' => 'inactive@citycare.test',
            'password' => Hash::make('SecurePass123!'),
            'user_type' => 'staff',
            'is_active' => false,
        ]);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'inactive@citycare.test',
            'password' => 'SecurePass123!',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_invalid_credentials_do_not_authenticate(): void
    {
        User::factory()->create([
            'email' => 'valid@citycare.test',
            'password' => Hash::make('CorrectPassword123!'),
            'is_active' => true,
        ]);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'valid@citycare.test',
            'password' => 'WrongPassword!',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_successful_login_clears_login_throttle(): void
    {
        $user = User::factory()->create([
            'email' => 'throttle@citycare.test',
            'password' => Hash::make('CorrectPassword123!'),
            'is_active' => true,
        ]);

        foreach (range(1, 4) as $attempt) {
            $this->post(route('login.store'), [
                'email' => 'throttle@citycare.test',
                'password' => 'WrongPassword!',
            ])->assertRedirect(route('login'));
        }

        $this->post(route('login.store'), [
            'email' => 'throttle@citycare.test',
            'password' => 'CorrectPassword123!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_permission_middleware_denies_unauthorized_user(): void
    {
        $user = User::factory()->create([
            'user_type' => 'staff',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_logout_invalidates_authenticated_session(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
