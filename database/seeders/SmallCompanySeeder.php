<?php

namespace Database\Seeders;

use App\Enums\ContractStatus;
use App\Enums\DeploymentShiftType;
use App\Enums\EmploymentStatus;
use App\Enums\GuardClassification;
use App\Enums\GuardGender;
use App\Enums\OperationalStatus;
use App\Enums\RegionStatus;
use App\Enums\SiteStatus;
use App\Enums\SupervisorStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Region;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Supervisor;
use App\Models\User;
use App\Services\DeploymentService;
use App\Services\GuardService;
use App\Services\OrganizationService;
use App\Services\StaffService;
use App\Services\SupervisorGuardService;
use App\Support\Access\RolePermissionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Lean seed for a small security company — Kampala + Western footprint.
 */
class SmallCompanySeeder extends Seeder
{
    private const PASSWORD = 'Password@123';

    public function run(): void
    {
        $this->seedUsers();

        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first();
        if ($admin) {
            Auth::login($admin);
        }

        $organization = app(OrganizationService::class);

        $kampala = $this->seedRegion('KLA', 'Kampala', 'Amina Juma', '+256700100100', 'Primary operating area for the company.');
        $western = $this->seedRegion('WES', 'Western', 'Daniel Kiprotich', '+256700100200', 'Western region operations.');

        $klaSupervisor = $this->seedSupervisor(
            $kampala,
            $organization,
            'SUP0001',
            'James Kato',
            '+256710000001',
            'james.kato@platinumsecurity.local',
            'Field supervisor for Kampala sites.',
        );
        $wesSupervisor = $this->seedSupervisor(
            $western,
            $organization,
            'SUP0002',
            'Ruth Asiimwe',
            '+256710000002',
            'ruth.asiimwe@platinumsecurity.local',
            'Field supervisor for Western sites.',
        );

        $this->seedRegionSupervisorUser($klaSupervisor, $kampala, 'supervisor@platinumsecurity.local');
        $this->seedRegionSupervisorUser($wesSupervisor, $western, 'supervisor.western@platinumsecurity.local');

        $klaClients = $this->seedKampalaClients();
        $wesClients = $this->seedWesternClients();

        $klaSites = $this->seedKampalaSites($kampala, $klaSupervisor, $klaClients, $organization);
        $wesSites = $this->seedWesternSites($western, $wesSupervisor, $wesClients, $organization);

        $klaGuards = $this->seedGuards($kampala, $this->kampalaGuardDefs(), 'Kampala, Uganda', 1);
        $wesGuards = $this->seedGuards($western, $this->westernGuardDefs(), 'Mbarara, Uganda', 9);

        $this->seedStaff();
        $this->seedDeployments($klaSites, $klaGuards);
        $this->seedDeployments($wesSites, $wesGuards);

        Auth::logout();

        $this->command?->newLine();
        $this->command?->info('=== Small company seed ===');
        foreach ([
            'Users' => User::query()->count(),
            'Regions' => Region::query()->count(),
            'Supervisors' => Supervisor::query()->count(),
            'Clients' => Client::query()->count(),
            'Sites' => Site::query()->count(),
            'Staff' => Staff::query()->count(),
            'Guards' => Guard::query()->count(),
            'Active deployments' => Deployment::query()->current()->count(),
        ] as $label => $count) {
            $this->command?->info("{$label}: {$count}");
        }
        $this->command?->info('Login: admin@platinumsecurity.local / '.self::PASSWORD);
        $this->command?->info('Western supervisor: supervisor.western@platinumsecurity.local / '.self::PASSWORD);
    }

    private function seedUsers(): void
    {
        $users = [
            ['name' => 'System Administrator', 'email' => 'admin@platinumsecurity.local', 'role' => UserRole::SuperAdmin, 'phone' => '+256700000001'],
            ['name' => 'Operations Manager', 'email' => 'operations@platinumsecurity.local', 'role' => UserRole::OperationsManager, 'phone' => '+256700000002'],
            ['name' => 'HR Manager', 'email' => 'hr@platinumsecurity.local', 'role' => UserRole::HrManager, 'phone' => '+256700000003'],
            ['name' => 'Shift Manager', 'email' => 'shifts@platinumsecurity.local', 'role' => UserRole::ShiftManager, 'phone' => '+256700000004'],
            ['name' => 'Finance Manager', 'email' => 'finance@platinumsecurity.local', 'role' => UserRole::FinanceManager, 'phone' => '+256700000005'],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'phone' => $user['phone'],
                    'password' => Hash::make(self::PASSWORD),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }

        app(RolePermissionService::class)->mergeMissingPermissions();
    }

    private function seedRegion(string $code, string $name, string $manager, string $phone, string $description): Region
    {
        return Region::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'manager_name' => $manager,
                'manager_phone' => $phone,
                'description' => $description,
                'status' => RegionStatus::Active,
            ],
        );
    }

    private function seedSupervisor(
        Region $region,
        OrganizationService $organization,
        string $code,
        string $name,
        string $phone,
        string $email,
        string $notes,
    ): Supervisor {
        $supervisor = Supervisor::query()->updateOrCreate(
            ['supervisor_code' => $code],
            [
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'region_id' => $region->id,
                'status' => SupervisorStatus::Active,
                'assignment_date' => now()->subMonths(4)->toDateString(),
                'notes' => $notes,
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

        // Link Guard + Staff payroll profiles so the board shows a company employment ID (PSG…),
        // not only the internal SUP#### supervisor code.
        app(SupervisorGuardService::class)->ensureEmployeeProfiles($supervisor->fresh());

        return $supervisor->fresh(['guardProfile', 'staffProfile']);
    }

    private function seedRegionSupervisorUser(Supervisor $supervisor, Region $region, string $email): void
    {
        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Region Supervisor ('.$region->name.')',
                'role' => UserRole::RegionSupervisor,
                'supervisor_id' => $supervisor->id,
                'phone' => $supervisor->phone,
                'password' => Hash::make(self::PASSWORD),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }

    /** @return array{0: Client, 1: Client} */
    private function seedKampalaClients(): array
    {
        return [
            Client::query()->updateOrCreate(
                ['name' => 'Alpha Warehouse Ltd'],
                [
                    'contact_person' => 'Sarah Nambi',
                    'phone' => '+256750000001',
                    'email' => 'contact@alphawarehouse.local',
                    'address' => 'Industrial Area, Kampala',
                    'contract_start_date' => now()->subMonths(6)->toDateString(),
                    'contract_end_date' => now()->addMonths(6)->toDateString(),
                    'contract_status' => ContractStatus::Active,
                    'notes' => 'Warehouse security contract.',
                ],
            ),
            Client::query()->updateOrCreate(
                ['name' => 'Pearl Plaza Management'],
                [
                    'contact_person' => 'David Okello',
                    'phone' => '+256750000002',
                    'email' => 'security@pearlplaza.local',
                    'address' => 'Nakasero, Kampala',
                    'contract_start_date' => now()->subMonths(3)->toDateString(),
                    'contract_end_date' => now()->addMonths(9)->toDateString(),
                    'contract_status' => ContractStatus::Active,
                    'notes' => 'Retail plaza gate security.',
                ],
            ),
        ];
    }

    /** @return array{0: Client, 1: Client} */
    private function seedWesternClients(): array
    {
        return [
            Client::query()->updateOrCreate(
                ['name' => 'Mbarara Grain Stores'],
                [
                    'contact_person' => 'Paul Tumwine',
                    'phone' => '+256750000011',
                    'email' => 'ops@mbararagrain.local',
                    'address' => 'High Street, Mbarara',
                    'contract_start_date' => now()->subMonths(5)->toDateString(),
                    'contract_end_date' => now()->addMonths(7)->toDateString(),
                    'contract_status' => ContractStatus::Active,
                    'notes' => 'Western warehouse security.',
                ],
            ),
            Client::query()->updateOrCreate(
                ['name' => 'Fort Portal Clinic'],
                [
                    'contact_person' => 'Helen Atuhaire',
                    'phone' => '+256750000012',
                    'email' => 'admin@fpclinic.local',
                    'address' => 'Kamwenge Road, Fort Portal',
                    'contract_start_date' => now()->subMonths(2)->toDateString(),
                    'contract_end_date' => now()->addMonths(10)->toDateString(),
                    'contract_status' => ContractStatus::Active,
                    'notes' => 'Clinic gate security.',
                ],
            ),
        ];
    }

    /**
     * @param  array{0: Client, 1: Client}  $clients
     * @return array{0: Site, 1: Site}
     */
    private function seedKampalaSites(Region $region, Supervisor $supervisor, array $clients, OrganizationService $organization): array
    {
        return $this->seedSitePair($region, $supervisor, $organization, [
            [
                'code' => 'KLA-WH1',
                'name' => 'Alpha Warehouse Gate',
                'client' => $clients[0],
                'location' => 'Plot 12, Industrial Area, Kampala',
                'contact' => 'Sarah Nambi',
                'phone' => '+256750000001',
                'notes' => 'Main warehouse entrance.',
            ],
            [
                'code' => 'KLA-PZ1',
                'name' => 'Pearl Plaza Main Gate',
                'client' => $clients[1],
                'location' => 'Pearl Plaza, Nakasero, Kampala',
                'contact' => 'David Okello',
                'phone' => '+256750000002',
                'notes' => 'Plaza front gate.',
            ],
        ]);
    }

    /**
     * @param  array{0: Client, 1: Client}  $clients
     * @return array{0: Site, 1: Site}
     */
    private function seedWesternSites(Region $region, Supervisor $supervisor, array $clients, OrganizationService $organization): array
    {
        return $this->seedSitePair($region, $supervisor, $organization, [
            [
                'code' => 'WES-GS1',
                'name' => 'Mbarara Grain Stores Gate',
                'client' => $clients[0],
                'location' => 'High Street, Mbarara',
                'contact' => 'Paul Tumwine',
                'phone' => '+256750000011',
                'notes' => 'Grain store main gate.',
            ],
            [
                'code' => 'WES-CL1',
                'name' => 'Fort Portal Clinic Gate',
                'client' => $clients[1],
                'location' => 'Kamwenge Road, Fort Portal',
                'contact' => 'Helen Atuhaire',
                'phone' => '+256750000012',
                'notes' => 'Clinic entrance.',
            ],
        ]);
    }

    /**
     * @param  list<array{code: string, name: string, client: Client, location: string, contact: string, phone: string, notes: string}>  $defs
     * @return array{0: Site, 1: Site}
     */
    private function seedSitePair(Region $region, Supervisor $supervisor, OrganizationService $organization, array $defs): array
    {
        $sites = [];

        foreach ($defs as $def) {
            $site = Site::query()->updateOrCreate(
                ['code' => $def['code']],
                [
                    'name' => $def['name'],
                    'client_id' => $def['client']->id,
                    'region_id' => $region->id,
                    'supervisor_id' => $supervisor->id,
                    'physical_location' => $def['location'],
                    'site_contact_person' => $def['contact'],
                    'site_contact_phone' => $def['phone'],
                    'contract_start_date' => now()->subMonths(4)->toDateString(),
                    'contract_end_date' => now()->addMonths(8)->toDateString(),
                    'required_guards' => 4,
                    'required_day_guards' => 2,
                    'required_day_armed_guards' => 0,
                    'required_day_unarmed_guards' => 2,
                    'required_night_guards' => 2,
                    'required_night_armed_guards' => 0,
                    'required_night_unarmed_guards' => 2,
                    'number_of_posts' => 2,
                    'status' => SiteStatus::Active,
                    'notes' => $def['notes'],
                ],
            );

            if ($site->manpowerRequirements()->where('is_current', true)->doesntExist()) {
                $organization->syncSiteManpower($site, 'Seeded manpower requirement');
            }

            $sites[] = $site;
        }

        return [$sites[0], $sites[1]];
    }

    /**
     * @return list<array{employment_id: string, first_name: string, last_name: string, gender: GuardGender}>
     */
    private function kampalaGuardDefs(): array
    {
        return [
            ['employment_id' => 'PSG0001', 'first_name' => 'Musa', 'last_name' => 'Kakooza', 'gender' => GuardGender::Male],
            ['employment_id' => 'PSG0002', 'first_name' => 'Esther', 'last_name' => 'Nakato', 'gender' => GuardGender::Female],
            ['employment_id' => 'PSG0003', 'first_name' => 'Brian', 'last_name' => 'Ssempala', 'gender' => GuardGender::Male],
            ['employment_id' => 'PSG0004', 'first_name' => 'Irene', 'last_name' => 'Achieng', 'gender' => GuardGender::Female],
            ['employment_id' => 'PSG0005', 'first_name' => 'Peter', 'last_name' => 'Okello', 'gender' => GuardGender::Male],
            ['employment_id' => 'PSG0006', 'first_name' => 'Grace', 'last_name' => 'Nabwire', 'gender' => GuardGender::Female],
            ['employment_id' => 'PSG0007', 'first_name' => 'John', 'last_name' => 'Mugisha', 'gender' => GuardGender::Male],
            ['employment_id' => 'PSG0008', 'first_name' => 'Mary', 'last_name' => 'Nalubega', 'gender' => GuardGender::Female],
        ];
    }

    /**
     * @return list<array{employment_id: string, first_name: string, last_name: string, gender: GuardGender}>
     */
    private function westernGuardDefs(): array
    {
        return [
            ['employment_id' => 'PSG0009', 'first_name' => 'Samuel', 'last_name' => 'Turyamureeba', 'gender' => GuardGender::Male],
            ['employment_id' => 'PSG0010', 'first_name' => 'Peace', 'last_name' => 'Kyomuhendo', 'gender' => GuardGender::Female],
            ['employment_id' => 'PSG0011', 'first_name' => 'Robert', 'last_name' => 'Bwambale', 'gender' => GuardGender::Male],
            ['employment_id' => 'PSG0012', 'first_name' => 'Scovia', 'last_name' => 'Akello', 'gender' => GuardGender::Female],
            ['employment_id' => 'PSG0013', 'first_name' => 'Denis', 'last_name' => 'Muhwezi', 'gender' => GuardGender::Male],
            ['employment_id' => 'PSG0014', 'first_name' => 'Janet', 'last_name' => 'Katusiime', 'gender' => GuardGender::Female],
            ['employment_id' => 'PSG0015', 'first_name' => 'Francis', 'last_name' => 'Byaruhanga', 'gender' => GuardGender::Male],
            ['employment_id' => 'PSG0016', 'first_name' => 'Doreen', 'last_name' => 'Ninsiima', 'gender' => GuardGender::Female],
        ];
    }

    /**
     * @param  list<array{employment_id: string, first_name: string, last_name: string, gender: GuardGender}>  $defs
     * @return list<Guard>
     */
    private function seedGuards(Region $region, array $defs, string $address, int $phoneStartIndex): array
    {
        $service = app(GuardService::class);
        $guards = [];

        foreach ($defs as $offset => $def) {
            $existing = Guard::query()->where('employment_id', $def['employment_id'])->first();
            if ($existing) {
                $guards[] = $existing;

                continue;
            }

            $phoneIndex = $phoneStartIndex + $offset;

            $guards[] = $service->createGuard([
                'employment_id' => $def['employment_id'],
                'first_name' => $def['first_name'],
                'last_name' => $def['last_name'],
                'region_id' => $region->id,
                'gender' => $def['gender']->value,
                'phone' => '+25670010'.str_pad((string) $phoneIndex, 4, '0', STR_PAD_LEFT),
                'date_employed' => now()->subMonths(2 + ($offset % 3))->toDateString(),
                'employment_status' => EmploymentStatus::Active->value,
                'operational_status' => OperationalStatus::AwaitingDeployment->value,
                'rank_designation' => 'Security Guard',
                'guard_classification' => GuardClassification::Unarmed->value,
                'address' => $address,
            ]);
        }

        return $guards;
    }

    private function seedStaff(): void
    {
        $service = app(StaffService::class);

        $members = [
            ['first_name' => 'Mwanje', 'last_name' => 'Jonah', 'job_title' => 'Finance Officer', 'department' => 'Finance', 'monthly_salary' => 900000],
            ['first_name' => 'Atim', 'last_name' => 'Joan', 'job_title' => 'Admin Assistant', 'department' => 'Administration', 'monthly_salary' => 700000],
            ['first_name' => 'Ssekandi', 'last_name' => 'Brian', 'job_title' => 'Operations Clerk', 'department' => 'Operations', 'monthly_salary' => 750000],
        ];

        foreach ($members as $index => $member) {
            $exists = Staff::query()
                ->where('first_name', $member['first_name'])
                ->where('last_name', $member['last_name'])
                ->exists();

            if ($exists) {
                continue;
            }

            $service->createStaff([
                ...$member,
                'employment_id' => $service->nextEmploymentId(),
                'phone' => '07'.str_pad((string) (51000000 + $index), 8, '0', STR_PAD_LEFT),
                'email' => strtolower($member['first_name']).'.'.strtolower($member['last_name']).'@platinumsecurity.local',
                'bank_name' => 'Centenary Bank',
                'bank_account' => '32'.str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT),
                'date_employed' => now()->subMonths($index + 2)->startOfMonth()->toDateString(),
                'employment_status' => EmploymentStatus::Active->value,
            ]);
        }
    }

    /**
     * @param  array{0: Site, 1: Site}  $sites
     * @param  list<Guard>  $guards
     */
    private function seedDeployments(array $sites, array $guards): void
    {
        if (count($guards) < 6) {
            return;
        }

        [$siteA, $siteB] = $sites;
        $deployments = app(DeploymentService::class);
        $start = now()->toDateString();

        $plan = [
            [$guards[0], $siteA, DeploymentShiftType::Day],
            [$guards[1], $siteA, DeploymentShiftType::Day],
            [$guards[2], $siteA, DeploymentShiftType::Night],
            [$guards[3], $siteB, DeploymentShiftType::Day],
            [$guards[4], $siteB, DeploymentShiftType::Night],
            [$guards[5], $siteB, DeploymentShiftType::Night],
        ];

        foreach ($plan as [$guard, $site, $shiftType]) {
            $already = Deployment::query()
                ->current()
                ->where('guard_id', $guard->id)
                ->exists();

            if ($already) {
                continue;
            }

            $deployments->deploy([
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'shift_type' => $shiftType->value,
                'start_date' => $start,
                'notes' => 'Small company seed deployment.',
            ]);
        }
    }
}
