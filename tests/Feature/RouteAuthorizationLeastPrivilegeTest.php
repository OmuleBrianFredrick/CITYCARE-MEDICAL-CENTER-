<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteAuthorizationLeastPrivilegeTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_application_routes_except_public_entry_points_require_authentication(): void
    {
        $publicNames = ['home', 'login', 'login.store'];

        foreach (Route::getRoutes() as $route) {
            if (! $route->getName() || in_array($route->getName(), $publicNames, true)) {
                continue;
            }

            if (! str_starts_with($route->uri(), '/') && ! str_starts_with($route->uri(), '')) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            $this->assertContains(
                'auth',
                $middleware,
                sprintf('Route [%s] must require authentication.', $route->getName())
            );
        }
    }

    public function test_all_named_protected_routes_have_an_explicit_permission_boundary_except_logout(): void
    {
        $excluded = ['home', 'login', 'login.store', 'logout'];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (! $name || in_array($name, $excluded, true)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            $hasPermission = collect($middleware)->contains(
                fn (string $value): bool => str_starts_with($value, 'permission:')
            );

            $this->assertTrue(
                $hasPermission,
                sprintf('Route [%s] must have an explicit permission boundary.', $name)
            );
        }
    }
}
