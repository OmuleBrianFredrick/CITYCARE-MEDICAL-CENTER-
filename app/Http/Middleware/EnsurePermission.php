<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        abort_unless(
            $request->user() && $request->user()->isActive() && $request->user()->hasPermissionTo($permission),
            403,
            'You are not authorized to perform this action.'
        );

        return $next($request);
    }
}
