<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClinicalCareController;
use App\Http\Controllers\ClinicalDiagnosisController;
use App\Http\Controllers\ClinicalEncounterController;
use App\Http\Controllers\ClinicalReferralAttachmentController;
use App\Http\Controllers\ClinicalReferralController;
use App\Http\Controllers\ClinicalTreatmentPlanController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientPortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', function () { return view('dashboard'); })->middleware('permission:dashboard.view')->name('dashboard');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::middleware('permission:patients.view')->group(function () {
        Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
        Route::get('/patients/{patient}', [PatientController::class, 'show'])->whereNumber('patient')->name('patients.show');
        Route::get('/patients/{patient}/portal', [PatientPortalController::class, 'show'])->whereNumber('patient')->middleware('permission:patients.update')->name('patients.portal.show');
    });
    Route::middleware('permission:patients.create')->group(function () {
        Route::get('/patients/create', [PatientController::class, 'create'])->name('patients.create');
        Route::post('/patients', [PatientController::class, 'store'])->name('patients.store');
    });
    Route::middleware('permission:patients.update')->group(function () {
        Route::post('/patients/{patient}/portal/provision', [PatientPortalController::class, 'provision'])->whereNumber('patient')->name('patients.portal.provision');
        Route::post('/patients/{patient}/portal/activate', [PatientPortalController::class, 'activate'])->whereNumber('patient')->name('patients.portal.activate');
        Route::post('/patients/{patient}/portal/disable', [PatientPortalController::class, 'disable'])->whereNumber('patient')->name('patients.portal.disable');
    });

    Route::middleware('permission:appointments.manage')->prefix('appointments')->name('appointments.')->group(function () {
        Route::get('/', [AppointmentController::class, 'index'])->name('index');
        Route::get('/create', [AppointmentController::class, 'create'])->name('create');
        Route::post('/', [AppointmentController::class, 'store'])->name('store');
        Route::post('/{appointment}/check-in', [AppointmentController::class, 'checkIn'])->whereNumber('appointment')->name('check-in');
        Route::post('/{appointment}/complete', [AppointmentController::class, 'complete'])->whereNumber('appointment')->name('complete');
        Route::post('/{appointment}/cancel', [AppointmentController::class, 'cancel'])->whereNumber('appointment')->name('cancel');
    });

    Route::middleware('permission:clinical.encounters.view')->prefix('encounters')->name('encounters.')->group(function () {
        Route::get('/', [ClinicalEncounterController::class, 'index'])->name('index');
        Route::get('/create', [ClinicalEncounterController::class, 'create'])->middleware('permission:clinical.encounters.create')->name('create');
        Route::get('/{encounter}', [ClinicalEncounterController::class, 'show'])->whereNumber('encounter')->name('show');
        Route::post('/{encounter}/diagnoses', [ClinicalDiagnosisController::class, 'store'])->middleware('permission:clinical.diagnoses.manage')->whereNumber('encounter')->name('diagnoses.store');
        Route::post('/{encounter}/notes', [ClinicalCareController::class, 'storeNote'])->middleware('permission:clinical.notes.manage')->whereNumber('encounter')->name('notes.store');
        Route::post('/notes/{note}/finalize', [ClinicalCareController::class, 'finalizeNote'])->middleware('permission:clinical.notes.manage')->whereNumber('note')->name('notes.finalize');
        Route::post('/{encounter}/vitals', [ClinicalCareController::class, 'storeVitals'])->middleware('permission:clinical.vitals.manage')->whereNumber('encounter')->name('vitals.store');
        Route::post('/{encounter}/treatment-plans', [ClinicalTreatmentPlanController::class, 'store'])->middleware('permission:clinical.treatment-plans.manage')->whereNumber('encounter')->name('treatment-plans.store');
        Route::post('/treatment-plans/{plan}/complete', [ClinicalTreatmentPlanController::class, 'complete'])->middleware('permission:clinical.treatment-plans.manage')->whereNumber('plan')->name('treatment-plans.complete');
        Route::post('/treatment-plans/{plan}/cancel', [ClinicalTreatmentPlanController::class, 'cancel'])->middleware('permission:clinical.treatment-plans.manage')->whereNumber('plan')->name('treatment-plans.cancel');
        Route::post('/{encounter}/referrals', [ClinicalReferralController::class, 'store'])->middleware('permission:clinical.referrals.manage')->whereNumber('encounter')->name('referrals.store');
        Route::post('/referrals/{referral}/accept', [ClinicalReferralController::class, 'accept'])->middleware('permission:clinical.referrals.manage')->whereNumber('referral')->name('referrals.accept');
        Route::post('/referrals/{referral}/complete', [ClinicalReferralController::class, 'complete'])->middleware('permission:clinical.referrals.manage')->whereNumber('referral')->name('referrals.complete');
        Route::post('/referrals/{referral}/cancel', [ClinicalReferralController::class, 'cancel'])->middleware('permission:clinical.referrals.manage')->whereNumber('referral')->name('referrals.cancel');
        Route::post('/referrals/{referral}/attachments', [ClinicalReferralAttachmentController::class, 'store'])->middleware('permission:clinical.referrals.manage')->whereNumber('referral')->name('referrals.attachments.store');
        Route::delete('/referral-attachments/{attachment}', [ClinicalReferralAttachmentController::class, 'destroy'])->middleware('permission:clinical.referrals.manage')->whereNumber('attachment')->name('referrals.attachments.destroy');
        Route::post('/{encounter}/close', [ClinicalEncounterController::class, 'close'])->middleware('permission:clinical.encounters.update')->whereNumber('encounter')->name('close');
        Route::post('/{encounter}/cancel', [ClinicalEncounterController::class, 'cancel'])->middleware('permission:clinical.encounters.update')->whereNumber('encounter')->name('cancel');
        Route::post('/', [ClinicalEncounterController::class, 'store'])->middleware('permission:clinical.encounters.create')->name('store');
    });

    Route::prefix('organization')->name('organization.')->middleware('permission:organization.view')->group(function () {
        Route::get('/', [OrganizationController::class, 'index'])->name('index');
        Route::put('/facility', [OrganizationController::class, 'updateFacility'])->middleware('permission:organization.manage')->name('facility.update');
        Route::post('/departments', [OrganizationController::class, 'storeDepartment'])->middleware('permission:organization.manage')->name('departments.store');
        Route::post('/service-points', [OrganizationController::class, 'storeServicePoint'])->middleware('permission:organization.manage')->name('service-points.store');
        Route::put('/settings/{key}', [OrganizationController::class, 'updateSetting'])->middleware('permission:organization.manage')->where('key', '.*')->name('settings.update');
    });
});
