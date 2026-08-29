<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteAuthorizationLeastPrivilegeTest extends TestCase
{
    public function test_all_application_routes_except_public_entry_points_require_authentication(): void
    {
        foreach ($this->applicationRoutes() as $route) {
            if ($this->isPublicEntryPoint($route->getName())) {
                continue;
            }

            $this->assertContains(
                'auth',
                $route->gatherMiddleware(),
                sprintf('Route [%s] must require authentication.', $route->getName())
            );
        }
    }

    public function test_all_protected_application_routes_have_an_explicit_permission_boundary_except_logout(): void
    {
        foreach ($this->applicationRoutes() as $route) {
            $name = $route->getName();

            if ($this->isPublicEntryPoint($name) || $name === 'logout') {
                continue;
            }

            $hasPermission = collect($route->gatherMiddleware())->contains(
                fn (string $middleware): bool => str_starts_with($middleware, 'permission:')
            );

            $this->assertTrue(
                $hasPermission,
                sprintf('Route [%s] must have an explicit permission boundary.', $name)
            );
        }
    }

    private function applicationRoutes(): array
    {
        return collect(Route::getRoutes())
            ->filter(function ($route): bool {
                $action = $route->getActionName();

                return is_string($action)
                    && str_starts_with($action, 'App\\Http\\Controllers\\');
            })
            ->values()
            ->all();
    }

    private function isPublicEntryPoint(?string $name): bool
    {
        return in_array($name, [
            'home',
            'login',
            'login.store',
            'portal.activation.create',
            'portal.activation.store',
            'staff-invitations.accept.create',
            'staff-invitations.accept.store',
        ], true);
    }
}
