<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'System Administrator',
                'email' => 'admin@platinumsecurity.local',
                'role' => UserRole::SuperAdmin,
                'phone' => '+255700000001',
            ],
            [
                'name' => 'Operations Manager',
                'email' => 'operations@platinumsecurity.local',
                'role' => UserRole::OperationsManager,
                'phone' => '+255700000002',
            ],
            [
                'name' => 'HR Manager',
                'email' => 'hr@platinumsecurity.local',
                'role' => UserRole::HrManager,
                'phone' => '+255700000003',
            ],
            [
                'name' => 'Shift Manager',
                'email' => 'shifts@platinumsecurity.local',
                'role' => UserRole::ShiftManager,
                'phone' => '+255700000004',
            ],
            [
                'name' => 'Finance Manager',
                'email' => 'finance@platinumsecurity.local',
                'role' => UserRole::FinanceManager,
                'phone' => '+255700000005',
            ],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'phone' => $user['phone'],
                    'password' => Hash::make('Password@123'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
