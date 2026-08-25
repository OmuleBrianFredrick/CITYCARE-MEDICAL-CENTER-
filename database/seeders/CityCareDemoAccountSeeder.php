<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CityCareDemoAccountSeeder extends Seeder
{
    private const ACCOUNTS = [
        ['name' => 'CityCare Test Administrator', 'email' => 'admin@citycare.test', 'role' => 'super-admin', 'password_suffix' => 'super-admin', 'department' => 'ADMIN', 'service_point' => 'ADMIN-MAIN', 'employee_number' => 'CC-DEMO-SUPER', 'job_title' => 'Super Administrator'],
        ['name' => 'CityCare Test Operations Administrator', 'email' => 'administrator@citycare.test', 'role' => 'administrator', 'password_suffix' => 'administrator', 'department' => 'ADMIN', 'service_point' => 'ADMIN-MAIN', 'employee_number' => 'CC-DEMO-ADMIN', 'job_title' => 'Operations Administrator'],
        ['name' => 'CityCare Test Receptionist', 'email' => 'reception@citycare.test', 'role' => 'receptionist', 'password_suffix' => 'reception', 'department' => 'RECEPTION', 'service_point' => 'RECEPTION-MAIN', 'employee_number' => 'CC-DEMO-RECEPTION', 'job_title' => 'Receptionist'],
        ['name' => 'CityCare Test Doctor', 'email' => 'doctor@citycare.test', 'role' => 'doctor', 'password_suffix' => 'doctor', 'department' => 'OPD', 'service_point' => 'OPD-GENERAL', 'employee_number' => 'CC-DEMO-DOCTOR', 'job_title' => 'Doctor'],
        ['name' => 'CityCare Test Nurse', 'email' => 'nurse@citycare.test', 'role' => 'nurse', 'password_suffix' => 'nurse', 'department' => 'NURSING', 'service_point' => 'NURSING-TRIAGE', 'employee_number' => 'CC-DEMO-NURSE', 'job_title' => 'Nurse'],
        ['name' => 'CityCare Test Laboratory', 'email' => 'laboratory@citycare.test', 'role' => 'laboratory', 'password_suffix' => 'laboratory', 'department' => 'LAB', 'service_point' => 'LAB-SAMPLE', 'employee_number' => 'CC-DEMO-LAB', 'job_title' => 'Laboratory Technologist'],
        ['name' => 'CityCare Test Pharmacy', 'email' => 'pharmacy@citycare.test', 'role' => 'pharmacy', 'password_suffix' => 'pharmacy', 'department' => 'PHARMACY', 'service_point' => 'PHARMACY-MAIN', 'employee_number' => 'CC-DEMO-PHARMACY', 'job_title' => 'Pharmacy Officer'],
        ['name' => 'CityCare Test Cashier', 'email' => 'cashier@citycare.test', 'role' => 'cashier', 'password_suffix' => 'cashier', 'department' => 'FINANCE', 'service_point' => 'FINANCE-CASHIER', 'employee_number' => 'CC-DEMO-CASHIER', 'job_title' => 'Cashier'],
        ['name' => 'CityCare Test Records Officer', 'email' => 'records@citycare.test', 'role' => 'records', 'password_suffix' => 'records', 'department' => 'RECORDS', 'service_point' => 'RECORDS-MAIN', 'employee_number' => 'CC-DEMO-RECORDS', 'job_title' => 'Records Officer'],
        ['name' => 'CityCare Test Inventory Officer', 'email' => 'inventory@citycare.test', 'role' => 'inventory', 'password_suffix' => 'inventory', 'department' => 'STORES', 'service_point' => 'STORES-MAIN', 'employee_number' => 'CC-DEMO-INVENTORY', 'job_title' => 'Inventory Officer'],
        ['name' => 'CityCare Test Patient', 'email' => 'patient@citycare.test', 'role' => 'patient', 'password_suffix' => 'patient', 'user_type' => 'patient'],
    ];

    public function run(): void
    {
        $basePassword = env('CITYCARE_TEST_PASSWORD');

        if (! is_string($basePassword) || strlen($basePassword) < 12) {
            throw ValidationException::withMessages([
                'CITYCARE_TEST_PASSWORD' => 'Set a local CITYCARE_TEST_PASSWORD of at least 12 characters in .env.',
            ]);
        }

        foreach (self::ACCOUNTS as $account) {
            $role = Role::query()->where('slug', $account['role'])->firstOrFail();
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make(self::passwordFor($basePassword, $account['password_suffix'])),
                    'user_type' => $account['user_type'] ?? 'staff',
                    'is_active' => true,
                ],
            );

            $user->roles()->sync([$role->id]);

            if ($user->isStaff()) {
                $this->syncStaffProfile($user, $account);
            }
        }
    }

    public static function passwordFor(string $basePassword, string $suffix): string
    {
        return $basePassword.'-'.$suffix;
    }

    private function syncStaffProfile(User $user, array $account): void
    {
        $department = Department::query()->where('code', $account['department'])->firstOrFail();
        $servicePoint = ServicePoint::query()->where('code', $account['service_point'])->firstOrFail();

        StaffProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'department_id' => $department->id,
                'service_point_id' => $servicePoint->id,
                'employee_number' => $account['employee_number'],
                'job_title' => $account['job_title'],
                'employment_status' => 'active',
                'phone' => null,
                'joined_at' => '2025-01-01',
            ],
        );
    }
}
