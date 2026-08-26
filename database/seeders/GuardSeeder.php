<?php

namespace Database\Seeders;

use App\Enums\EmploymentStatus;
use App\Enums\GuardGender;
use App\Enums\OperationalStatus;
use App\Models\Region;
use App\Models\User;
use App\Services\GuardService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class GuardSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first()
            ?? User::query()->first();

        if ($admin) {
            Auth::login($admin);
        }

        $service = app(GuardService::class);
        $regions = Region::query()->orderBy('id')->get();

        if ($regions->isEmpty()) {
            return;
        }

        $samples = [
            [
                'first_name' => 'John',
                'middle_name' => 'Kamau',
                'last_name' => 'Mwangi',
                'gender' => GuardGender::Male->value,
                'phone' => '+254712000001',
                'national_id' => '28451234',
                'rank_designation' => 'Security Guard',
                'employment_status' => EmploymentStatus::Active->value,
                'operational_status' => OperationalStatus::AwaitingDeployment->value,
                'emergency_contact_name' => 'Mary Mwangi',
                'emergency_contact_phone' => '+254712000011',
            ],
            [
                'first_name' => 'Grace',
                'middle_name' => null,
                'last_name' => 'Achieng',
                'gender' => GuardGender::Female->value,
                'phone' => '+254712000002',
                'national_id' => '30124567',
                'rank_designation' => 'Senior Guard',
                'employment_status' => EmploymentStatus::Active->value,
                'operational_status' => OperationalStatus::OnLeave->value,
                'emergency_contact_name' => 'Peter Achieng',
                'emergency_contact_phone' => '+254712000012',
            ],
            [
                'first_name' => 'Samuel',
                'middle_name' => 'Otieno',
                'last_name' => 'Okello',
                'gender' => GuardGender::Male->value,
                'phone' => '+254712000003',
                'national_id' => '27563412',
                'rank_designation' => 'Security Guard',
                'employment_status' => EmploymentStatus::Active->value,
                'operational_status' => OperationalStatus::Absent->value,
            ],
            [
                'first_name' => 'Faith',
                'middle_name' => 'Wanjiru',
                'last_name' => 'Njeri',
                'gender' => GuardGender::Female->value,
                'phone' => '+254712000004',
                'national_id' => '31245890',
                'rank_designation' => 'Team Leader',
                'employment_status' => EmploymentStatus::Active->value,
                'operational_status' => OperationalStatus::OffDuty->value,
            ],
            [
                'first_name' => 'Daniel',
                'middle_name' => null,
                'last_name' => 'Kiptoo',
                'gender' => GuardGender::Male->value,
                'phone' => '+254712000005',
                'national_id' => '26890123',
                'rank_designation' => 'Security Guard',
                'employment_status' => EmploymentStatus::Suspended->value,
                'operational_status' => OperationalStatus::Deserted->value,
            ],
            [
                'first_name' => 'Amina',
                'middle_name' => 'Hassan',
                'last_name' => 'Ali',
                'gender' => GuardGender::Female->value,
                'phone' => '+254712000006',
                'national_id' => '29567801',
                'rank_designation' => 'Security Guard',
                'employment_status' => EmploymentStatus::Active->value,
                'operational_status' => OperationalStatus::Training->value,
            ],
        ];

        foreach ($samples as $index => $sample) {
            $employmentId = 'PSG'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);

            if (\App\Models\Guard::withTrashed()->where('employment_id', $employmentId)->exists()) {
                continue;
            }

            $region = $regions[$index % $regions->count()];

            $service->createGuard([
                ...$sample,
                'employment_id' => $employmentId,
                'region_id' => $region->id,
                'date_employed' => now()->subMonths(rand(1, 24))->toDateString(),
                'date_of_birth' => now()->subYears(rand(24, 45))->toDateString(),
                'address' => 'Nairobi Metropolitan Area',
            ]);
        }

        Auth::logout();
    }
}
