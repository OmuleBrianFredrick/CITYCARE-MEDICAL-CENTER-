<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/access-check', function () {
        return response()->json([
            'user' => request()->user()->only(['id', 'name', 'email', 'user_type', 'is_active']),
            'roles' => request()->user()->roles()->pluck('slug'),
        ]);
    })->middleware('permission:dashboard.view')->name('access-check');
});
