<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CityCareAccessSeeder::class,
            CityCareOrganizationSeeder::class,
        ]);

        $email = env('CITYCARE_ADMIN_EMAIL');
        $password = env('CITYCARE_ADMIN_PASSWORD');

        if (blank($email) && blank($password)) {
            return;
        }

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['CITYCARE_ADMIN_EMAIL' => 'Set a valid administrator email in the local .env file.']);
        }

        if (! is_string($password) || strlen($password) < 12) {
            throw ValidationException::withMessages(['CITYCARE_ADMIN_PASSWORD' => 'Set an administrator password of at least 12 characters in the local .env file.']);
        }

        $admin = User::updateOrCreate(
            ['email' => strtolower(trim($email))],
            [
                'name' => 'CityCare Administrator',
                'password' => Hash::make($password),
                'user_type' => 'staff',
                'is_active' => true,
            ],
        );

        $role = Role::query()->where('slug', 'super-admin')->firstOrFail();
        $admin->roles()->syncWithoutDetaching([$role->id]);
    }
}
