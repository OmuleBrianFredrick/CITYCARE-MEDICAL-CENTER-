<?php

namespace App\Providers;

use App\Services\DashboardWorkspaceService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email', '')));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        View::composer('layouts.app', function ($view): void {
            if (auth()->check()) {
                $view->with('shell', app(DashboardWorkspaceService::class)->shell(auth()->user()));
            }
        });
    }
}
