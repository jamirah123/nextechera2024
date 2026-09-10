<?php

namespace Database\Seeders;

use App\Enums\AbsenceReason;
use App\Enums\AssetCategory;
use App\Enums\AssetIssuanceType;
use App\Enums\BillingMode;
use App\Enums\CompensationType;
use App\Enums\ContractStatus;
use App\Enums\DeploymentShiftType;
use App\Enums\EmployeeType;
use App\Enums\EmploymentStatus;
use App\Enums\GuardClassification;
use App\Enums\GuardGender;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentType;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\OperationalStatus;
use App\Enums\PaymentMethod;
use App\Enums\RegionStatus;
use App\Enums\ReplacementReason;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\SiteStatus;
use App\Enums\SupervisorStatus;
use App\Models\Client;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Incident;
use App\Models\Invoice;
use App\Models\Leave;
use App\Models\Region;
use App\Models\Shift;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Supervisor;
use App\Models\User;
use App\Services\AbsenceService;
use App\Services\DeploymentService;
use App\Services\Finance\BillingService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\PayrollCalculationService;
use App\Services\Finance\PayrollRunService;
use App\Services\GuardAssetService;
use App\Services\GuardService;
use App\Services\LeaveService;
use App\Services\Operations\IncidentService;
use App\Services\OrganizationService;
use App\Services\ReplacementService;
use App\Services\ShiftService;
use App\Services\StaffService;
use App\Services\SupervisorGuardService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Large, rule-safe Platinum Security demo volume.
 *
 * Builds a connected operational graph: org → people → deployments → shifts →
 * HR events → incidents → billing/payroll — using domain services so capacity,
 * employment IDs, and conflict rules stay intact.
 */
class WorkflowVolumeSeeder extends Seeder
{
    private const TARGET_REGIONS = 8;

    private const TARGET_CLIENTS = 90;

    private const TARGET_SITES = 120;

    private const TARGET_SUPERVISORS = 32;

    private const TARGET_OFFICE_STAFF = 100;

    private const TARGET_GUARDS = 600;

    private const DUTY_HISTORY_DAYS = 5;

    private const TARGET_LEAVES = 180;

    private const TARGET_ABSENCES = 90;

    private const TARGET_REPLACEMENTS = 45;

    private const TARGET_INCIDENTS = 120;

    private const TARGET_INVOICES = 80;

    private const TARGET_ASSET_ISSUANCES = 100;

    /** @var list<string> */
    private array $firstNames = [
        'James', 'Mary', 'Paul', 'Ruth', 'David', 'Grace', 'Peter', 'Sarah', 'Joseph', 'Amina',
        'Daniel', 'Irene', 'Moses', 'Joan', 'Brian', 'Esther', 'Samuel', 'Faith', 'Isaac', 'Mercy',
        'Emmanuel', 'Prossy', 'Ronald', 'Annet', 'Fred', 'Christine', 'Ivan', 'Betty', 'Alex', 'Jackie',
        'Musa', 'Nakato', 'Okello', 'Atim', 'Kato', 'Namuli', 'Mugisha', 'Nalubega', 'Ssekandi', 'Achieng',
    ];

    /** @var list<string> */
    private array $lastNames = [
        'Okello', 'Namuli', 'Kato', 'Asiimwe', 'Mugisha', 'Nabwire', 'Ssekandi', 'Atim', 'Kakooza', 'Nakato',
        'Ochieng', 'Wanyama', 'Byaruhanga', 'Tumusiime', 'Kizza', 'Nansubuga', 'Opio', 'Auma', 'Ssali', 'Kyeyune',
        'Muwonge', 'Nakitende', 'Owor', 'Akello', 'Bbosa', 'Nalwanga', 'Okot', 'Among', 'Lubega', 'Nambi',
    ];

    /** @var list<string> */
    private array $clientPrefixes = [
        'Nile', 'Pearl', 'Victoria', 'Kampala', 'Entebbe', 'Jinja', 'Gulu', 'Mbarara', 'Mbale', 'Fort Portal',
        'Summit', 'Harbor', 'Lakeview', 'Royal', 'Prime', 'Unity', 'Horizon', 'Eagle', 'Crest', 'Atlas',
    ];

    /** @var list<string> */
    private array $clientSuffixes = [
        'Logistics Ltd', 'Industries', 'Retail Group', 'Agro Processors', 'Bank Branch', 'Mall',
        'Estates', 'Medical Centre', 'Warehousing Co', 'Energy Ltd', 'Construction Ltd', 'Holdings',
    ];

    public function run(): void
    {
        if (Guard::query()->whereDoesntHave('supervisorProfile')->count() >= self::TARGET_GUARDS) {
            $this->command?->warn('Workflow volume data already present (guards ≥ '.self::TARGET_GUARDS.'). Skipping.');

            return;
        }

        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first()
            ?? User::query()->first();

        if ($admin) {
            Auth::login($admin);
        }

        $this->command?->info('Seeding Platinum Security workflow volume…');

        $organization = app(OrganizationService::class);
        $regions = $this->seedRegions();
        $this->command?->info('Regions: '.$regions->count());

        $clients = $this->seedClients();
        $this->command?->info('Clients: '.$clients->count());

        $supervisors = $this->seedSupervisors($regions);
        $this->command?->info('Supervisors: '.$supervisors->count());

        $sites = $this->seedSites($regions, $supervisors, $clients, $organization);
        $this->command?->info('Sites: '.$sites->count());

        $this->seedOfficeStaff($regions);
        $this->command?->info('Staff: '.Staff::query()->count());

        $guards = $this->seedGuards($regions);
        $this->command?->info('Guards (field): '.$guards->count());

        $deployments = $this->seedDeployments($sites, $guards);
        $this->command?->info('Active deployments: '.$deployments->count());

        $historyShifts = $this->seedDutyHistory($deployments);
        $this->command?->info('Duty history shifts created: '.$historyShifts);

        $this->seedLeaves($guards);
        $this->seedAbsencesAndReplacements($deployments, $guards);
        $this->seedIncidents($sites, $deployments);
        $this->seedAssets($guards);
        $this->seedBillingAndInvoices($clients, $sites);
        $this->seedPayrollSample();

        Auth::logout();

        $this->printSummary();
    }

    /** @return Collection<int, Region> */
    private function seedRegions(): Collection
    {
        $defs = [
            ['name' => 'Central', 'code' => 'CEN', 'manager_name' => 'Amina Juma'],
            ['name' => 'Eastern', 'code' => 'EAS', 'manager_name' => 'Joseph Mwangi'],
            ['name' => 'Northern', 'code' => 'NOR', 'manager_name' => 'Grace Okello'],
            ['name' => 'Western', 'code' => 'WES', 'manager_name' => 'Daniel Kiprotich'],
            ['name' => 'Southern', 'code' => 'SOU', 'manager_name' => 'Harriet Nambi'],
            ['name' => 'Lake Zone', 'code' => 'LAK', 'manager_name' => 'Samuel Owor'],
            ['name' => 'Mid-West', 'code' => 'MID', 'manager_name' => 'Patricia Kyeyune'],
            ['name' => 'Kampala Metro', 'code' => 'KLA', 'manager_name' => 'Robert Ssali'],
        ];

        return collect($defs)->map(function (array $data) {
            return Region::query()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'manager_name' => $data['manager_name'],
                    'manager_phone' => '+256700'.fake()->numerify('######'),
                    'description' => $data['name'].' operational region — Platinum Security.',
                    'status' => RegionStatus::Active,
                ],
            );
        })->values();
    }

    /** @return Collection<int, Client> */
    private function seedClients(): Collection
    {
        $created = collect();
        $existing = Client::query()->count();
        $needed = max(0, self::TARGET_CLIENTS - $existing);

        for ($i = 0; $i < $needed; $i++) {
            $name = $this->clientPrefixes[$i % count($this->clientPrefixes)].' '
                .$this->clientSuffixes[$i % count($this->clientSuffixes)].' '
                .str_pad((string) ($existing + $i + 1), 2, '0', STR_PAD_LEFT);

            if (Client::query()->where('name', $name)->exists()) {
                continue;
            }

            $created->push(Client::query()->create([
                'name' => $name,
                'contact_person' => $this->personName($i),
                'phone' => '+25675'.str_pad((string) (($existing + $i) % 10000000), 7, '0', STR_PAD_LEFT),
                'email' => 'client'.($existing + $i + 1).'@contracts.local',
                'address' => fake()->streetAddress().', Uganda',
                'contract_start_date' => now()->subMonths(fake()->numberBetween(3, 24))->toDateString(),
                'contract_end_date' => now()->addMonths(fake()->numberBetween(6, 24))->toDateString(),
                'contract_status' => ContractStatus::Active,
                'notes' => 'Active Platinum Security services contract.',
            ]));
        }

        return Client::query()->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, Region>  $regions
     * @return Collection<int, Supervisor>
     */
    private function seedSupervisors(Collection $regions): Collection
    {
        $staffService = app(StaffService::class);
        $profiles = app(SupervisorGuardService::class);
        $organization = app(OrganizationService::class);

        // Link any legacy supervisors that lack staff/guard profiles.
        Supervisor::query()
            ->where(function ($query) {
                $query->whereNull('staff_id')->orWhereNull('guard_id');
            })
            ->orderBy('id')
            ->get()
            ->each(function (Supervisor $supervisor) use ($profiles) {
                try {
                    $profiles->ensureEmployeeProfiles($supervisor);
                    $staff = $supervisor->fresh()->staffProfile;
                    if ($staff && (float) $staff->monthly_salary <= 0) {
                        $staff->update(['monthly_salary' => fake()->numberBetween(1_200_000, 2_000_000)]);
                        $profiles->syncGuardSalaryFromStaff($supervisor->fresh()->guardProfile, $staff->fresh());
                    }
                } catch (Throwable $e) {
                    $this->command?->warn('Supervisor profile link failed: '.$e->getMessage());
                }
            });

        $existing = Supervisor::query()->count();
        $needed = max(0, self::TARGET_SUPERVISORS - $existing);
        $regionList = $regions->values();

        for ($i = 0; $i < $needed; $i++) {
            $region = $regionList[($existing + $i) % $regionList->count()];
            $first = $this->firstNames[($existing + $i) % count($this->firstNames)];
            $last = $this->lastNames[($existing + $i * 3) % count($this->lastNames)];

            try {
                $staffService->createStaff([
                    'employment_id' => $staffService->nextEmploymentId(),
                    'first_name' => $first,
                    'last_name' => $last.'-SV',
                    'phone' => '+25671'.str_pad((string) (($existing + $i) % 10000000), 7, '0', STR_PAD_LEFT),
                    'email' => strtolower($first).'.sv'.($existing + $i + 1).'@platinumsecurity.local',
                    'region_id' => $region->id,
                    'employment_status' => EmploymentStatus::Active->value,
                    'job_title' => 'Supervisor',
                    'department' => 'Operations',
                    'date_employed' => now()->subMonths(fake()->numberBetween(2, 48))->toDateString(),
                    'monthly_salary' => fake()->numberBetween(1_200_000, 2_200_000),
                    'bank_name' => fake()->randomElement(['Centenary Bank', 'Stanbic Bank', 'Absa Bank']),
                    'bank_account' => (string) fake()->numerify('##########'),
                    'employee_type' => EmployeeType::Supervisor->value,
                    'supervisor_status' => SupervisorStatus::Active->value,
                    'assignment_date' => now()->subMonths(fake()->numberBetween(1, 24))->toDateString(),
                    'notes' => 'Field supervisor — '.$region->name.'.',
                ]);
            } catch (Throwable $e) {
                $this->command?->warn('Supervisor create skipped: '.$e->getMessage());
            }
        }

        // Ensure every site region has at least one active supervisor.
        foreach ($regions as $region) {
            if (Supervisor::query()->where('region_id', $region->id)->active()->exists()) {
                continue;
            }

            try {
                $staffService->createStaff([
                    'employment_id' => $staffService->nextEmploymentId(),
                    'first_name' => 'Lead',
                    'last_name' => $region->code.'-Supervisor',
                    'phone' => '+25671'.fake()->numerify('#######'),
                    'email' => 'lead.'.strtolower($region->code).'@platinumsecurity.local',
                    'region_id' => $region->id,
                    'employment_status' => EmploymentStatus::Active->value,
                    'job_title' => 'Supervisor',
                    'department' => 'Operations',
                    'date_employed' => now()->subYear()->toDateString(),
                    'monthly_salary' => 1_800_000,
                    'employee_type' => EmployeeType::Supervisor->value,
                    'supervisor_status' => SupervisorStatus::Active->value,
                    'assignment_date' => now()->subYear()->toDateString(),
                ]);
            } catch (Throwable) {
                // continue
            }
        }

        unset($organization);

        return Supervisor::query()->with(['region', 'guardProfile', 'staffProfile'])->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, Region>  $regions
     * @param  Collection<int, Supervisor>  $supervisors
     * @param  Collection<int, Client>  $clients
     * @return Collection<int, Site>
     */
    private function seedSites(
        Collection $regions,
        Collection $supervisors,
        Collection $clients,
        OrganizationService $organization,
    ): Collection {
        $existing = Site::query()->count();
        $needed = max(0, self::TARGET_SITES - $existing);
        $siteTypes = ['Gate', 'Warehouse', 'Depot', 'Mall', 'HQ Annex', 'Plant', 'Yard', 'Campus'];
        $clientList = $clients->values();
        $created = 0;

        for ($i = 0; $i < $needed; $i++) {
            $region = $regions[($existing + $i) % $regions->count()];
            $supervisor = $supervisors->firstWhere('region_id', $region->id)
                ?? $supervisors->first();
            if (! $supervisor) {
                continue;
            }

            $client = $clientList[($existing + $i) % $clientList->count()];
            $seq = $existing + $i + 1;
            $code = $region->code.'-V'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);

            if (Site::query()->where('code', $code)->exists()) {
                continue;
            }

            // Balanced manpower: 2–3 day + 2–3 night unarmed posts (capacity for deployments).
            $dayUnarmed = 2 + ($seq % 2);
            $nightUnarmed = 2 + (($seq + 1) % 2);
            $dayArmed = $seq % 5 === 0 ? 1 : 0;
            $nightArmed = $seq % 7 === 0 ? 1 : 0;
            $day = $dayArmed + $dayUnarmed;
            $night = $nightArmed + $nightUnarmed;

            $site = Site::query()->create([
                'name' => $client->name.' '.$siteTypes[$seq % count($siteTypes)].' '.$region->code,
                'code' => $code,
                'client_id' => $client->id,
                'region_id' => $region->id,
                'supervisor_id' => $supervisor->id,
                'physical_location' => fake()->streetAddress().', '.$region->name.', Uganda',
                'site_contact_person' => $this->personName($seq),
                'site_contact_phone' => '+25676'.str_pad((string) ($seq % 10000000), 7, '0', STR_PAD_LEFT),
                'contract_start_date' => now()->subMonths(fake()->numberBetween(2, 18))->toDateString(),
                'contract_end_date' => now()->addMonths(fake()->numberBetween(6, 24))->toDateString(),
                'required_guards' => $day + $night,
                'required_day_guards' => $day,
                'required_day_armed_guards' => $dayArmed,
                'required_day_unarmed_guards' => $dayUnarmed,
                'required_night_guards' => $night,
                'required_night_armed_guards' => $nightArmed,
                'required_night_unarmed_guards' => $nightUnarmed,
                'number_of_posts' => max($day, $night),
                'status' => SiteStatus::Active,
                'notes' => 'Volume-seeded active site.',
            ]);

            $organization->syncSiteManpower($site, 'Volume seed manpower');
            $created++;
        }

        // Point demo sites (from DemoDataSeeder) at supervisors that now have profiles.
        Site::query()
            ->whereNotNull('supervisor_id')
            ->whereNull('deleted_at')
            ->get()
            ->each(function (Site $site) use ($supervisors) {
                $match = $supervisors->firstWhere('region_id', $site->region_id);
                if ($match && (int) $site->supervisor_id !== (int) $match->id && $site->supervisor_id) {
                    return;
                }
                if ($match && ! $site->supervisor_id) {
                    $site->update(['supervisor_id' => $match->id]);
                }
            });

        unset($created);

        return Site::query()->with(['region', 'client', 'supervisor'])->orderBy('id')->get();
    }

    /** @param  Collection<int, Region>  $regions */
    private function seedOfficeStaff(Collection $regions): void
    {
        $service = app(StaffService::class);
        $existing = Staff::query()->whereDoesntHave('supervisorProfile')->count();
        $needed = max(0, self::TARGET_OFFICE_STAFF - $existing);

        $titles = [
            ['Finance Officer', 'Finance', 1_100_000],
            ['HR Assistant', 'Human Resources', 850_000],
            ['Payroll Officer', 'Finance', 950_000],
            ['Admin Officer', 'Administration', 900_000],
            ['Operations Clerk', 'Operations', 750_000],
            ['Logistics Officer', 'Operations', 800_000],
            ['Receptionist', 'Administration', 650_000],
            ['Accounts Assistant', 'Finance', 880_000],
        ];

        for ($i = 0; $i < $needed; $i++) {
            $title = $titles[$i % count($titles)];
            $first = $this->firstNames[($existing + $i) % count($this->firstNames)];
            $last = $this->lastNames[($existing + $i * 5) % count($this->lastNames)];
            $region = $i % 4 === 0 ? null : $regions[($existing + $i) % $regions->count()];

            try {
                $service->createStaff([
                    'employment_id' => $service->nextEmploymentId(),
                    'first_name' => $first,
                    'last_name' => $last,
                    'phone' => '+25670'.str_pad((string) (($existing + $i) % 10000000), 7, '0', STR_PAD_LEFT),
                    'email' => strtolower($first).'.office'.($existing + $i + 1).'@platinumsecurity.local',
                    'region_id' => $region?->id,
                    'employment_status' => EmploymentStatus::Active->value,
                    'job_title' => $title[0],
                    'department' => $title[1],
                    'date_employed' => now()->subMonths(fake()->numberBetween(1, 60))->toDateString(),
                    'monthly_salary' => $title[2] + (($i % 5) * 25_000),
                    'bank_name' => fake()->randomElement(['Centenary Bank', 'Stanbic Bank', 'dfcu Bank']),
                    'bank_account' => (string) fake()->numerify('##########'),
                    'nssf_number' => fake()->optional(0.7)->numerify('############'),
                    'employee_type' => EmployeeType::Staff->value,
                ]);
            } catch (Throwable $e) {
                $this->command?->warn('Office staff skipped: '.$e->getMessage());
            }
        }
    }

    /**
     * @param  Collection<int, Region>  $regions
     * @return Collection<int, Guard>
     */
    private function seedGuards(Collection $regions): Collection
    {
        $service = app(GuardService::class);
        $existing = Guard::query()->whereDoesntHave('supervisorProfile')->count();
        $needed = max(0, self::TARGET_GUARDS - $existing);
        $ranks = ['Security Guard', 'Security Guard', 'Senior Guard', 'Team Leader'];

        for ($i = 0; $i < $needed; $i++) {
            $n = $existing + $i + 1;
            $region = $regions[($n - 1) % $regions->count()];
            $first = $this->firstNames[$n % count($this->firstNames)];
            $last = $this->lastNames[($n * 7) % count($this->lastNames)];
            $armed = $n % 8 === 0;

            try {
                $service->createGuard([
                    'employment_id' => $service->nextEmploymentId(),
                    'first_name' => $first,
                    'last_name' => $last,
                    'gender' => $n % 3 === 0 ? GuardGender::Female->value : GuardGender::Male->value,
                    'phone' => '+2567'.str_pad((string) ($n % 100000000), 8, '0', STR_PAD_LEFT),
                    'address' => fake()->streetAddress().', '.$region->name,
                    'date_employed' => now()->subMonths(fake()->numberBetween(1, 48))->toDateString(),
                    'employment_status' => EmploymentStatus::Active->value,
                    'operational_status' => OperationalStatus::AwaitingDeployment->value,
                    'rank_designation' => $ranks[$n % count($ranks)],
                    'guard_classification' => $armed
                        ? GuardClassification::Armed->value
                        : GuardClassification::Unarmed->value,
                    'region_id' => $region->id,
                    'compensation_type' => CompensationType::Shift->value,
                    'base_shift_rate' => fake()->numberBetween(700_000, 1_100_000),
                    'bank_name' => fake()->randomElement(['Centenary Bank', 'Stanbic Bank', 'Absa Bank']),
                    'bank_account' => (string) fake()->numerify('##########'),
                    'nssf_number' => fake()->optional(0.6)->numerify('############'),
                ]);
            } catch (Throwable $e) {
                $this->command?->warn('Guard create skipped: '.$e->getMessage());
            }

            if (($i + 1) % 100 === 0) {
                $this->command?->info('  …guards '.($i + 1).'/'.$needed);
            }
        }

        return Guard::query()
            ->whereDoesntHave('supervisorProfile')
            ->where('employment_status', EmploymentStatus::Active)
            ->where('operational_status', OperationalStatus::AwaitingDeployment)
            ->orderBy('id')
            ->get();
    }

    /**
     * Fill site day/night capacity with awaiting guards from the same region.
     *
     * @param  Collection<int, Site>  $sites
     * @param  Collection<int, Guard>  $awaitingGuards
     * @return Collection<int, Deployment>
     */
    private function seedDeployments(Collection $sites, Collection $awaitingGuards): Collection
    {
        $deployments = app(DeploymentService::class);
        $byRegion = $awaitingGuards->groupBy('region_id')->map(fn (Collection $g) => $g->values());
        $created = collect();

        foreach ($sites as $site) {
            foreach ([DeploymentShiftType::Day, DeploymentShiftType::Night] as $period) {
                $need = $period === DeploymentShiftType::Day
                    ? (int) $site->required_day_guards
                    : (int) $site->required_night_guards;

                $current = Deployment::query()
                    ->current()
                    ->where('site_id', $site->id)
                    ->where('shift_type', $period->value)
                    ->count();

                $slots = max(0, $need - $current);
                $pool = $byRegion->get($site->region_id, collect());

                for ($s = 0; $s < $slots; $s++) {
                    /** @var Guard|null $guard */
                    $guard = $pool->shift();
                    if (! $guard) {
                        break;
                    }

                    // Prefer matching armed/unarmed to site mix roughly.
                    $wantsArmed = $period === DeploymentShiftType::Day
                        ? $s < (int) $site->required_day_armed_guards
                        : $s < (int) $site->required_night_armed_guards;

                    if ($wantsArmed && $guard->guard_classification !== GuardClassification::Armed) {
                        $armed = $pool->first(
                            fn (Guard $g) => $g->guard_classification === GuardClassification::Armed
                        );
                        if ($armed) {
                            $pool = $pool->reject(fn (Guard $g) => $g->id === $armed->id)->values();
                            $pool->prepend($guard);
                            $guard = $armed;
                        }
                    }

                    try {
                        $deployment = $deployments->deploy([
                            'guard_id' => $guard->id,
                            'site_id' => $site->id,
                            'shift_type' => $period->value,
                            'start_date' => now()->subDays(self::DUTY_HISTORY_DAYS)->toDateString(),
                            'duty_date_to' => now()->toDateString(),
                            'duty_type' => ShiftType::Normal->value,
                            'notes' => 'Volume seed posting ('.$period->label().').',
                        ]);
                        $created->push($deployment);
                    } catch (Throwable $e) {
                        // Return guard to pool if deploy failed for capacity/conflict.
                        $pool->prepend($guard);
                        $this->command?->warn('Deploy skipped @ '.$site->code.': '.$e->getMessage());
                        break;
                    }
                }

                $byRegion[$site->region_id] = $pool;
            }
        }

        // End a slice of deployments to create historical posting records + free some capacity narrative.
        $toEnd = $created->shuffle()->take(80);
        foreach ($toEnd as $deployment) {
            try {
                $deployments->end(
                    $deployment->fresh(),
                    now()->subDays(2)->toDateString(),
                    'Volume seed — rotation ended for demo history.',
                );
            } catch (Throwable) {
                // continue
            }
        }

        return Deployment::query()
            ->current()
            ->with(['assignedGuard', 'site'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Extra recorded duties are already created when deploy() uses duty_date_to.
     * Top up a few more historical days where safe.
     *
     * @param  Collection<int, Deployment>  $deployments
     */
    private function seedDutyHistory(Collection $deployments): int
    {
        $service = app(DeploymentService::class);
        $created = 0;

        foreach ($deployments->take(250) as $deployment) {
            $guard = $deployment->assignedGuard;
            $site = $deployment->site;
            if (! $guard || ! $site) {
                continue;
            }

            try {
                $created += $service->recordDutiesForPosting(
                    $guard,
                    $site,
                    [
                        'start_date' => now()->subDays(self::DUTY_HISTORY_DAYS + 7)->toDateString(),
                        'duty_date_to' => now()->subDays(self::DUTY_HISTORY_DAYS + 1)->toDateString(),
                        'notes' => 'Volume seed backfill duties.',
                        'duty_type' => ShiftType::Normal->value,
                    ],
                    $deployment->shift_type,
                    $deployment->shift_type,
                );
            } catch (Throwable) {
                // capacity / conflict — skip
            }
        }

        // Sprinkle deliberate overtime shifts on ~40 deployments (explicit duty type).
        foreach ($deployments->shuffle()->take(40) as $deployment) {
            $guard = $deployment->assignedGuard;
            $site = $deployment->site;
            if (! $guard || ! $site) {
                continue;
            }

            $opposite = $deployment->shift_type === DeploymentShiftType::Night
                ? DeploymentShiftType::Day
                : DeploymentShiftType::Night;

            try {
                $created += $service->recordDutiesForPosting(
                    $guard,
                    $site,
                    [
                        'start_date' => now()->subDays(3)->toDateString(),
                        'duty_date_to' => now()->subDays(3)->toDateString(),
                        'notes' => 'Volume seed overtime cover.',
                        'duty_type' => ShiftType::Overtime->value,
                    ],
                    $deployment->shift_type,
                    $opposite,
                );
            } catch (Throwable) {
                // opposite-period conflicts are expected for some guards
            }
        }

        return $created;
    }

    /** @param  Collection<int, Guard>  $guards */
    private function seedLeaves(Collection $guards): void
    {
        $service = app(LeaveService::class);
        $pool = Guard::query()
            ->whereDoesntHave('supervisorProfile')
            ->where('employment_status', EmploymentStatus::Active)
            ->whereIn('operational_status', [
                OperationalStatus::AwaitingDeployment,
                OperationalStatus::OffDuty,
                OperationalStatus::OnDuty,
            ])
            ->inRandomOrder()
            ->limit(self::TARGET_LEAVES + 40)
            ->get();

        $created = 0;
        foreach ($pool as $index => $guard) {
            if ($created >= self::TARGET_LEAVES) {
                break;
            }

            $start = now()->addDays(fake()->numberBetween(5, 45))->toDateString();
            $end = Carbon::parse($start)->addDays(fake()->numberBetween(2, 10))->toDateString();
            $status = $index % 4 === 0 ? LeaveStatus::Pending : LeaveStatus::Approved;

            // Past completed leave for a smaller set (awaiting / off duty only).
            if ($index % 5 === 0 && in_array($guard->operational_status, [OperationalStatus::AwaitingDeployment, OperationalStatus::OffDuty], true)) {
                $start = now()->subDays(fake()->numberBetween(20, 60))->toDateString();
                $end = Carbon::parse($start)->addDays(fake()->numberBetween(3, 7))->toDateString();
                $status = LeaveStatus::Approved;
            }

            try {
                $service->create([
                    'guard_id' => $guard->id,
                    'leave_type' => fake()->randomElement(LeaveType::cases())->value,
                    'start_date' => $start,
                    'end_date' => $end,
                    'expected_return_date' => $end,
                    'reason' => 'Volume seed leave request.',
                    'status' => $status->value,
                ]);
                $created++;
            } catch (Throwable) {
                // continue
            }
        }

        $this->command?->info('Leaves: '.Leave::query()->count());
    }

    /**
     * @param  Collection<int, Deployment>  $deployments
     * @param  Collection<int, Guard>  $originalAwaiting
     */
    private function seedAbsencesAndReplacements(Collection $deployments, Collection $originalAwaiting): void
    {
        $absences = app(AbsenceService::class);
        $replacements = app(ReplacementService::class);
        $shifts = app(ShiftService::class);

        $awaiting = Guard::query()
            ->whereDoesntHave('supervisorProfile')
            ->where('operational_status', OperationalStatus::AwaitingDeployment)
            ->where('employment_status', EmploymentStatus::Active)
            ->orderBy('id')
            ->get()
            ->values();

        $absenceCreated = 0;
        $replacementCreated = 0;

        // Create future scheduled shifts that can be replaced / missed.
        $scheduled = collect();
        foreach ($deployments->shuffle()->take(self::TARGET_REPLACEMENTS + 30) as $deployment) {
            $guard = $deployment->assignedGuard;
            $site = $deployment->site;
            if (! $guard || ! $site) {
                continue;
            }

            $date = now()->addDays(fake()->numberBetween(1, 10))->toDateString();
            $period = $deployment->shift_type === DeploymentShiftType::Night
                ? ShiftPeriod::Night
                : ShiftPeriod::Day;
            $start = $period === ShiftPeriod::Night
                ? (string) config('psg.shift_defaults.night.start', '18:00')
                : (string) config('psg.shift_defaults.day.start', '06:00');
            $end = $period === ShiftPeriod::Night
                ? (string) config('psg.shift_defaults.night.end', '06:00')
                : (string) config('psg.shift_defaults.day.end', '18:00');

            try {
                $shift = $shifts->create([
                    'guard_id' => $guard->id,
                    'site_id' => $site->id,
                    'shift_date' => $date,
                    'start_time' => $start,
                    'end_time' => $end,
                    'period' => $period->value,
                    'shift_type' => ShiftType::Normal->value,
                    'guard_classification' => $guard->guard_classification->value,
                    'status' => ShiftStatus::Scheduled->value,
                    'acknowledge_warnings' => true,
                    'notes' => 'Volume seed scheduled duty.',
                ]);
                $scheduled->push($shift);
            } catch (Throwable) {
                // continue
            }
        }

        // Absences on past recorded shifts (does not require scheduled status).
        $pastShifts = Shift::query()
            ->whereIn('status', ShiftStatus::payableValues())
            ->whereDate('shift_date', '<', now()->toDateString())
            ->whereDate('shift_date', '>=', now()->subDays(14)->toDateString())
            ->inRandomOrder()
            ->limit(self::TARGET_ABSENCES)
            ->get();

        foreach ($pastShifts as $shift) {
            if ($absenceCreated >= self::TARGET_ABSENCES) {
                break;
            }

            try {
                $absences->record([
                    'guard_id' => $shift->guard_id,
                    'absence_date' => $shift->shift_date->toDateString(),
                    'reason' => fake()->randomElement(AbsenceReason::cases())->value,
                    'site_id' => $shift->site_id,
                    'shift_id' => $shift->id,
                    'action_taken' => 'Logged for volume demo.',
                    'replacement_required' => false,
                    'notes' => 'Volume seed absence.',
                ]);
                $absenceCreated++;
            } catch (Throwable) {
                // already absent / still current conflict
            }
        }

        // Replacements against scheduled future shifts.
        foreach ($scheduled as $original) {
            if ($replacementCreated >= self::TARGET_REPLACEMENTS) {
                break;
            }

            $replacementGuard = $awaiting->first(
                fn (Guard $g) => (int) $g->region_id === (int) $original->region_id
                    && $g->id !== $original->guard_id
            );

            if (! $replacementGuard) {
                $replacementGuard = $awaiting->first(fn (Guard $g) => $g->id !== $original->guard_id);
            }

            if (! $replacementGuard) {
                break;
            }

            $awaiting = $awaiting->reject(fn (Guard $g) => $g->id === $replacementGuard->id)->values();

            try {
                $replacements->record([
                    'original_shift_id' => $original->id,
                    'replacement_guard_id' => $replacementGuard->id,
                    'reason' => fake()->randomElement(ReplacementReason::cases())->value,
                    'notes' => 'Volume seed replacement.',
                    'acknowledge_warnings' => true,
                    'override_critical' => true,
                    'override_reason' => 'Volume seed demo override.',
                ]);
                $replacementCreated++;
            } catch (Throwable) {
                $awaiting->prepend($replacementGuard);
            }
        }

        $this->command?->info("Absences created: {$absenceCreated}; Replacements created: {$replacementCreated}");
        unset($originalAwaiting);
    }

    /**
     * @param  Collection<int, Site>  $sites
     * @param  Collection<int, Deployment>  $deployments
     */
    private function seedIncidents(Collection $sites, Collection $deployments): void
    {
        $service = app(IncidentService::class);
        $titles = [
            'Perimeter alarm activation',
            'Unauthorized access attempt',
            'Client complaint — gate delay',
            'Equipment malfunction at post',
            'Medical assistance rendered',
            'Suspicious vehicle reported',
            'Fire extinguisher inspection finding',
            'Lost property report',
        ];

        $created = 0;
        for ($i = 0; $i < self::TARGET_INCIDENTS; $i++) {
            $site = $sites[$i % $sites->count()];
            $deployment = $deployments->firstWhere('site_id', $site->id);
            $guardId = $deployment?->guard_id;

            try {
                $service->record([
                    'site_id' => $site->id,
                    'guard_id' => $guardId,
                    'incident_type' => fake()->randomElement(IncidentType::cases())->value,
                    'severity' => fake()->randomElement(IncidentSeverity::cases())->value,
                    'occurred_at' => now()->subDays(fake()->numberBetween(0, 45))->subHours(fake()->numberBetween(1, 20)),
                    'title' => $titles[$i % count($titles)].' #'.($i + 1),
                    'description' => 'Volume-seeded occurrence for workflow demonstration at '.$site->name.'.',
                    'action_taken' => 'Logged and escalated per SOP.',
                    'client_notified' => $i % 3 === 0,
                ]);
                $created++;
            } catch (Throwable $e) {
                $this->command?->warn('Incident skipped: '.$e->getMessage());
            }
        }

        $this->command?->info("Incidents: {$created}");
    }

    /** @param  Collection<int, Guard>  $guards */
    private function seedAssets(Collection $guards): void
    {
        $service = app(GuardAssetService::class);
        $pool = Guard::query()
            ->whereDoesntHave('supervisorProfile')
            ->where('employment_status', EmploymentStatus::Active)
            ->inRandomOrder()
            ->limit(self::TARGET_ASSET_ISSUANCES)
            ->get();

        $created = 0;
        foreach ($pool as $index => $guard) {
            try {
                $service->issue([
                    'guard_id' => $guard->id,
                    'issuance_type' => AssetIssuanceType::InitialKit->value,
                    'issued_at' => now()->subMonths(fake()->numberBetween(0, 18))->toDateString(),
                    'notes' => 'Volume seed uniform / kit issuance.',
                    'lines' => [
                        [
                            'asset_category' => AssetCategory::Uniform->value,
                            'description' => 'Security uniform set',
                            'quantity' => 1,
                            'unit_value' => 120_000,
                            'recover_cost' => $index % 4 === 0,
                            'recovery_amount' => $index % 4 === 0 ? 120_000 : null,
                            'monthly_recovery' => $index % 4 === 0 ? 20_000 : null,
                        ],
                        [
                            'asset_category' => AssetCategory::Other->value,
                            'description' => 'Whistle & torch kit',
                            'quantity' => 1,
                            'unit_value' => 35_000,
                        ],
                    ],
                ]);
                $created++;
            } catch (Throwable) {
                // continue
            }
        }

        $this->command?->info("Asset issuances: {$created}");
    }

    /**
     * @param  Collection<int, Client>  $clients
     * @param  Collection<int, Site>  $sites
     */
    private function seedBillingAndInvoices(Collection $clients, Collection $sites): void
    {
        $billing = app(BillingService::class);
        $invoices = app(InvoiceService::class);
        $payments = app(PaymentService::class);

        $profileCount = 0;
        foreach ($sites->take(100) as $site) {
            try {
                $billing->create([
                    'client_id' => $site->client_id,
                    'site_id' => $site->id,
                    'billing_mode' => BillingMode::Monthly->value,
                    'currency' => 'UGX',
                    'contracted_day_unarmed_guards' => (int) $site->required_day_unarmed_guards,
                    'contracted_day_armed_guards' => (int) $site->required_day_armed_guards,
                    'contracted_night_unarmed_guards' => (int) $site->required_night_unarmed_guards,
                    'contracted_night_armed_guards' => (int) $site->required_night_armed_guards,
                    'monthly_rate_per_armed_guard' => 650_000,
                    'monthly_rate_per_unarmed_guard' => 450_000,
                    'monthly_rate_per_armed_day_guard' => 650_000,
                    'monthly_rate_per_unarmed_day_guard' => 450_000,
                    'monthly_rate_per_armed_night_guard' => 700_000,
                    'monthly_rate_per_unarmed_night_guard' => 480_000,
                    'effective_from' => now()->subMonths(3)->startOfMonth()->toDateString(),
                    'is_active' => true,
                    'notes' => 'Volume seed billing profile.',
                ]);
                $profileCount++;
            } catch (Throwable) {
                // continue
            }
        }

        $invoiceCount = 0;
        $paymentCount = 0;
        $billableSites = $sites->take(self::TARGET_INVOICES);

        foreach ($billableSites as $index => $site) {
            $periodStart = now()->subMonths(($index % 3) + 1)->startOfMonth()->toDateString();
            $periodEnd = Carbon::parse($periodStart)->endOfMonth()->toDateString();

            try {
                $invoice = $invoices->createDraft([
                    'client_id' => $site->client_id,
                    'site_id' => $site->id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'auto_generate' => true,
                    'notes' => 'Volume seed invoice.',
                ]);

                $issued = $invoices->issue($invoice->fresh('lines'));
                $invoiceCount++;

                if ($index % 2 === 0) {
                    $payments->record([
                        'invoice_id' => $issued->id,
                        'amount' => round(((float) $issued->balance) * ($index % 3 === 0 ? 1 : 0.5), 2),
                        'payment_date' => now()->subDays(fake()->numberBetween(1, 20))->toDateString(),
                        'method' => PaymentMethod::BankTransfer->value,
                        'external_reference' => 'SEED-PAY-'.($index + 1),
                        'notes' => 'Volume seed payment.',
                    ]);
                    $paymentCount++;
                }
            } catch (Throwable $e) {
                // Fall back to manual line if auto_generate finds nothing.
                try {
                    $invoice = $invoices->createDraft([
                        'client_id' => $site->client_id,
                        'site_id' => $site->id,
                        'period_start' => $periodStart,
                        'period_end' => $periodEnd,
                        'lines' => [[
                            'description' => 'Security services — '.$site->name,
                            'quantity' => max(1, (int) $site->required_guards),
                            'unit_price' => 450_000,
                            'site_id' => $site->id,
                        ]],
                        'notes' => 'Volume seed invoice (manual lines).',
                    ]);
                    $issued = $invoices->issue($invoice->fresh('lines'));
                    $invoiceCount++;
                } catch (Throwable) {
                    $this->command?->warn('Invoice skipped: '.$e->getMessage());
                }
            }
        }

        $this->command?->info("Billing profiles: {$profileCount}; Invoices: {$invoiceCount}; Payments: {$paymentCount}");
        unset($clients);
    }

    private function seedPayrollSample(): void
    {
        try {
            $period = PayrollRunService::lastClosedPeriod();
            $run = app(PayrollRunService::class)->createDraft([
                'period_year' => $period->year,
                'period_month' => $period->month,
                'notes' => 'Volume seed payroll run.',
            ], Auth::user());

            app(PayrollCalculationService::class)->calculate($run);
            $this->command?->info('Payroll run calculated: '.$run->fresh()->reference);
        } catch (Throwable $e) {
            $this->command?->warn('Payroll sample skipped: '.$e->getMessage());
        }
    }

    private function personName(int $seed): string
    {
        return $this->firstNames[$seed % count($this->firstNames)].' '
            .$this->lastNames[($seed * 3) % count($this->lastNames)];
    }

    private function printSummary(): void
    {
        $this->command?->newLine();
        $this->command?->info('=== Workflow volume seed summary ===');

        $rows = [
            'Regions' => Region::query()->count(),
            'Clients' => Client::query()->count(),
            'Sites' => Site::query()->count(),
            'Supervisors' => Supervisor::query()->count(),
            'Staff (all)' => Staff::query()->count(),
            'Guards (all)' => Guard::query()->count(),
            'Guards (field)' => Guard::query()->whereDoesntHave('supervisorProfile')->count(),
            'Deployments (current)' => Deployment::query()->current()->count(),
            'Deployments (all)' => Deployment::query()->count(),
            'Shifts' => Shift::query()->count(),
            'Leaves' => Leave::query()->count(),
            'Absences' => DB::table('absences')->count(),
            'Replacements' => DB::table('shift_replacements')->count(),
            'Incidents' => Incident::query()->count(),
            'Invoices' => Invoice::query()->count(),
            'Payments' => DB::table('payments')->count(),
            'Asset issuances' => DB::table('guard_asset_issuances')->count(),
            'Payroll runs' => DB::table('payroll_runs')->count(),
            'Billing profiles' => DB::table('billing_profiles')->count(),
        ];

        foreach ($rows as $label => $count) {
            $this->command?->info(str_pad($label, 24).$count);
        }

        $this->command?->info('Login: admin@platinumsecurity.local / Password@123');
    }
}
