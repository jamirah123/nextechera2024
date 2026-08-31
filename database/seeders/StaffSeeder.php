<?php

namespace Database\Seeders;

use App\Enums\EmploymentStatus;
use App\Models\Staff;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first()
            ?? User::query()->first();

        if ($admin) {
            Auth::login($admin);
        }

        $service = app(StaffService::class);

        foreach ($this->staffMembers() as $data) {
            if (Staff::query()->where('employment_id', $data['employment_id'])->exists()) {
                continue;
            }

            $service->createStaff($data);
        }

        Auth::logout();

        $this->command?->info('Staff members: '.Staff::query()->count());
    }

    /** @return list<array<string, mixed>> */
    private function staffMembers(): array
    {
        return [
            [
                'employment_id' => 'STF0001',
                'first_name' => 'Mwanje',
                'last_name' => 'Jonah',
                'job_title' => 'Finance Manager',
                'department' => 'Finance',
                'region_id' => null,
                'phone' => '0750083154',
                'email' => 'mwanje.jonah@platinumsecurity.local',
                'monthly_salary' => 1000000,
                'bank_name' => 'Centenary Bank',
                'bank_account' => '3200220022',
                'nssf_number' => '234567890987654',
                'tin_number' => '1000123456',
                'date_employed' => now()->startOfMonth()->toDateString(),
                'employment_status' => EmploymentStatus::Active->value,
                'notes' => 'Seeded finance manager.',
            ],
            [
                'employment_id' => 'STF0002',
                'first_name' => 'Nabwire',
                'last_name' => 'Grace',
                'job_title' => 'HR Assistant',
                'department' => 'Human Resources',
                'region_id' => null,
                'phone' => '0772123456',
                'email' => 'grace.nabwire@platinumsecurity.local',
                'monthly_salary' => 850000,
                'bank_name' => 'Stanbic Bank',
                'bank_account' => '9030012345678',
                'nssf_number' => '123456789012345',
                'date_employed' => now()->subMonths(8)->startOfMonth()->toDateString(),
                'employment_status' => EmploymentStatus::Active->value,
            ],
            [
                'employment_id' => 'STF0003',
                'first_name' => 'Okello',
                'last_name' => 'Peter',
                'job_title' => 'Finance Officer',
                'department' => 'Finance',
                'region_id' => null,
                'phone' => '0783456789',
                'email' => 'peter.okello@platinumsecurity.local',
                'monthly_salary' => 1200000,
                'bank_name' => 'DFCU Bank',
                'bank_account' => '0123456789',
                'nssf_number' => '987654321098765',
                'tin_number' => '1000789012',
                'date_employed' => now()->subYear()->startOfMonth()->toDateString(),
                'employment_status' => EmploymentStatus::Active->value,
            ],
            [
                'employment_id' => 'STF0004',
                'first_name' => 'Achieng',
                'last_name' => 'Sarah',
                'job_title' => 'Admin Officer',
                'department' => 'Administration',
                'region_id' => null,
                'phone' => '0700112233',
                'email' => 'sarah.achieng@platinumsecurity.local',
                'monthly_salary' => 900000,
                'bank_name' => 'Centenary Bank',
                'bank_account' => '4500112233',
                'date_employed' => now()->subMonths(14)->startOfMonth()->toDateString(),
                'employment_status' => EmploymentStatus::Active->value,
            ],
            [
                'employment_id' => 'STF0005',
                'first_name' => 'Mugisha',
                'last_name' => 'David',
                'job_title' => 'Operations Clerk',
                'department' => 'Operations',
                'region_id' => null,
                'phone' => '0765432109',
                'email' => 'david.mugisha@platinumsecurity.local',
                'monthly_salary' => 750000,
                'bank_name' => 'Equity Bank',
                'bank_account' => '5566778899',
                'nssf_number' => '456789012345678',
                'date_employed' => now()->subMonths(3)->startOfMonth()->toDateString(),
                'employment_status' => EmploymentStatus::Active->value,
            ],
        ];
    }
}
