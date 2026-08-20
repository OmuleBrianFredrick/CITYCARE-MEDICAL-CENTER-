<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class CityCareAccessSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'View dashboard', 'slug' => 'dashboard.view', 'group' => 'dashboard'],
            ['name' => 'View organization', 'slug' => 'organization.view', 'group' => 'administration'],
            ['name' => 'Manage organization', 'slug' => 'organization.manage', 'group' => 'administration'],
            ['name' => 'View patients', 'slug' => 'patients.view', 'group' => 'patients'],
            ['name' => 'Create patients', 'slug' => 'patients.create', 'group' => 'patients'],
            ['name' => 'Update patients', 'slug' => 'patients.update', 'group' => 'patients'],
            ['name' => 'Manage appointments', 'slug' => 'appointments.manage', 'group' => 'appointments'],
            ['name' => 'Manage reception and queues', 'slug' => 'reception.manage', 'group' => 'reception'],
            ['name' => 'View clinical encounters', 'slug' => 'clinical.encounters.view', 'group' => 'clinical'],
            ['name' => 'Create clinical encounters', 'slug' => 'clinical.encounters.create', 'group' => 'clinical'],
            ['name' => 'Update clinical encounters', 'slug' => 'clinical.encounters.update', 'group' => 'clinical'],
            ['name' => 'Record clinical vitals', 'slug' => 'clinical.vitals.manage', 'group' => 'clinical'],
            ['name' => 'Manage clinical diagnoses', 'slug' => 'clinical.diagnoses.manage', 'group' => 'clinical'],
            ['name' => 'Manage clinical notes', 'slug' => 'clinical.notes.manage', 'group' => 'clinical'],
            ['name' => 'Manage clinical treatment plans', 'slug' => 'clinical.treatment-plans.manage', 'group' => 'clinical'],
            ['name' => 'Manage clinical referrals', 'slug' => 'clinical.referrals.manage', 'group' => 'clinical'],
            ['name' => 'View diagnostic orders and results', 'slug' => 'laboratory.view', 'group' => 'laboratory'],
            ['name' => 'Create diagnostic orders', 'slug' => 'laboratory.orders.create', 'group' => 'laboratory'],
            ['name' => 'Record diagnostic results', 'slug' => 'laboratory.results.record', 'group' => 'laboratory'],
            ['name' => 'Complete or cancel diagnostic work', 'slug' => 'laboratory.work.manage', 'group' => 'laboratory'],
            ['name' => 'Manage laboratory', 'slug' => 'laboratory.manage', 'group' => 'laboratory'],
            ['name' => 'Manage pharmacy', 'slug' => 'pharmacy.manage', 'group' => 'pharmacy'],
            ['name' => 'View inventory', 'slug' => 'inventory.view', 'group' => 'inventory'],
            ['name' => 'Manage inventory', 'slug' => 'inventory.manage', 'group' => 'inventory'],
            ['name' => 'Manage billing and payments', 'slug' => 'billing.manage', 'group' => 'billing'],
            ['name' => 'View reports', 'slug' => 'reports.view', 'group' => 'reports'],
            ['name' => 'Manage staff accounts', 'slug' => 'staff.manage', 'group' => 'administration'],
            ['name' => 'Manage roles and permissions', 'slug' => 'access.manage', 'group' => 'administration'],
            ['name' => 'Manage system settings', 'slug' => 'settings.manage', 'group' => 'administration'],
            ['name' => 'View audit logs', 'slug' => 'audit.view', 'group' => 'security'],
            ['name' => 'Manage patient portal', 'slug' => 'patient-portal.manage', 'group' => 'patient-portal'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }

        $roles = [
            'super-admin' => ['name' => 'Super Administrator', 'description' => 'Full system authority, security administration, and organization configuration.', 'permissions' => Permission::query()->pluck('slug')->all()],
            'administrator' => ['name' => 'Administrator', 'description' => 'Operational management across the medical center.', 'permissions' => ['dashboard.view', 'organization.view', 'organization.manage', 'patients.view', 'patients.create', 'patients.update', 'appointments.manage', 'reception.manage', 'clinical.encounters.view', 'laboratory.view', 'laboratory.orders.create', 'laboratory.work.manage', 'laboratory.manage', 'pharmacy.manage', 'inventory.view', 'inventory.manage', 'billing.manage', 'reports.view', 'staff.manage', 'audit.view']],
            'receptionist' => ['name' => 'Receptionist', 'description' => 'Front-desk patient registration, scheduling, check-in, and queue operations.', 'permissions' => ['dashboard.view', 'patients.view', 'patients.create', 'patients.update', 'appointments.manage', 'reception.manage', 'billing.manage']],
            'doctor' => ['name' => 'Doctor / Clinician', 'description' => 'Clinical consultation, assessment, treatment, and referral workflows.', 'permissions' => ['dashboard.view', 'patients.view', 'appointments.manage', 'clinical.encounters.view', 'clinical.encounters.create', 'clinical.encounters.update', 'clinical.vitals.manage', 'clinical.diagnoses.manage', 'clinical.notes.manage', 'clinical.treatment-plans.manage', 'clinical.referrals.manage', 'laboratory.view', 'laboratory.orders.create', 'pharmacy.manage', 'reports.view']],
            'nurse' => ['name' => 'Nurse / Clinical Support', 'description' => 'Triage, vitals, nursing observations, and assigned clinical support.', 'permissions' => ['dashboard.view', 'patients.view', 'appointments.manage', 'reception.manage', 'clinical.encounters.view', 'clinical.vitals.manage', 'clinical.notes.manage', 'laboratory.view']],
            'laboratory' => ['name' => 'Laboratory Staff', 'description' => 'Laboratory order, specimen, result, verification, and release workflows.', 'permissions' => ['dashboard.view', 'patients.view', 'laboratory.view', 'laboratory.results.record', 'laboratory.work.manage', 'laboratory.manage', 'reports.view']],
            'pharmacy' => ['name' => 'Pharmacy Staff', 'description' => 'Prescription processing, dispensing, and pharmacy stock operations.', 'permissions' => ['dashboard.view', 'patients.view', 'pharmacy.manage', 'inventory.view', 'reports.view']],
            'cashier' => ['name' => 'Cashier / Finance', 'description' => 'Billing, payment, receipt, balance, and authorized financial operations.', 'permissions' => ['dashboard.view', 'patients.view', 'billing.manage', 'reports.view']],
            'records' => ['name' => 'Records Officer', 'description' => 'Controlled patient-record administration and records workflows.', 'permissions' => ['dashboard.view', 'patients.view', 'patients.create', 'patients.update', 'reports.view']],
            'inventory' => ['name' => 'Inventory / Stores Staff', 'description' => 'Suppliers, receiving, stock movements, inventory control, and reporting.', 'permissions' => ['dashboard.view', 'inventory.view', 'inventory.manage', 'reports.view']],
            'patient' => ['name' => 'Patient', 'description' => 'Patient-facing account with access limited to permitted portal functions.', 'permissions' => ['dashboard.view', 'patient-portal.manage']],
        ];

        foreach ($roles as $slug => $definition) {
            $role = Role::updateOrCreate(['slug' => $slug], ['name' => $definition['name'], 'description' => $definition['description'], 'is_system' => true]);
            $permissionIds = Permission::whereIn('slug', $definition['permissions'])->pluck('id');
            $role->permissions()->sync($permissionIds);
        }
    }
}
