<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientPortalController;
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
    })->middleware('permission:dashboard.view')->name('dashboard');

    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::middleware('permission:patients.view')->group(function () {
        Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
        Route::get('/patients/{patient}', [PatientController::class, 'show'])
            ->whereNumber('patient')
            ->name('patients.show');
        Route::get('/patients/{patient}/portal', [PatientPortalController::class, 'show'])
            ->whereNumber('patient')
            ->middleware('permission:patients.update')
            ->name('patients.portal.show');
    });

    Route::middleware('permission:patients.create')->group(function () {
        Route::get('/patients/create', [PatientController::class, 'create'])->name('patients.create');
        Route::post('/patients', [PatientController::class, 'store'])->name('patients.store');
    });

    Route::middleware('permission:patients.update')->group(function () {
        Route::post('/patients/{patient}/portal/provision', [PatientPortalController::class, 'provision'])
            ->whereNumber('patient')
            ->name('patients.portal.provision');
        Route::post('/patients/{patient}/portal/activate', [PatientPortalController::class, 'activate'])
            ->whereNumber('patient')
            ->name('patients.portal.activate');
        Route::post('/patients/{patient}/portal/disable', [PatientPortalController::class, 'disable'])
            ->whereNumber('patient')
            ->name('patients.portal.disable');
    });

    Route::prefix('organization')->name('organization.')->middleware('permission:organization.view')->group(function () {
        Route::get('/', [OrganizationController::class, 'index'])->name('index');

        Route::put('/facility', [OrganizationController::class, 'updateFacility'])
            ->middleware('permission:organization.manage')
            ->name('facility.update');

        Route::post('/departments', [OrganizationController::class, 'storeDepartment'])
            ->middleware('permission:organization.manage')
            ->name('departments.store');

        Route::post('/service-points', [OrganizationController::class, 'storeServicePoint'])
            ->middleware('permission:organization.manage')
            ->name('service-points.store');

        Route::put('/settings/{key}', [OrganizationController::class, 'updateSetting'])
            ->middleware('permission:organization.manage')
            ->where('key', '.*')
            ->name('settings.update');
    });
});
