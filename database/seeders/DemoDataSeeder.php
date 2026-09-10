<?php

namespace Database\Seeders;

use App\Enums\ContractStatus;
use App\Enums\EmploymentStatus;
use App\Enums\GuardClassification;
use App\Enums\GuardGender;
use App\Enums\OperationalStatus;
use App\Enums\RegionStatus;
use App\Enums\SiteStatus;
use App\Enums\SupervisorStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Guard;
use App\Models\Region;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Supervisor;
use App\Models\User;
use App\Services\GuardService;
use App\Services\OrganizationService;
use App\Services\StaffService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUsers();

        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first();
        if ($admin) {
            Auth::login($admin);
        }

        $organization = app(OrganizationService::class);
        $regions = $this->seedRegions();
        $supervisors = $this->seedSupervisors($regions, $organization);
        $this->seedRegionSupervisorUsers($supervisors, $regions);
        $clients = $this->seedClients();
        $this->seedSites($regions, $supervisors, $clients, $organization);
        $this->seedGuards($regions);
        $this->seedStaff();

        Auth::logout();

        $this->command?->newLine();
        $this->command?->info('=== Demo seed summary ===');
        foreach ([
            'Users' => User::query()->count(),
            'Regions' => Region::query()->count(),
            'Supervisors' => Supervisor::query()->count(),
            'Clients' => Client::query()->count(),
            'Sites' => Site::query()->count(),
            'Staff' => Staff::query()->count(),
            'Guards' => Guard::query()->count(),
        ] as $label => $count) {
            $this->command?->info("{$label}: {$count}");
        }
        $this->command?->info('Login: admin@platinumsecurity.local / Password@123');
    }

    private function seedUsers(): void
    {
        $users = [
            ['name' => 'System Administrator', 'email' => 'admin@platinumsecurity.local', 'role' => UserRole::SuperAdmin, 'phone' => '+256700000001'],
            ['name' => 'Operations Manager', 'email' => 'operations@platinumsecurity.local', 'role' => UserRole::OperationsManager, 'phone' => '+256700000002'],
            ['name' => 'HR Manager', 'email' => 'hr@platinumsecurity.local', 'role' => UserRole::HrManager, 'phone' => '+256700000003'],
            ['name' => 'Shift Manager', 'email' => 'shifts@platinumsecurity.local', 'role' => UserRole::ShiftManager, 'phone' => '+256700000004'],
            ['name' => 'Finance Manager', 'email' => 'finance@platinumsecurity.local', 'role' => UserRole::FinanceManager, 'phone' => '+256700000005'],
            ['name' => 'Managing Director', 'email' => 'md@platinumsecurity.local', 'role' => UserRole::ManagingDirector, 'phone' => '+256700000006'],
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

    /** @return \Illuminate\Support\Collection<int, Region> */
    private function seedRegions()
    {
        $defs = [
            ['name' => 'Central', 'code' => 'CEN', 'manager_name' => 'Amina Juma'],
            ['name' => 'Eastern', 'code' => 'EAS', 'manager_name' => 'Joseph Mwangi'],
            ['name' => 'Northern', 'code' => 'NOR', 'manager_name' => 'Grace Okello'],
            ['name' => 'Western', 'code' => 'WES', 'manager_name' => 'Daniel Kiprotich'],
        ];

        return collect($defs)->map(function (array $data) {
            return Region::query()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'manager_name' => $data['manager_name'],
                    'manager_phone' => '+256700'.fake()->numerify('######'),
                    'description' => $data['name'].' operational region.',
                    'status' => RegionStatus::Active,
                ],
            );
        })->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Region>  $regions
     * @return \Illuminate\Support\Collection<int, Supervisor>
     */
    private function seedSupervisors($regions, OrganizationService $organization)
    {
        $names = [
            'CEN' => 'James Kato',
            'EAS' => 'Mary Namuli',
            'NOR' => 'Paul Okot',
            'WES' => 'Ruth Asiimwe',
        ];

        return $regions->values()->map(function (Region $region, int $index) use ($names, $organization) {
            $code = 'SUP'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
            $supervisor = Supervisor::query()->updateOrCreate(
                ['supervisor_code' => $code],
                [
                    'name' => $names[$region->code] ?? fake()->name(),
                    'phone' => '+25671'.fake()->numerify('#######'),
                    'email' => 'field.supervisor'.($index + 1).'@platinumsecurity.local',
                    'region_id' => $region->id,
                    'status' => SupervisorStatus::Active,
                    'assignment_date' => now()->subMonths(6)->toDateString(),
                    'notes' => 'Primary supervisor for '.$region->name.'.',
                ],
            );

            if ($supervisor->assignmentHistories()->doesntExist()) {
                $organization->recordSupervisorAssignment(
                    $supervisor,
                    null,
                    (int) $region->id,
                    'initial_assignment',
                    'Seeded assignment',
                    'Initial regional assignment.',
                );
            }

            return $supervisor;
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Supervisor>  $supervisors
     * @param  \Illuminate\Support\Collection<int, Region>  $regions
     */
    private function seedRegionSupervisorUsers($supervisors, $regions): void
    {
        foreach ($supervisors as $supervisor) {
            $region = $regions->firstWhere('id', $supervisor->region_id);
            if (! $region) {
                continue;
            }

            $slug = strtolower($region->code);
            User::query()->updateOrCreate(
                ['email' => "supervisor.{$slug}@platinumsecurity.local"],
                [
                    'name' => 'Region Supervisor ('.$region->name.')',
                    'role' => UserRole::RegionSupervisor,
                    'supervisor_id' => $supervisor->id,
                    'phone' => $supervisor->phone,
                    'password' => Hash::make('Password@123'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }
    }

    /** @return \Illuminate\Support\Collection<int, Client> */
    private function seedClients()
    {
        $names = [
            'ABC Logistics Ltd',
            'Summit Industries',
            'Harbor Retail Group',
            'Nile Agro Processors',
            'Pearl Bank HQ',
            'Victoria Malls',
            'Lakeview Estates',
            'Kampala Medical Centre',
        ];

        return collect($names)->values()->map(function (string $name, int $index) {
            $slug = 'cli'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);

            return Client::query()->updateOrCreate(
                ['name' => $name],
                [
                    'contact_person' => fake()->name(),
                    'phone' => '+25675'.fake()->numerify('#######'),
                    'email' => $slug.'@client.local',
                    'address' => fake()->streetAddress().', Uganda',
                    'contract_start_date' => now()->subMonths(12)->toDateString(),
                    'contract_end_date' => now()->addMonths(12)->toDateString(),
                    'contract_status' => ContractStatus::Active,
                    'notes' => 'Active security services contract.',
                ],
            );
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Region>  $regions
     * @param  \Illuminate\Support\Collection<int, Supervisor>  $supervisors
     * @param  \Illuminate\Support\Collection<int, Client>  $clients
     */
    private function seedSites($regions, $supervisors, $clients, OrganizationService $organization): void
    {
        $siteTypes = ['Gate', 'Warehouse', 'Depot', 'Mall'];
        $siteCounter = 0;

        foreach ($regions->values() as $regionIndex => $region) {
            $supervisor = $supervisors->firstWhere('region_id', $region->id);
            if (! $supervisor) {
                continue;
            }

            for ($s = 0; $s < 4; $s++) {
                $siteCounter++;
                $client = $clients[($siteCounter - 1) % $clients->count()];
                $dayUnarmed = 2;
                $dayArmed = 0;
                $nightUnarmed = 2;
                $nightArmed = 0;
                $day = $dayArmed + $dayUnarmed;
                $night = $nightArmed + $nightUnarmed;
                $code = $region->code.'-S'.str_pad((string) ($s + 1), 2, '0', STR_PAD_LEFT);

                $site = Site::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $client->name.' '.$siteTypes[$s].' '.$region->code,
                        'client_id' => $client->id,
                        'region_id' => $region->id,
                        'supervisor_id' => $supervisor->id,
                        'physical_location' => fake()->streetAddress().', '.$region->name,
                        'site_contact_person' => fake()->name(),
                        'site_contact_phone' => '+25676'.fake()->numerify('#######'),
                        'contract_start_date' => now()->subMonths(6)->toDateString(),
                        'contract_end_date' => now()->addMonths(12)->toDateString(),
                        'required_guards' => $day + $night,
                        'required_day_guards' => $day,
                        'required_day_armed_guards' => $dayArmed,
                        'required_day_unarmed_guards' => $dayUnarmed,
                        'required_night_guards' => $night,
                        'required_night_armed_guards' => $nightArmed,
                        'required_night_unarmed_guards' => $nightUnarmed,
                        'number_of_posts' => max($day, $night),
                        'status' => SiteStatus::Active,
                        'notes' => 'Demo seeded site.',
                    ],
                );

                if ($site->manpowerRequirements()->where('is_current', true)->doesntExist()) {
                    $organization->syncSiteManpower($site, 'Seeded manpower requirement');
                }
            }
        }
    }

    private function seedStaff(): void
    {
        $service = app(StaffService::class);

        $members = [
            ['first_name' => 'Mwanje', 'last_name' => 'Jonah', 'job_title' => 'Finance Manager', 'department' => 'Finance', 'monthly_salary' => 1000000],
            ['first_name' => 'Nabwire', 'last_name' => 'Grace', 'job_title' => 'HR Assistant', 'department' => 'Human Resources', 'monthly_salary' => 850000],
            ['first_name' => 'Okello', 'last_name' => 'Peter', 'job_title' => 'Finance Officer', 'department' => 'Finance', 'monthly_salary' => 1200000],
            ['first_name' => 'Achieng', 'last_name' => 'Sarah', 'job_title' => 'Admin Officer', 'department' => 'Administration', 'monthly_salary' => 900000],
            ['first_name' => 'Mugisha', 'last_name' => 'David', 'job_title' => 'Operations Clerk', 'department' => 'Operations', 'monthly_salary' => 750000],
            ['first_name' => 'Nalubega', 'last_name' => 'Irene', 'job_title' => 'Payroll Officer', 'department' => 'Finance', 'monthly_salary' => 950000],
            ['first_name' => 'Ssekandi', 'last_name' => 'Brian', 'job_title' => 'Logistics Officer', 'department' => 'Operations', 'monthly_salary' => 800000],
            ['first_name' => 'Atim', 'last_name' => 'Joan', 'job_title' => 'Receptionist', 'department' => 'Administration', 'monthly_salary' => 650000],
        ];

        foreach ($members as $index => $member) {
            $alreadySeeded = Staff::query()
                ->where('first_name', $member['first_name'])
                ->where('last_name', $member['last_name'])
                ->exists();

            if ($alreadySeeded) {
                continue;
            }

            $service->createStaff([
                ...$member,
                'employment_id' => $service->nextEmploymentId(),
                'phone' => '07'.str_pad((string) (50000000 + $index), 8, '0', STR_PAD_LEFT),
                'email' => strtolower($member['first_name']).'.'.strtolower($member['last_name']).'@platinumsecurity.local',
                'bank_name' => 'Centenary Bank',
                'bank_account' => '32'.str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT),
                'date_employed' => now()->subMonths($index + 1)->startOfMonth()->toDateString(),
                'employment_status' => EmploymentStatus::Active->value,
            ]);
        }
    }

    /** @param  \Illuminate\Support\Collection<int, Region>  $regions */
    private function seedGuards($regions): void
    {
        $service = app(GuardService::class);
        $central = $regions->firstWhere('code', 'CEN') ?? $regions->first();
        $eastern = $regions->firstWhere('code', 'EAS') ?? $regions->skip(1)->first();

        $guards = [
            [
                'employment_id' => 'PSG0001',
                'first_name' => 'Musa',
                'last_name' => 'Kakooza',
                'region_id' => $central?->id,
            ],
            [
                'employment_id' => 'PSG0002',
                'first_name' => 'Esther',
                'last_name' => 'Nakato',
                'region_id' => $eastern?->id,
            ],
        ];

        foreach ($guards as $index => $data) {
            if (Guard::query()->where('employment_id', $data['employment_id'])->exists()) {
                continue;
            }

            $service->createGuard([
                ...$data,
                'gender' => $index === 0 ? GuardGender::Male->value : GuardGender::Female->value,
                'phone' => '+25670000'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'date_employed' => now()->subMonths(3)->toDateString(),
                'employment_status' => EmploymentStatus::Active->value,
                'operational_status' => OperationalStatus::AwaitingDeployment->value,
                'rank_designation' => 'Security Guard',
                'guard_classification' => GuardClassification::Unarmed->value,
                'address' => 'Kampala, Uganda',
            ]);
        }
    }
}
