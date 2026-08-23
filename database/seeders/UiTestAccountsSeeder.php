<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UiTestAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('CITYCARE_TEST_PASSWORD');

        if (! is_string($password) || strlen($password) < 12) {
            throw ValidationException::withMessages([
                'CITYCARE_TEST_PASSWORD' => 'Set a local CITYCARE_TEST_PASSWORD of at least 12 characters in .env.',
            ]);
        }

        $accounts = [
            ['name' => 'CityCare Test Administrator', 'email' => 'admin@citycare.test', 'role' => 'super-admin'],
            ['name' => 'CityCare Test Receptionist', 'email' => 'reception@citycare.test', 'role' => 'receptionist'],
            ['name' => 'CityCare Test Doctor', 'email' => 'doctor@citycare.test', 'role' => 'doctor'],
            ['name' => 'CityCare Test Nurse', 'email' => 'nurse@citycare.test', 'role' => 'nurse'],
            ['name' => 'CityCare Test Laboratory', 'email' => 'laboratory@citycare.test', 'role' => 'laboratory'],
            ['name' => 'CityCare Test Pharmacy', 'email' => 'pharmacy@citycare.test', 'role' => 'pharmacy'],
            ['name' => 'CityCare Test Cashier', 'email' => 'cashier@citycare.test', 'role' => 'cashier'],
            ['name' => 'CityCare Test Records Officer', 'email' => 'records@citycare.test', 'role' => 'records'],
            ['name' => 'CityCare Test Inventory Officer', 'email' => 'inventory@citycare.test', 'role' => 'inventory'],
            ['name' => 'CityCare Test Patient', 'email' => 'patient@citycare.test', 'role' => 'patient', 'user_type' => 'patient'],
        ];

        foreach ($accounts as $account) {
            $role = Role::query()->where('slug', $account['role'])->firstOrFail();
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make($password),
                    'user_type' => $account['user_type'] ?? 'staff',
                    'is_active' => true,
                ],
            );

            $user->roles()->sync([$role->id]);
        }
    }
}
