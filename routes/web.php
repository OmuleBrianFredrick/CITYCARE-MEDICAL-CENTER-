<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingCatalogController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ClinicalCareController;
use App\Http\Controllers\ClinicalDiagnosisController;
use App\Http\Controllers\ClinicalEncounterController;
use App\Http\Controllers\ClinicalReferralAttachmentController;
use App\Http\Controllers\ClinicalReferralController;
use App\Http\Controllers\ClinicalTreatmentPlanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeInvitationAcceptanceController;
use App\Http\Controllers\InventoryCatalogController;
use App\Http\Controllers\InventoryProcurementController;
use App\Http\Controllers\LaboratoryOrderController;
use App\Http\Controllers\LaboratoryWorkspaceController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientNotificationController;
use App\Http\Controllers\PatientPortalActivationController;
use App\Http\Controllers\PatientPortalController;
use App\Http\Controllers\PatientPortalWorkspaceController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\PharmacyWorkspaceController;
use App\Http\Controllers\ReportingController;
use App\Http\Controllers\RolePermissionAdministrationController;
use App\Http\Controllers\StaffAdministrationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/portal/activate/{token}', [PatientPortalActivationController::class, 'create'])->name('portal.activation.create');
    Route::post('/portal/activate', [PatientPortalActivationController::class, 'store'])->middleware('throttle:6,1')->name('portal.activation.store');
    Route::get('/staff/setup/{token}', [EmployeeInvitationAcceptanceController::class, 'create'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('staff-invitations.accept.create');
    Route::post('/staff/setup', [EmployeeInvitationAcceptanceController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('staff-invitations.accept.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permission:dashboard.view')->name('dashboard');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::middleware('permission:patient-portal.manage')->group(function () {
        Route::get('/portal', [PatientPortalWorkspaceController::class, 'index'])->name('portal.index');
        Route::post('/portal/notifications/read-all', [PatientNotificationController::class, 'markAllRead'])->name('portal.notifications.read-all');
        Route::post('/portal/notifications/{notification}/read', [PatientNotificationController::class, 'markRead'])->name('portal.notifications.read');
    });

    Route::middleware('permission:patients.view')->group(function () {
        Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
        Route::get('/patients/search', [PatientController::class, 'search'])->name('patients.search');
        Route::get('/patients/{patient}', [PatientController::class, 'show'])->whereNumber('patient')->name('patients.show');
        Route::get('/patients/{patient}/portal', [PatientPortalController::class, 'show'])->whereNumber('patient')->middleware('permission:patients.update')->name('patients.portal.show');
    });
    Route::middleware('permission:patients.create')->group(function () {
        Route::get('/patients/create', [PatientController::class, 'create'])->name('patients.create');
        Route::post('/patients', [PatientController::class, 'store'])->name('patients.store');
    });
    Route::middleware('permission:patients.update')->group(function () {
        Route::post('/patients/{patient}/portal/provision', [PatientPortalController::class, 'provision'])->whereNumber('patient')->name('patients.portal.provision');
        Route::post('/patients/{patient}/portal/invitation', [PatientPortalController::class, 'invitation'])->whereNumber('patient')->name('patients.portal.invitation');
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

    Route::get('/laboratory', [LaboratoryWorkspaceController::class, 'index'])
        ->middleware('permission:laboratory.view')
        ->name('laboratory.index');

    Route::get('/pharmacy', [PharmacyWorkspaceController::class, 'index'])
        ->middleware('permission:pharmacy.view')
        ->name('pharmacy.index');

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
        Route::get('/referral-attachments/{attachment}/download', [ClinicalReferralAttachmentController::class, 'download'])->whereNumber('attachment')->name('referrals.attachments.download');
        Route::delete('/referral-attachments/{attachment}', [ClinicalReferralAttachmentController::class, 'destroy'])->middleware('permission:clinical.referrals.manage')->whereNumber('attachment')->name('referrals.attachments.destroy');
        Route::post('/{encounter}/laboratory-orders', [LaboratoryOrderController::class, 'store'])->middleware('permission:laboratory.orders.create')->whereNumber('encounter')->name('laboratory-orders.store');
        Route::post('/{encounter}/close', [ClinicalEncounterController::class, 'close'])->middleware('permission:clinical.encounters.update')->whereNumber('encounter')->name('close');
        Route::post('/{encounter}/cancel', [ClinicalEncounterController::class, 'cancel'])->middleware('permission:clinical.encounters.update')->whereNumber('encounter')->name('cancel');
        Route::post('/', [ClinicalEncounterController::class, 'store'])->middleware('permission:clinical.encounters.create')->name('store');
    });

    Route::prefix('encounters')->name('encounters.')->group(function () {
        Route::post('/laboratory-order-items/{item}/result', [LaboratoryOrderController::class, 'recordResult'])
            ->middleware('permission:laboratory.results.record')
            ->whereNumber('item')
            ->name('laboratory-order-items.result.store');

        Route::post('/laboratory-orders/{order}/cancel', [LaboratoryOrderController::class, 'cancel'])
            ->middleware('permission:laboratory.work.manage')
            ->whereNumber('order')
            ->name('laboratory-orders.cancel');

        Route::post('/{encounter}/prescriptions', [PharmacyController::class, 'store'])
            ->middleware('permission:pharmacy.prescriptions.create')
            ->whereNumber('encounter')
            ->name('prescriptions.store');

        Route::get('/{encounter}/pharmacy', [PharmacyController::class, 'show'])
            ->middleware('permission:pharmacy.view')
            ->whereNumber('encounter')
            ->name('pharmacy.show');

        Route::post('/prescriptions/{prescription}/dispense', [PharmacyController::class, 'dispense'])
            ->middleware('permission:pharmacy.dispensing.manage')
            ->whereNumber('prescription')
            ->name('prescriptions.dispense');

        Route::post('/prescriptions/{prescription}/cancel', [PharmacyController::class, 'cancel'])
            ->middleware('permission:pharmacy.work.manage')
            ->whereNumber('prescription')
            ->name('prescriptions.cancel');
    });

    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('/', [BillingController::class, 'index'])
            ->middleware('permission:billing.view')
            ->name('index');

        Route::middleware('permission:billing.manage')->group(function () {
            Route::get('/catalogue', [BillingCatalogController::class, 'index'])->name('catalogue.index');
            Route::post('/catalogue/services', [BillingCatalogController::class, 'storeService'])->name('catalogue.services.store');
            Route::put('/catalogue/services/{billableService}', [BillingCatalogController::class, 'updateService'])->whereNumber('billableService')->name('catalogue.services.update');
            Route::post('/catalogue/services/{billableService}/prices', [BillingCatalogController::class, 'storePrice'])->whereNumber('billableService')->name('catalogue.prices.store');
            Route::put('/catalogue/prices/{servicePrice}', [BillingCatalogController::class, 'updatePrice'])->whereNumber('servicePrice')->name('catalogue.prices.update');
        });

        Route::get('/patients/{patient}', [BillingController::class, 'show'])
            ->middleware('permission:billing.view')
            ->whereNumber('patient')
            ->name('show');

        Route::post('/patients/{patient}/charges', [BillingController::class, 'storeCharge'])
            ->middleware('permission:billing.charges.manage')
            ->whereNumber('patient')
            ->name('charges.store');

        Route::post('/patients/{patient}/invoices', [BillingController::class, 'storeInvoice'])
            ->middleware('permission:billing.invoices.manage')
            ->whereNumber('patient')
            ->name('invoices.store');

        Route::post('/invoices/{invoice}/payments', [BillingController::class, 'storePayment'])
            ->middleware('permission:billing.payments.record')
            ->whereNumber('invoice')
            ->name('payments.store');

        Route::post('/invoices/{invoice}/cancel', [BillingController::class, 'cancelInvoice'])
            ->middleware('permission:billing.work.manage')
            ->whereNumber('invoice')
            ->name('invoices.cancel');

        Route::post('/charges/{charge}/void', [BillingController::class, 'voidCharge'])
            ->middleware('permission:billing.work.manage')
            ->whereNumber('charge')
            ->name('charges.void');

        Route::post('/payments/{payment}/reverse', [BillingController::class, 'reversePayment'])
            ->middleware('permission:billing.work.manage')
            ->whereNumber('payment')
            ->name('payments.reverse');

        Route::post('/invoices/{invoice}/refresh', [BillingController::class, 'refreshInvoice'])
            ->middleware('permission:billing.work.manage')
            ->whereNumber('invoice')
            ->name('invoices.refresh');
    });

    Route::prefix('inventory')->name('inventory.')->middleware('permission:inventory.view')->group(function () {
        Route::get('/catalogue', [InventoryCatalogController::class, 'index'])->name('catalogue.index');
        Route::middleware('permission:inventory.manage')->group(function () {
            Route::post('/catalogue/items', [InventoryCatalogController::class, 'storeItem'])->name('catalogue.items.store');
            Route::put('/catalogue/items/{inventoryItem}', [InventoryCatalogController::class, 'updateItem'])->whereNumber('inventoryItem')->name('catalogue.items.update');
            Route::post('/catalogue/suppliers', [InventoryCatalogController::class, 'storeSupplier'])->name('catalogue.suppliers.store');
            Route::put('/catalogue/suppliers/{inventorySupplier}', [InventoryCatalogController::class, 'updateSupplier'])->whereNumber('inventorySupplier')->name('catalogue.suppliers.update');
            Route::post('/catalogue/stores', [InventoryCatalogController::class, 'storeStore'])->name('catalogue.stores.store');
            Route::put('/catalogue/stores/{inventoryStore}', [InventoryCatalogController::class, 'updateStore'])->whereNumber('inventoryStore')->name('catalogue.stores.update');
            Route::post('/adjustments', [InventoryCatalogController::class, 'adjust'])->name('adjustments.store');
        });
    });

    Route::prefix('inventory/procurement')->name('inventory.procurement.')->middleware('permission:inventory.view')->group(function () {
        Route::get('/', [InventoryProcurementController::class, 'index'])->name('index');
        Route::get('/create', [InventoryProcurementController::class, 'create'])->middleware('permission:inventory.manage')->name('create');
        Route::get('/{purchaseOrder}', [InventoryProcurementController::class, 'show'])->whereNumber('purchaseOrder')->name('show');
        Route::post('/', [InventoryProcurementController::class, 'store'])->middleware('permission:inventory.manage')->name('store');
        Route::post('/{purchaseOrder}/items', [InventoryProcurementController::class, 'addItem'])->middleware('permission:inventory.manage')->whereNumber('purchaseOrder')->name('items.store');
        Route::post('/{purchaseOrder}/submit', [InventoryProcurementController::class, 'submit'])->middleware('permission:inventory.manage')->whereNumber('purchaseOrder')->name('submit');
        Route::post('/{purchaseOrder}/receive', [InventoryProcurementController::class, 'receive'])->middleware('permission:inventory.manage')->whereNumber('purchaseOrder')->name('receive');
        Route::post('/{purchaseOrder}/cancel', [InventoryProcurementController::class, 'cancel'])->middleware('permission:inventory.manage')->whereNumber('purchaseOrder')->name('cancel');
    });

    Route::prefix('reports')->name('reports.')->middleware('permission:reports.view')->group(function () {
        Route::get('/', [ReportingController::class, 'index'])->name('index');
        Route::post('/{reportDefinition}/run', [ReportingController::class, 'run'])->whereNumber('reportDefinition')->name('run');
        Route::get('/runs/{reportRun}', [ReportingController::class, 'show'])->whereNumber('reportRun')->name('show');
        Route::post('/export', [ReportingController::class, 'export'])->name('export');
    });

    Route::get('/audit', [AuditLogController::class, 'index'])
        ->middleware('permission:audit.view')
        ->name('audit.index');

    Route::prefix('administration/staff')->name('staff.')->middleware('permission:staff.manage')->group(function () {
        Route::get('/', [StaffAdministrationController::class, 'index'])->name('index');
        Route::get('/create', [StaffAdministrationController::class, 'create'])->name('create');
        Route::post('/', [StaffAdministrationController::class, 'store'])->name('store');
        Route::get('/{staff}/edit', [StaffAdministrationController::class, 'edit'])->whereNumber('staff')->name('edit');
        Route::put('/{staff}', [StaffAdministrationController::class, 'update'])->whereNumber('staff')->name('update');
        Route::put('/{staff}/roles', [StaffAdministrationController::class, 'syncRoles'])->whereNumber('staff')->name('roles.update');
        Route::post('/{staff}/deactivate', [StaffAdministrationController::class, 'deactivate'])->whereNumber('staff')->name('deactivate');
        Route::post('/{staff}/reactivate', [StaffAdministrationController::class, 'reactivate'])->whereNumber('staff')->name('reactivate');
        Route::post('/{staff}/invitations', [StaffAdministrationController::class, 'reissueInvitation'])->whereNumber('staff')->name('invitations.reissue');
        Route::delete('/{staff}/invitations/{invitation}', [StaffAdministrationController::class, 'revokeInvitation'])->whereNumber(['staff', 'invitation'])->name('invitations.revoke');
    });

    Route::prefix('administration/access/roles')->name('access.roles.')->middleware('permission:access.manage')->group(function () {
        Route::get('/', [RolePermissionAdministrationController::class, 'index'])->name('index');
        Route::put('/{role}', [RolePermissionAdministrationController::class, 'update'])->whereNumber('role')->name('update');
    });

    Route::prefix('organization')->name('organization.')->middleware('permission:organization.view')->group(function () {
        Route::get('/', [OrganizationController::class, 'index'])->name('index');
        Route::put('/facility', [OrganizationController::class, 'updateFacility'])->middleware('permission:organization.manage')->name('facility.update');
        Route::post('/departments', [OrganizationController::class, 'storeDepartment'])->middleware('permission:organization.manage')->name('departments.store');
        Route::post('/service-points', [OrganizationController::class, 'storeServicePoint'])->middleware('permission:organization.manage')->name('service-points.store');
        Route::put('/settings/{key}', [OrganizationController::class, 'updateSetting'])->middleware('permission:organization.manage')->where('key', '.*')->name('settings.update');
    });
});
