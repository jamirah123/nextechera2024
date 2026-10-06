<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Access\RolePermissionService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private const PASSWORD = 'Password@123';

    public function run(): void
    {
        if (app()->environment('production') && ! filter_var(config('psg.seed.allow_production', false), FILTER_VALIDATE_BOOL)) {
            $this->command?->warn('Production refused the seeder. User accounts were left unchanged.');

            return;
        }

        $users = [
            ['name' => 'Grace Namuli', 'email' => 'admin@platinumsecurity.local', 'role' => UserRole::SuperAdmin],
            ['name' => 'David Okello', 'email' => 'md@platinumsecurity.local', 'role' => UserRole::ManagingDirector],
            ['name' => 'Amina Juma', 'email' => 'operations@platinumsecurity.local', 'role' => UserRole::OperationsManager],
            ['name' => 'Sarah Nalwoga', 'email' => 'hr@platinumsecurity.local', 'role' => UserRole::HrManager],
            ['name' => 'Alex Smith', 'email' => 'shifts@platinumsecurity.local', 'role' => UserRole::ShiftManager],
            ['name' => 'Peter Mugisha', 'email' => 'finance@platinumsecurity.local', 'role' => UserRole::FinanceManager],
            ['name' => 'Joan Akello', 'email' => 'procurement@platinumsecurity.local', 'role' => UserRole::ProcurementOfficer],
        ];

        foreach ($users as $index => $user) {
            if (User::query()->where('email', $user['email'])->exists()) {
                continue;
            }

            $account = User::query()->create([
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'phone' => '+256700000'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'password' => self::PASSWORD,
                'is_active' => true,
            ]);
            $account->forceFill(['email_verified_at' => now()])->save();
        }

        app(RolePermissionService::class)->mergeMissingPermissions();

        $mode = strtolower(trim((string) config('psg.seed.mode', 'off')));
        if ($mode === 'load') {
            $this->call(LargeCompanySeeder::class);
        }
    }
}
