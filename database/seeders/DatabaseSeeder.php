<?php

namespace Database\Seeders;

use App\Enums\BillingMode;
use App\Enums\CompensationType;
use App\Enums\ContractStatus;
use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\EmployeeType;
use App\Enums\EmploymentStatus;
use App\Enums\GuardClassification;
use App\Enums\GuardGender;
use App\Enums\InvoiceStatus;
use App\Enums\LeaveStatus;
use App\Enums\OperationalStatus;
use App\Enums\RegionStatus;
use App\Enums\SalaryChangeReason;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\SiteStatus;
use App\Enums\UniformChargeStatus;
use App\Enums\StaffSalaryChangeType;
use App\Enums\UserRole;
use App\Models\Guard;
use App\Models\Position;
use App\Models\Region;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Supervisor;
use App\Models\User;
use App\Services\EmployeePromotionService;
use App\Services\Finance\BillingService;
use App\Services\Finance\PayrollRunService;
use App\Services\GuardSalaryService;
use App\Services\GuardService;
use App\Services\StaffSalaryService;
use App\Services\StaffService;
use App\Services\UniformChargeExemptionService;
use App\Services\UserAccessService;
use App\Support\Access\RolePermissionService;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Head-office users, then a company of about 300 guards from PSG_SEED_START_DATE
 * (1 January 2023) through today when PSG_SEED_MODE=load.
 *
 * Records follow employment order: regions and sites, then guards, promotions,
 * duties, payroll, billing, and incidents. Names and contracts are fictional.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private const PASSWORD = 'Password@123';

    private const GUARD_MONTHLY_GROSS = 170000;

    private const TARGET_SUPERVISORS = 10;

    private const TARGET_OFFICE_STAFF = 24;

    /** @var list<string> */
    private array $maleNames = ['Richard', 'Brian', 'Ivan', 'Derrick', 'Joseph', 'Patrick', 'Samuel', 'Daniel', 'Moses', 'Ronald', 'Peter', 'Francis', 'Isaac', 'Denis', 'Robert', 'Emmanuel', 'Andrew', 'Charles', 'David', 'Fred', 'Herbert', 'Julius', 'Kenneth', 'Lawrence', 'Martin', 'Nicholas', 'Paul', 'Stephen', 'Timothy', 'Walter', 'Yusuf', 'Hamza', 'Simon', 'Godfrey', 'Henry'];

    /** @var list<string> */
    private array $femaleNames = ['Sarah', 'Sharon', 'Grace', 'Joan', 'Esther', 'Irene', 'Peace', 'Scovia', 'Janet', 'Doreen', 'Naomi', 'Amina', 'Ruth', 'Prossy', 'Rebecca', 'Christine', 'Florence', 'Harriet', 'Immaculate', 'Juliet', 'Linda', 'Mary', 'Norah', 'Olivia', 'Patience', 'Rita', 'Stella', 'Teddy', 'Violet', 'Winnie', 'Zahara', 'Agnes', 'Betty', 'Claire', 'Diana'];

    /** @var list<string> */
    private array $lastNames = ['Kaheru', 'Mugisha', 'Tumusiime', 'Mwesigwa', 'Okello', 'Ouma', 'Kato', 'Ssekabira', 'Tumwine', 'Namukasa', 'Nanyonga', 'Atim', 'Akello', 'Nakato', 'Kakooza', 'Ssempala', 'Achieng', 'Nabwire', 'Turyamureeba', 'Kyomuhendo', 'Bwambale', 'Muhwezi', 'Katusiime', 'Byaruhanga', 'Ninsiima', 'Otim', 'Namuli', 'Kiprotich', 'Asiimwe', 'Wasswa', 'Ochieng', 'Odong', 'Babirye', 'Nakimuli', 'Lubega', 'Muwonge', 'Nsubuga', 'Opio', 'Adong', 'Kirabo'];

    public function run(): void
    {
        if (app()->environment('production') && ! filter_var(config('psg.seed.allow_production', false), FILTER_VALIDATE_BOOL)) {
            $this->command?->warn('Production refused the seeder. User accounts were left unchanged.');

            return;
        }

        $this->seedHeadOfficeUsers();
        app(RolePermissionService::class)->mergeMissingPermissions();

        if (strtolower(trim((string) config('psg.seed.mode', 'off'))) !== 'load') {
            return;
        }

        ini_set('memory_limit', '1024M');
        set_time_limit(0);
        DB::disableQueryLog();

        if ($this->companyAlreadyPresent() && ! $this->resumeRequested()) {
            $this->command?->warn('Region KLA is already in this database. The large-company history was left unchanged.');
            $this->command?->warn('PSG_SEED_RESUME=true continues an interrupted load without duplicating closed months.');

            return;
        }

        $this->silenceOutboundMail();

        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first();
        if ($admin === null) {
            $this->command?->warn('Head-office users are missing. Seed users before the large-company history.');

            return;
        }

        Auth::login($admin);
        $this->command?->info('Large company history '.$this->seedStart()->toDateString().' through '.$this->seedEnd()->toDateString().'.');

        $this->ensureSeniorGuardPosition();
        $regions = $this->seedRegions();
        $this->seedSupervisors($regions);
        $this->seedSupervisorUsers();
        $this->seedOfficeStaff($regions);
        $this->seedOfficeUsers();
        $clients = $this->seedClients();
        $sites = $this->seedSites($regions, $clients);
        $guards = $this->seedOperationalGuards($regions);
        $this->seedSalaryHistory($guards);
        $this->seedUniformExemptions($guards);
        $this->seedPromotions($guards);
        $this->markLeavers($guards);
        $leaveDays = $this->seedLeave($guards);
        $plans = $this->deploymentPlans($guards, $sites);
        $this->seedDeploymentsAndShifts($plans, $leaveDays);
        $this->seedReplacements();
        $this->seedAttendanceAndAbsence();
        $this->refreshGuardPostings();
        $this->noteRosterVariety();
        $this->seedIncidents();
        $this->seedPayroll();
        $this->seedBillingProfiles($sites);
        $this->seedInvoices($clients, $sites);
        $this->seedAuditAndNotifications();
        $this->assertOperationalDataset();
        $this->printReport();
        Auth::logout();
    }

    private function seedHeadOfficeUsers(): void
    {
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
    }

    public function patchVolumes(): void
    {
        $this->copyCancelledPayslips();
        $this->addSupplementalInvoices();
    }

    private function silenceOutboundMail(): void
    {
        config([
            'mail.default' => 'log',
            'psg.notifications.workflow_email_enabled' => false,
        ]);
        Mail::fake();
    }

    private function companyAlreadyPresent(): bool
    {
        return Region::query()->where('code', 'KLA')->exists();
    }

    private function resumeRequested(): bool
    {
        return filter_var(config('psg.seed.resume', false), FILTER_VALIDATE_BOOL);
    }

    private function seedStart(): Carbon
    {
        $configured = trim((string) config('psg.seed.start_date', '2023-01-01'));

        try {
            return Carbon::parse($configured !== '' ? $configured : '2023-01-01')->startOfDay();
        } catch (\Throwable) {
            return Carbon::parse('2023-01-01')->startOfDay();
        }
    }

    private function seedEnd(): Carbon
    {
        return now()->startOfDay();
    }

    private function ensureSeniorGuardPosition(): void
    {
        Position::query()->firstOrCreate(
            ['code' => 'senior_guard'],
            [
                'name' => 'Senior Guard',
                'is_guard_position' => true,
                'is_staff_position' => false,
                'is_supervisor_position' => false,
                'is_management_position' => false,
                'eligible_for_deployment' => true,
                'eligible_for_shifts' => true,
                'eligible_for_overtime' => true,
                'salary_type' => 'variable',
                'is_active' => true,
            ],
        );
    }

    /** @return list<Region> */
    private function seedRegions(): array
    {
        $definitions = [
            ['code' => 'KLA', 'name' => 'Kampala'],
            ['code' => 'WKS', 'name' => 'Wakiso'],
            ['code' => 'MUK', 'name' => 'Mukono'],
            ['code' => 'ENT', 'name' => 'Entebbe'],
            ['code' => 'JIN', 'name' => 'Jinja'],
            ['code' => 'MBA', 'name' => 'Mbarara'],
        ];
        $regions = [];
        foreach ($definitions as $definition) {
            $regions[] = Region::query()->firstOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['name'].' operations, opened '.$this->seedStart()->toDateString().'.',
                    'status' => RegionStatus::Active->value,
                    'created_by' => Auth::id(),
                ],
            );
        }

        $this->command?->info('Regions ready.');

        return $regions;
    }

    /** @param  list<Region>  $regions */
    private function seedSupervisors(array $regions): void
    {
        if (Supervisor::query()->count() >= self::TARGET_SUPERVISORS) {
            return;
        }

        $staff = app(StaffService::class);
        $perRegion = [3, 2, 2, 1, 1, 1];
        $salaries = [1200000, 1350000, 1500000, 1100000, 1600000, 1450000, 1250000];
        $cursor = 0;
        Auth::login(User::query()->where('email', 'hr@platinumsecurity.local')->first() ?? Auth::user());

        foreach ($regions as $regionIndex => $region) {
            for ($s = 0; $s < ($perRegion[$regionIndex] ?? 1); $s++) {
                $hired = $this->spreadDate($cursor, self::TARGET_SUPERVISORS, $this->seedStart()->toDateString(), '2026-03-01');
                $email = strtolower($region->code).'.s'.$s.'@platinumsecurity.local';
                if (Staff::query()->where('email', $email)->exists()) {
                    $cursor++;

                    continue;
                }

                $staff->createStaff([
                    'first_name' => $this->givenName($cursor + 3),
                    'last_name' => $this->familyName($cursor + 5),
                    'phone' => '+256702'.str_pad((string) $cursor, 6, '0', STR_PAD_LEFT),
                    'email' => $email,
                    'gender' => $this->isFemale($cursor + 3) ? GuardGender::Female->value : GuardGender::Male->value,
                    'region_id' => $region->id,
                    'date_employed' => $hired,
                    'job_title' => 'Supervisor',
                    'department' => 'Operations',
                    'monthly_salary' => $salaries[$cursor % count($salaries)],
                    'bank_name' => 'Centenary Bank',
                    'bank_account' => '32'.str_pad((string) $cursor, 8, '0', STR_PAD_LEFT),
                    'employee_type' => EmployeeType::Supervisor->value,
                    'assignment_date' => $hired,
                    'notes' => 'Field supervisor engaged in '.$this->yearOf($hired).'.',
                ]);
                $cursor++;
            }
        }

        $this->command?->info('Supervisors ready.');
    }

    private function seedSupervisorUsers(): void
    {
        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first();
        if ($admin !== null) {
            Auth::login($admin);
        }

        $access = app(UserAccessService::class);
        Supervisor::query()->orderBy('id')->each(function (Supervisor $supervisor) use ($access): void {
            $email = $supervisor->email ?: $supervisor->staffProfile?->email;
            if ($email === null) {
                return;
            }

            $already = User::query()->where('supervisor_id', $supervisor->id)->orWhere('email', $email)->exists();
            if ($already) {
                return;
            }

            $access->create([
                'name' => $supervisor->name,
                'email' => $email,
                'phone' => $supervisor->phone,
                'role' => UserRole::RegionSupervisor->value,
                'supervisor_id' => $supervisor->id,
                'password' => self::PASSWORD,
                'is_active' => true,
            ]);
        });
    }

    /** @param  list<Region>  $regions */
    private function seedOfficeStaff(array $regions): void
    {
        $titles = [
            ['Finance Officer', 'Finance', 900000],
            ['Admin Assistant', 'Administration', 700000],
            ['Operations Clerk', 'Operations', 750000],
            ['HR Assistant', 'Human Resources', 680000],
        ];
        $existing = Staff::query()->whereDoesntHave('supervisorProfile')->count();
        $staff = app(StaffService::class);
        Auth::login(User::query()->where('email', 'hr@platinumsecurity.local')->first() ?? Auth::user());

        for ($i = $existing; $i < self::TARGET_OFFICE_STAFF; $i++) {
            $title = $titles[$i % count($titles)];
            $hired = $this->spreadDate($i, self::TARGET_OFFICE_STAFF, $this->seedStart()->toDateString(), '2026-06-01');
            $region = $regions[$i % count($regions)];
            $email = 'office'.$i.'@platinumsecurity.local';
            if (Staff::query()->where('email', $email)->exists()) {
                continue;
            }

            $member = $staff->createStaff([
                'first_name' => $this->givenName($i + 11),
                'last_name' => $this->familyName($i + 4),
                'phone' => '+256705'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'email' => $email,
                'gender' => $this->isFemale($i + 11) ? GuardGender::Female->value : GuardGender::Male->value,
                'region_id' => $region->id,
                'date_employed' => $hired,
                'job_title' => $title[0],
                'department' => $title[1],
                'monthly_salary' => $title[2] + ($i * 5000),
                'bank_name' => 'Centenary Bank',
                'bank_account' => '33'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'employee_type' => EmployeeType::Staff->value,
                'notes' => 'Office staff engaged in '.$this->yearOf($hired).'.',
            ]);

            $reviewOn = $this->addDays($hired, 180);
            if ($i < 15 && $reviewOn < $this->seedEnd()->toDateString()) {
                app(StaffSalaryService::class)->change(
                    $member,
                    (float) $member->monthly_salary + 50000,
                    Carbon::parse($reviewOn),
                    StaffSalaryChangeType::Increment,
                    'Annual office salary review.',
                    User::query()->where('email', 'hr@platinumsecurity.local')->first(),
                    $member->job_title,
                );
            }
        }

        $this->command?->info('Office staff ready.');
    }

    private function seedOfficeUsers(): void
    {
        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first();
        if ($admin !== null) {
            Auth::login($admin);
        }

        $access = app(UserAccessService::class);
        for ($i = 0; $i < 5; $i++) {
            $email = 'clerk'.$i.'@platinumsecurity.local';
            if (User::query()->where('email', $email)->exists()) {
                continue;
            }

            $access->create([
                'name' => $this->givenName($i + 2).' '.$this->familyName($i + 8),
                'email' => $email,
                'phone' => '+256706'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'role' => UserRole::ShiftManager->value,
                'password' => self::PASSWORD,
                'is_active' => true,
            ]);
        }
    }

    /**
     * @return list<array{id: int, opened: string}>
     */
    private function seedClients(): array
    {
        $names = [
            'Nakasero Office Complex', 'Ntinda Business Park', 'Namanve Industrial Yard', 'Bweyogerere Warehouse',
            'Entebbe Road Distribution Centre', 'Jinja Road Factory', 'Mbarara High Street Offices', 'Kololo Residential Estate',
            'Bugolobi Shopping Centre', 'Luzira Industrial Warehouse', 'Kireka Commercial Centre', 'Seeta School Campus',
            'Mukono Hospital Annex', 'Kajjansi Warehouse', 'Kawempe Market Offices', 'Wandegeya Court Offices',
            'Port Bell Distribution Yard', 'Njeru Industrial Area', 'Walukuba Factory', 'Kakoba Residential Estate',
            'Nyamitanga Office Block', 'Kigungu Airport Road Complex', 'Abaita Ababiri Estate', 'Katosi Landing Warehouse',
            'Gayaza Road School', 'Banda Industrial Park', 'Nsambya Hospital Gate', 'Rubaga Hill Offices',
            'Kitgum House Annex', 'Crested Towers Annex',
        ];
        $opened = [];
        $waves = [
            [$this->seedStart()->toDateString(), 8],
            ['2024-01-01', 8],
            ['2025-01-01', 8],
            ['2026-03-01', 6],
        ];
        foreach ($waves as [$start, $count]) {
            for ($n = 0; $n < $count; $n++) {
                $opened[] = $this->spreadDate($n, $count, $start, $this->addDays($start, 300));
            }
        }

        $clients = [];
        foreach ($opened as $index => $start) {
            if ($start > $this->seedEnd()->toDateString()) {
                $start = $this->seedEnd()->toDateString();
            }
            $email = 'client'.($index + 1).'@example.test';
            $existing = DB::table('clients')->where('email', $email)->first();
            if ($existing !== null) {
                $clients[] = ['id' => (int) $existing->id, 'opened' => $start];

                continue;
            }

            $id = DB::table('clients')->insertGetId([
                'name' => $names[$index % count($names)].' (test)',
                'contact_person' => $this->givenName($index + 6).' '.$this->familyName($index + 2),
                'phone' => '+256703'.str_pad((string) $index, 6, '0', STR_PAD_LEFT),
                'email' => $email,
                'address' => $names[$index % count($names)].', Uganda',
                'contract_start_date' => $start,
                'contract_end_date' => $this->seedEnd()->copy()->endOfYear()->toDateString(),
                'contract_status' => ContractStatus::Active->value,
                'notes' => 'Fictional test contract. Not a record of a real engagement.',
                'created_by' => Auth::id(),
                'created_at' => $start.' 08:00:00',
                'updated_at' => $start.' 08:00:00',
            ]);
            $clients[] = ['id' => $id, 'opened' => $start];
        }

        $this->command?->info('Clients ready: '.count($clients).'.');

        return $clients;
    }

    /**
     * @param  list<Region>  $regions
     * @param  list<array{id: int, opened: string}>  $clients
     * @return list<array{id: int, region_id: int, supervisor_id: int|null, opened: string, client_id: int, day: int, night: int}>
     */
    private function seedSites(array $regions, array $clients): array
    {
        $waves = [
            'KLA' => [6, 5, 5, 4],
            'WKS' => [4, 4, 3, 3],
            'MUK' => [3, 3, 3, 2],
            'ENT' => [3, 2, 2, 2],
            'JIN' => [3, 2, 2, 2],
            'MBA' => [2, 2, 2, 2],
        ];
        $years = [2023, 2024, 2025, 2026];
        $patterns = [[2, 1, 1], [4, 2, 2], [6, 3, 3], [3, 2, 1], [5, 2, 3], [8, 4, 4]];
        $kinds = ['Office Complex', 'Warehouse', 'Factory', 'Shopping Centre', 'Hotel', 'Hospital', 'School', 'Residential Estate', 'Distribution Centre', 'Industrial Yard'];
        $sites = [];
        $billing = app(BillingService::class);

        foreach ($regions as $region) {
            $sequence = 0;
            $supervisors = Supervisor::query()->where('region_id', $region->id)->orderBy('assignment_date')->get();
            foreach ($waves[$region->code] ?? [] as $waveIndex => $count) {
                for ($n = 0; $n < $count; $n++) {
                    $sequence++;
                    $code = $region->code.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
                    $opened = Carbon::create($years[$waveIndex], 1, 1)->addDays((int) floor((360 / max(1, $count)) * $n))->toDateString();
                    if ($opened < $this->seedStart()->toDateString()) {
                        $opened = $this->seedStart()->toDateString();
                    }
                    if ($opened > $this->seedEnd()->toDateString()) {
                        $opened = $this->seedEnd()->toDateString();
                    }

                    $existing = Site::query()->where('code', $code)->first();
                    $pattern = $region->code === 'KLA' && $sequence === 12
                        ? [20, 10, 10]
                        : ($sequence % 15 === 0 ? [12, 6, 6] : $patterns[$sequence % count($patterns)]);
                    $client = $this->clientOpenBy($clients, $opened, $sequence);
                    $supervisor = $supervisors->first(fn (Supervisor $row) => $row->assignment_date === null || $row->assignment_date->toDateString() <= $opened)
                        ?? $supervisors->first();

                    if ($existing === null) {
                        $existing = Site::query()->create([
                            'name' => $region->name.' '.$kinds[$sequence % count($kinds)].' '.$sequence,
                            'code' => $code,
                            'client_id' => $client['id'],
                            'region_id' => $region->id,
                            'supervisor_id' => $supervisor?->id,
                            'physical_location' => $region->name.', plot '.$sequence,
                            'site_contact_person' => $this->givenName($sequence + 8).' '.$this->familyName($sequence),
                            'site_contact_phone' => '+256708'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
                            'contract_start_date' => $opened,
                            'contract_end_date' => $this->seedEnd()->copy()->endOfYear()->toDateString(),
                            'required_guards' => $pattern[0],
                            'required_day_guards' => $pattern[1],
                            'required_day_armed_guards' => 0,
                            'required_day_unarmed_guards' => $pattern[1],
                            'required_night_guards' => $pattern[2],
                            'required_night_armed_guards' => 0,
                            'required_night_unarmed_guards' => $pattern[2],
                            'number_of_posts' => max($pattern[1], $pattern[2]),
                            'status' => SiteStatus::Active->value,
                            'notes' => 'Site opened in '.$this->yearOf($opened).'.',
                            'created_by' => Auth::id(),
                        ]);
                        $existing->forceFill([
                            'created_at' => $opened.' 08:00:00',
                            'updated_at' => $opened.' 08:00:00',
                        ])->save();
                        DB::table('site_manpower_requirements')->insert([
                            'site_id' => $existing->id,
                            'required_total' => $pattern[0],
                            'required_day' => $pattern[1],
                            'required_day_armed' => 0,
                            'required_day_unarmed' => $pattern[1],
                            'required_night' => $pattern[2],
                            'required_night_armed' => 0,
                            'required_night_unarmed' => $pattern[2],
                            'effective_from' => $opened,
                            'is_current' => true,
                            'notes' => 'Opening manpower requirement',
                            'created_by' => Auth::id(),
                            'created_at' => $opened.' 08:00:00',
                            'updated_at' => $opened.' 08:00:00',
                        ]);
                    }

                    if (! DB::table('billing_profiles')->where('site_id', $existing->id)->exists()) {
                        $billing->create([
                            'client_id' => $existing->client_id,
                            'site_id' => $existing->id,
                            'billing_mode' => BillingMode::Monthly->value,
                            'monthly_rate_per_unarmed_guard' => 450000,
                            'monthly_rate_per_armed_guard' => 650000,
                            'effective_from' => $opened,
                        ]);
                    }

                    if ($sequence % 11 === 0) {
                        $existing->update(['status' => SiteStatus::Closed->value]);

                        continue;
                    }

                    $sites[] = [
                        'id' => $existing->id,
                        'region_id' => (int) $existing->region_id,
                        'supervisor_id' => $existing->supervisor_id ? (int) $existing->supervisor_id : null,
                        'opened' => $existing->contract_start_date?->toDateString() ?? $opened,
                        'client_id' => (int) $existing->client_id,
                        'day' => (int) $existing->required_day_guards,
                        'night' => (int) $existing->required_night_guards,
                    ];
                }
            }
        }

        $this->command?->info('Sites ready: '.count($sites).'.');

        return $sites;
    }

    /**
     * @param  list<Region>  $regions
     * @return list<array{id: int, region_id: int, hired: string, left: string|null, active: bool}>
     */
    private function seedOperationalGuards(array $regions): array
    {
        $positionId = Position::query()->where('code', 'security_guard')->value('id');
        $service = app(GuardService::class);
        Auth::login(User::query()->where('email', 'hr@platinumsecurity.local')->first() ?? Auth::user());
        $targetOperational = $this->targetGuards() - Guard::query()->whereHas('supervisorProfile')->count();
        $existing = Guard::query()->whereDoesntHave('supervisorProfile')->count();
        $shares = $this->regionShares(count($regions), max(0, $targetOperational));
        $regionIds = [];
        foreach ($regions as $regionIndex => $region) {
            for ($n = 0; $n < ($shares[$regionIndex] ?? 0); $n++) {
                $regionIds[] = $region->id;
            }
        }

        for ($i = $existing; $i < $targetOperational; $i++) {
            $phone = '+256704'.str_pad((string) $i, 6, '0', STR_PAD_LEFT);
            if (Guard::query()->where('phone', $phone)->exists()) {
                continue;
            }

            $hired = $this->guardHireDate($i, $targetOperational);
            $rate = match ($i % 5) {
                0 => 200000,
                1 => 180000,
                default => self::GUARD_MONTHLY_GROSS,
            };
            $service->createGuard([
                'first_name' => $this->givenName($i),
                'last_name' => $this->familyName($i * 3),
                'region_id' => $regionIds[$i],
                'gender' => $i % 3 === 0 ? GuardGender::Female->value : GuardGender::Male->value,
                'phone' => $phone,
                'date_employed' => $hired,
                'employment_status' => EmploymentStatus::Active->value,
                'operational_status' => OperationalStatus::AwaitingDeployment->value,
                'rank_designation' => 'Security Guard',
                'position_id' => $positionId,
                'guard_classification' => GuardClassification::Unarmed->value,
                'address' => $regions[$i % count($regions)]->name.', Uganda',
                'compensation_type' => CompensationType::Shift->value,
                'base_shift_rate' => $rate,
                'bank_name' => 'Centenary Bank',
                'bank_account' => '30'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'nssf_number' => 'NSSF'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
            ]);

            if (($i + 1) % 50 === 0) {
                $this->command?->info('Guards created: '.($i + 1).' / '.$targetOperational.'.');
            }
        }

        DB::table('guard_status_histories')
            ->join('guards', 'guards.id', '=', 'guard_status_histories.guard_id')
            ->where('guard_status_histories.reason', 'initial_registration')
            ->update([
                'guard_status_histories.effective_at' => DB::raw('guards.date_employed'),
                'guard_status_histories.created_at' => DB::raw('guards.date_employed'),
            ]);

        $rows = Guard::query()
            ->whereDoesntHave('supervisorProfile')
            ->orderBy('id')
            ->get(['id', 'region_id', 'date_employed'])
            ->map(fn (Guard $guard) => [
                'id' => $guard->id,
                'region_id' => (int) $guard->region_id,
                'hired' => $guard->date_employed?->toDateString() ?? $this->seedStart()->toDateString(),
                'left' => null,
                'active' => true,
            ])
            ->all();

        $this->command?->info('Operational guards ready: '.count($rows).'.');

        return $rows;
    }

    /** @param  list<array{id: int, region_id: int, hired: string, left: string|null, active: bool}>  $guards */
    private function seedSalaryHistory(array $guards): void
    {
        $salaries = app(GuardSalaryService::class);
        $hr = User::query()->where('email', 'hr@platinumsecurity.local')->first();
        Auth::login($hr ?? Auth::user());
        $done = 0;

        foreach ($guards as $index => $guard) {
            if ($index % 8 !== 0 || $done >= 40) {
                continue;
            }

            $review = $this->addDays($guard['hired'], 365);
            if ($review >= $this->seedEnd()->toDateString()) {
                continue;
            }

            $model = Guard::query()->find($guard['id']);
            if ($model === null || $model->salaryRevisions()->count() > 1) {
                continue;
            }

            $salaries->increment(
                $model,
                (float) $model->base_shift_rate + 15000,
                Carbon::parse($review),
                SalaryChangeReason::LengthOfService,
                $hr,
                'Length-of-service increase after the first year.',
            );
            $done++;
        }

        $this->command?->info('Salary reviews recorded: '.$done.'.');
    }

    /** @param  list<array{id: int, region_id: int, hired: string, left: string|null, active: bool}>  $guards */
    private function seedPromotions(array &$guards): void
    {
        $position = Position::query()->where('code', 'senior_guard')->first();
        $officer = Position::query()->where('code', 'operations_officer')->first();
        $supervisorPosition = Position::query()->where('code', 'supervisor')->first();
        if ($position === null) {
            return;
        }

        $promotions = app(EmployeePromotionService::class);
        $hr = User::query()->where('email', 'hr@platinumsecurity.local')->first();
        Auth::login($hr ?? Auth::user());
        $moved = 0;

        foreach ($guards as $index => $guard) {
            if ($moved >= 3 || $this->yearOf($guard['hired']) > 2024 || $index % 40 !== 7) {
                continue;
            }

            $next = $moved === 0 ? $supervisorPosition : $officer;
            if ($next === null) {
                continue;
            }

            $effective = $this->addDays($guard['hired'], 500);
            if ($effective >= $this->seedEnd()->toDateString()) {
                continue;
            }

            $model = Guard::query()->find($guard['id']);
            if ($model === null || $model->promotions()->exists()) {
                continue;
            }

            $promotion = $promotions->schedule(
                $model,
                $next,
                $next->is_supervisor_position ? 1400000 : 900000,
                Carbon::parse($effective),
                'Moved from the guard roster after field service.',
                $hr,
                'MOVE-'.$model->id,
                null,
                $guard['region_id'],
            );
            $staffStart = Carbon::parse($effective)->startOfMonth()->addMonth()->toDateString();
            $guardLast = Carbon::parse($staffStart)->subDay()->toDateString();
            if ($promotion->staff_id) {
                Staff::query()->whereKey($promotion->staff_id)->update([
                    'date_employed' => $staffStart,
                    'compensation_from' => $staffStart,
                ]);
            }
            Guard::query()->whereKey($model->id)->update([
                'guard_pay_until' => $guardLast,
            ]);
            $guards[$index]['active'] = false;
            $guards[$index]['left'] = $guardLast;
            $moved++;
        }

        $done = 0;

        foreach ($guards as $index => $guard) {
            if ($index % 20 !== 0 || $done >= 15 || ! $guard['active']) {
                continue;
            }

            $effective = $this->addDays($guard['hired'], 400);
            if ($effective < '2024-01-01' || $effective >= $this->seedEnd()->toDateString()) {
                continue;
            }

            $model = Guard::query()->find($guard['id']);
            if ($model === null || $model->promotions()->exists() || $model->salaryRevisions()->count() > 1) {
                continue;
            }

            $promotions->schedule(
                $model,
                $position,
                (float) $model->base_shift_rate + 25000,
                Carbon::parse($effective),
                'Promoted to Senior Guard after a full year on post.',
                $hr,
                'PROM-'.$model->id,
                null,
                $guard['region_id'],
            );
            $done++;
        }

        $this->command?->info('Promotions applied: '.($done + $moved).', including '.$moved.' who left the guard roster.');
    }

    /** @param  list<array{id: int, region_id: int, hired: string, left: string|null, active: bool}>  $guards */
    private function markLeavers(array &$guards): void
    {
        $service = app(GuardService::class);
        $hr = User::query()->where('email', 'hr@platinumsecurity.local')->first();
        Auth::login($hr ?? Auth::user());
        $statuses = [
            EmploymentStatus::Resigned,
            EmploymentStatus::Terminated,
            EmploymentStatus::Retired,
            EmploymentStatus::Suspended,
        ];
        $marked = 0;
        $limit = max(8, (int) round(count($guards) * 0.08));

        foreach ($guards as $index => $guard) {
            $model = Guard::query()->find($guard['id']);
            if ($model !== null && $model->employment_status !== EmploymentStatus::Active) {
                $guards[$index]['active'] = false;
                $guards[$index]['left'] = $model->employment_end_date?->toDateString();
                $marked++;

                continue;
            }
            if ($marked >= $limit) {
                continue;
            }
            if ($this->yearOf($guard['hired']) > 2024) {
                continue;
            }

            $left = $this->addDays($guard['hired'], 420 + ($marked * 7));
            if ($left >= $this->seedEnd()->toDateString()) {
                $left = $this->addDays($this->seedEnd()->toDateString(), -30 - $marked);
            }
            if ($left <= $guard['hired']) {
                continue;
            }

            $model = Guard::query()->find($guard['id']);
            if ($model === null) {
                continue;
            }

            $status = $statuses[$marked % count($statuses)];
            $service->updateGuard($model, [
                'employment_status' => $status->value,
                'employment_end_date' => $left,
                'operational_status' => OperationalStatus::OffDuty->value,
            ], 'employment_ended');
            DB::table('guard_status_histories')
                ->where('guard_id', $model->id)
                ->where('new_status', $status->value)
                ->update(['effective_at' => $left.' 08:00:00']);
            $guards[$index]['active'] = false;
            $guards[$index]['left'] = $left;
            $marked++;
        }

        $this->command?->info('Leavers recorded: '.$marked.'.');
    }

    /**
     * @param  list<array{id: int, region_id: int, hired: string, left: string|null, active: bool}>  $guards
     * @return array<int, array<string, true>>
     */
    private function seedLeave(array $guards): array
    {
        if (DB::table('leaves')->count() >= 160) {
            return $this->leaveSkipDays();
        }

        $annual = (int) DB::table('leave_types')->where('code', 'annual')->value('id');
        $sick = (int) DB::table('leave_types')->where('code', 'sick')->value('id');
        $hrId = User::query()->where('email', 'hr@platinumsecurity.local')->value('id');
        $already = DB::table('leaves')->pluck('guard_id')->flip();
        $days = [];
        $rows = [];
        $rejected = (int) DB::table('leaves')->where('status', LeaveStatus::Rejected->value)->count();

        foreach ($guards as $index => $guard) {
            if (count($already) + count($rows) >= 160) {
                break;
            }
            if (isset($already[$guard['id']])) {
                continue;
            }

            $start = $this->addDays($guard['hired'], 200 + ($index % 40));
            $endLimit = $guard['left'] ?? $this->addDays($this->seedEnd()->toDateString(), -3);
            if ($start >= $endLimit) {
                continue;
            }
            $end = $this->addDays($start, 4);
            if ($end > $endLimit) {
                $end = $endLimit;
            }

            $cursor = $start;
            while ($cursor <= $end) {
                $days[$guard['id']][$cursor] = true;
                $cursor = $this->addDays($cursor, 1);
            }

            $status = $rejected < 20 && $index % 21 === 0 ? LeaveStatus::Rejected : LeaveStatus::Completed;
            if ($status === LeaveStatus::Rejected) {
                $rejected++;
            }

            $rows[] = [
                'guard_id' => $guard['id'],
                'leave_type' => $index % 5 === 0 ? 'sick' : 'annual',
                'leave_type_id' => $index % 5 === 0 ? $sick : $annual,
                'start_date' => $start,
                'end_date' => $end,
                'expected_return_date' => $this->addDays($end, 1),
                'days' => $this->inclusiveDays($start, $end),
                'reason' => $status === LeaveStatus::Rejected ? 'Dates clash with a client cover.' : 'Approved rest days.',
                'status' => $status->value,
                'rejection_reason' => $status === LeaveStatus::Rejected ? 'Cover was not available for those dates.' : null,
                'requested_by' => $hrId,
                'approved_by' => $status === LeaveStatus::Completed ? $hrId : null,
                'approved_at' => $status === LeaveStatus::Completed ? $start.' 09:00:00' : null,
                'submitted_at' => $this->addDays($start, -7).' 09:00:00',
                'created_by' => $hrId,
                'created_at' => $this->addDays($start, -7).' 09:00:00',
                'updated_at' => $start.' 09:00:00',
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('leaves')->insert($chunk);
        }

        $this->command?->info('Leave records: '.DB::table('leaves')->count().'.');

        return $this->leaveSkipDays();
    }

    /** @return array<int, array<string, true>> */
    private function leaveSkipDays(): array
    {
        $days = [];
        $stored = DB::table('leaves')
            ->where('status', '!=', LeaveStatus::Rejected->value)
            ->get(['guard_id', 'start_date', 'end_date']);
        foreach ($stored as $leave) {
            $cursor = $leave->start_date;
            while ($cursor <= $leave->end_date) {
                $days[$leave->guard_id][$cursor] = true;
                $cursor = $this->addDays($cursor, 1);
            }
        }

        return $days;
    }

    /**
     * @param  list<array{id: int, region_id: int, hired: string, left: string|null, active: bool}>  $guards
     * @param  list<array{id: int, region_id: int, supervisor_id: int|null, opened: string, client_id: int, day: int, night: int}>  $sites
     * @return list<array<string, mixed>>
     */
    private function deploymentPlans(array $guards, array $sites): array
    {
        $byRegion = [];
        foreach ($sites as $site) {
            $byRegion[$site['region_id']][] = $site;
        }

        $segmentDays = 200;
        $plans = [];
        for ($pass = 0; $pass < 5; $pass++) {
            $plans = $this->buildPlans($guards, $byRegion, $segmentDays);
            $estimate = $this->countPlanDays($plans);
            $deployments = count($plans);
            if ($deployments >= 5_000 && $deployments <= 7_000) {
                break;
            }
            if ($estimate < 1) {
                break;
            }
            $segmentDays = $deployments > 7_000
                ? (int) ceil($estimate / 5_500)
                : max(21, (int) floor($estimate / 6_500));
        }

        $this->assertPlansWithinManpower($plans, $sites);
        $this->command?->info('Deployment plan: '.count($plans).' postings, about '.$this->countPlanDays($plans).' duties, each site kept within its day and night requirement.');

        return $plans;
    }

    /**
     * Fill each site's day and night posts up to the contracted requirement.
     * A guard holds one post at a time. A post is never stacked past required_day_guards
     * or required_night_guards, which is the check DeploymentService enforces.
     *
     * @param  list<array{id: int, region_id: int, hired: string, left: string|null, active: bool}>  $guards
     * @param  array<int, list<array{id: int, region_id: int, supervisor_id: int|null, opened: string, day: int, night: int}>>  $sitesByRegion
     * @return list<array<string, mixed>>
     */
    private function buildPlans(array $guards, array $sitesByRegion, int $segmentDays): array
    {
        $horizon = $this->seedEnd()->toDateString();
        $plans = [];
        $segmentDays = max(21, $segmentDays);

        $guardsByRegion = [];
        foreach ($guards as $guard) {
            $guardsByRegion[$guard['region_id']][] = $guard;
        }

        foreach ($sitesByRegion as $regionId => $sites) {
            $pool = $guardsByRegion[$regionId] ?? [];
            if ($pool === []) {
                continue;
            }
            usort($pool, fn (array $a, array $b): int => [$a['hired'], $a['id']] <=> [$b['hired'], $b['id']]);

            $freeFrom = [];
            foreach ($pool as $guard) {
                $freeFrom[$guard['id']] = $guard['hired'];
            }

            $lanes = [];
            foreach ($sites as $site) {
                for ($slot = 0; $slot < max(0, (int) $site['day']); $slot++) {
                    $lanes[] = ['site' => $site, 'period' => DeploymentShiftType::Day->value, 'cursor' => $site['opened']];
                }
                for ($slot = 0; $slot < max(0, (int) $site['night']); $slot++) {
                    $lanes[] = ['site' => $site, 'period' => DeploymentShiftType::Night->value, 'cursor' => $site['opened']];
                }
            }

            $steps = 0;
            while ($steps++ < 200000) {
                $laneIndex = null;
                $earliest = '9999-99-99';
                foreach ($lanes as $index => $lane) {
                    if ($lane['cursor'] <= $horizon && $lane['cursor'] < $earliest) {
                        $earliest = $lane['cursor'];
                        $laneIndex = $index;
                    }
                }
                if ($laneIndex === null) {
                    break;
                }

                $cursor = $lanes[$laneIndex]['cursor'];
                $guard = $this->claimGuard($pool, $freeFrom, $cursor, $horizon);
                if ($guard === null) {
                    $jump = $this->earliestLaterOpening($pool, $freeFrom, $cursor, $horizon);
                    $destination = ($jump === null || $jump <= $cursor)
                        ? $this->addDays($horizon, 1)
                        : $jump;
                    foreach ($lanes as $index => $lane) {
                        if ($lane['cursor'] === $cursor) {
                            $lanes[$index]['cursor'] = $destination;
                        }
                    }

                    continue;
                }

                $until = $this->guardWindowEnd($guard, $horizon);
                $length = min($segmentDays, $this->inclusiveDays($cursor, $until));
                if ($length < 1) {
                    $freeFrom[$guard['id']] = $this->addDays($horizon, 1);

                    continue;
                }
                $postEnd = $this->addDays($cursor, $length - 1);
                $isCurrent = $guard['active'] && $postEnd === $horizon;

                $plans[] = [
                    'guard_id' => $guard['id'],
                    'site_id' => $lanes[$laneIndex]['site']['id'],
                    'region_id' => (int) $regionId,
                    'supervisor_id' => $lanes[$laneIndex]['site']['supervisor_id'],
                    'shift_type' => $lanes[$laneIndex]['period'],
                    'status' => $isCurrent ? DeploymentStatus::Active->value : DeploymentStatus::Ended->value,
                    'start_date' => $cursor,
                    'end_date' => $isCurrent ? null : $postEnd,
                    'is_current' => $isCurrent,
                    'is_temporary' => false,
                    'duty_type' => ShiftType::Normal->value,
                    'transfer_from' => null,
                    'work_end' => $postEnd,
                ];

                $next = $this->addDays($postEnd, 1);
                $freeFrom[$guard['id']] = $next;
                $lanes[$laneIndex]['cursor'] = $next;
            }

            foreach ($lanes as $lane) {
                if ($lane['cursor'] <= $horizon) {
                    throw new \RuntimeException('Posting plan did not finish within the step limit.');
                }
            }
        }

        return $plans;
    }

    /**
     * @param  list<array{id: int, hired: string, left: string|null, active: bool}>  $pool
     * @param  array<int, string>  $freeFrom
     */
    private function claimGuard(array $pool, array $freeFrom, string $cursor, string $horizon): ?array
    {
        $best = null;
        foreach ($pool as $guard) {
            $until = $this->guardWindowEnd($guard, $horizon);
            $available = $freeFrom[$guard['id']];
            if ($available > $cursor || $available > $until || $until < $cursor) {
                continue;
            }
            if ($best === null
                || $available < $freeFrom[$best['id']]
                || ($available === $freeFrom[$best['id']] && $guard['id'] < $best['id'])) {
                $best = $guard;
            }
        }

        return $best;
    }

    /**
     * @param  list<array{id: int, hired: string, left: string|null, active: bool}>  $pool
     * @param  array<int, string>  $freeFrom
     */
    private function earliestLaterOpening(array $pool, array $freeFrom, string $cursor, string $horizon): ?string
    {
        $next = null;
        foreach ($pool as $guard) {
            $until = $this->guardWindowEnd($guard, $horizon);
            $available = $freeFrom[$guard['id']];
            if ($available <= $cursor || $available > $until) {
                continue;
            }
            if ($next === null || $available < $next) {
                $next = $available;
            }
        }

        return $next;
    }

    /** @param  array{active: bool, left: string|null, hired: string}  $guard */
    private function guardWindowEnd(array $guard, string $horizon): string
    {
        return $guard['active'] ? $horizon : ($guard['left'] ?? $guard['hired']);
    }

    /**
     * @param  list<array<string, mixed>>  $plans
     * @param  list<array{id: int, day: int, night: int}>  $sites
     */
    private function assertPlansWithinManpower(array $plans, array $sites): void
    {
        $capacity = [];
        foreach ($sites as $site) {
            $capacity[$site['id']] = [
                DeploymentShiftType::Day->value => (int) $site['day'],
                DeploymentShiftType::Night->value => (int) $site['night'],
            ];
        }

        $siteEvents = [];
        $guardEvents = [];
        $currentByGuard = [];

        foreach ($plans as $plan) {
            if ($plan['is_current']) {
                $currentByGuard[$plan['guard_id']] = ($currentByGuard[$plan['guard_id']] ?? 0) + 1;
                if ($currentByGuard[$plan['guard_id']] > 1) {
                    throw new \RuntimeException('Guard '.$plan['guard_id'].' has more than one current posting.');
                }
            }

            $periods = $plan['shift_type'] === DeploymentShiftType::Rotating->value
                ? [DeploymentShiftType::Day->value, DeploymentShiftType::Night->value]
                : [$plan['shift_type']];
            $release = $this->addDays($plan['work_end'], 1);
            foreach ($periods as $period) {
                $siteEvents[$plan['site_id']][$period][] = [$plan['start_date'], 1];
                $siteEvents[$plan['site_id']][$period][] = [$release, -1];
            }
            $guardEvents[$plan['guard_id']][] = [$plan['start_date'], 1];
            $guardEvents[$plan['guard_id']][] = [$release, -1];
        }

        foreach ($siteEvents as $siteId => $byPeriod) {
            foreach ($byPeriod as $period => $events) {
                $peak = $this->peakOverlap($events);
                $limit = $capacity[$siteId][$period] ?? 0;
                if ($limit > 0 && $peak > $limit) {
                    throw new \RuntimeException('Site '.$siteId.' '.$period.' peaks at '.$peak.' against a requirement of '.$limit.'.');
                }
            }
        }

        foreach ($guardEvents as $guardId => $events) {
            if ($this->peakOverlap($events) > 1) {
                throw new \RuntimeException('Guard '.$guardId.' is posted on two sites for the same day.');
            }
        }
    }

    /** @param  list<array{0: string, 1: int}>  $events */
    private function peakOverlap(array $events): int
    {
        usort($events, fn (array $left, array $right): int => [$left[0], $left[1]] <=> [$right[0], $right[1]]);
        $running = 0;
        $peak = 0;
        foreach ($events as [$date, $delta]) {
            $running += $delta;
            if ($running > $peak) {
                $peak = $running;
            }
        }

        return $peak;
    }

    /** @param  list<array<string, mixed>>  $plans */
    private function countPlanDays(array $plans): int
    {
        $days = 0;
        foreach ($plans as $plan) {
            $count = $this->inclusiveDays($plan['start_date'], $plan['work_end']);
            $days += $plan['shift_type'] === DeploymentShiftType::Rotating->value ? $count * 2 : $count;
        }

        return $days;
    }

    /**
     * @param  list<array<string, mixed>>  $plans
     * @param  array<int, array<string, true>>  $leaveDays
     */
    private function seedDeploymentsAndShifts(array $plans, array $leaveDays): void
    {
        $existingDeployments = DB::table('deployments')
            ->get([
                'id',
                'guard_id',
                'site_id',
                'shift_type',
                'status',
                'start_date',
                'end_date',
                'is_current',
                'is_temporary',
                'duty_type',
            ])
            ->keyBy(fn (object $deployment): string => $this->deploymentIdentity($deployment));

        $pending = [];
        $now = now()->toDateTimeString();

        foreach ($plans as $plan) {
            $stored = $existingDeployments->get($this->deploymentIdentity($plan));

            if ($stored === null) {
                $pending[] = $plan;

                continue;
            }

            $this->alignStoredDeployment($stored, $plan, $now);
        }

        if ($pending !== []) {
            $rows = [];
            foreach ($pending as $plan) {
                $rows[] = [
                    'guard_id' => $plan['guard_id'],
                    'site_id' => $plan['site_id'],
                    'region_id' => $plan['region_id'],
                    'supervisor_id' => $plan['supervisor_id'],
                    'shift_type' => $plan['shift_type'],
                    'status' => $plan['status'],
                    'start_date' => $plan['start_date'],
                    'end_date' => $plan['end_date'],
                    'is_current' => $plan['is_current'],
                    'is_temporary' => $plan['is_temporary'],
                    'duty_type' => $plan['duty_type'],
                    'notes' => $plan['is_temporary'] ? 'Overtime cover for a short post.' : 'Posting opened '.$plan['start_date'].'.',
                    'created_by' => Auth::id(),
                    'created_at' => $plan['start_date'].' 07:00:00',
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('deployments')->insert($chunk);
            }
            $this->seedTransfers($pending);
        }

        $this->command?->info('Writing duties...');
        $deployments = DB::table('deployments')->orderBy('id')->get();
        $buffer = [];
        $written = 0;
        $actor = Auth::id();

        foreach ($deployments as $deployment) {
            $end = $deployment->end_date ?: $this->seedEnd()->toDateString();
            $periods = $deployment->shift_type === DeploymentShiftType::Rotating->value
                ? [ShiftPeriod::Day->value, ShiftPeriod::Night->value]
                : [$deployment->shift_type === DeploymentShiftType::Night->value ? ShiftPeriod::Night->value : ShiftPeriod::Day->value];
            $cursor = $deployment->start_date;
            $dayIndex = 0;
            while ($cursor <= $end) {
                if (! isset($leaveDays[$deployment->guard_id][$cursor])) {
                    foreach ($periods as $period) {
                        $slotKey = $deployment->guard_id.'|'.$cursor.'|'.$period;
                        $kind = ($deployment->id + $dayIndex) % 100;
                        $recorded = $kind !== 0 && $kind !== 1;
                        $status = $recorded ? ShiftStatus::Recorded->value : ($kind === 0 ? ShiftStatus::Missed->value : ShiftStatus::Cancelled->value);
                        $type = $deployment->is_temporary || ($recorded && $kind % 5 === 0)
                            ? ShiftType::Overtime->value
                            : ShiftType::Normal->value;
                        $night = $period === ShiftPeriod::Night->value;
                        $buffer[] = [
                            'reference' => 'SH'.$deployment->guard_id.'-'.str_replace('-', '', $cursor).'-'.substr($period, 0, 1),
                            'guard_id' => $deployment->guard_id,
                            'site_id' => $deployment->site_id,
                            'region_id' => $deployment->region_id,
                            'supervisor_id' => $deployment->supervisor_id,
                            'deployment_id' => $deployment->id,
                            'shift_date' => $cursor,
                            'starts_at' => $cursor.($night ? ' 18:00:00' : ' 06:00:00'),
                            'ends_at' => ($night ? $this->addDays($cursor, 1) : $cursor).($night ? ' 06:00:00' : ' 18:00:00'),
                            'period' => $period,
                            'shift_type' => $type,
                            'guard_classification' => GuardClassification::Unarmed->value,
                            'status' => $status,
                            'same_shift_slot' => $recorded ? $slotKey : null,
                            'is_overnight' => $night,
                            'created_by' => $actor,
                            'created_at' => $cursor.' 06:00:00',
                            'updated_at' => $cursor.' 18:00:00',
                        ];
                        $written++;
                        if (count($buffer) >= 400) {
                            DB::table('shifts')->insertOrIgnore($buffer);
                            $buffer = [];
                            if ($written % 100000 === 0) {
                                $this->command?->info('Duties prepared: '.$written.'.');
                            }
                        }
                    }
                }
                $cursor = $this->addDays($cursor, 1);
                $dayIndex++;
            }
        }

        if ($buffer !== []) {
            DB::table('shifts')->insertOrIgnore($buffer);
        }

        $this->command?->info('Duties stored: '.DB::table('shifts')->count().'.');
    }

    /** @param  object|array<string, mixed>  $deployment */
    private function deploymentIdentity(object|array $deployment): string
    {
        $value = function (string $field) use ($deployment): mixed {
            return is_array($deployment) ? ($deployment[$field] ?? null) : ($deployment->{$field} ?? null);
        };

        return implode('|', [
            (int) $value('guard_id'),
            (int) $value('site_id'),
            (string) $value('shift_type'),
            substr((string) $value('start_date'), 0, 10),
            (int) $value('is_temporary'),
            (string) ($value('duty_type') ?? ''),
        ]);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function alignStoredDeployment(object $stored, array $plan, string $now): void
    {
        $current = (bool) $plan['is_current'];
        $endDate = $current ? null : ($plan['end_date'] !== null ? substr((string) $plan['end_date'], 0, 10) : null);
        $storedEnd = $stored->end_date !== null ? substr((string) $stored->end_date, 0, 10) : null;
        $status = (string) $stored->status;

        if ($current) {
            $status = DeploymentStatus::Active->value;
        } elseif ($status !== DeploymentStatus::Transferred->value) {
            $status = (string) $plan['status'];
        }

        if ($storedEnd === $endDate && (bool) $stored->is_current === $current && (string) $stored->status === $status) {
            return;
        }

        DB::table('deployments')->where('id', $stored->id)->update([
            'status' => $status,
            'end_date' => $endDate,
            'is_current' => $current,
            'updated_at' => $now,
        ]);
    }

    /** @param  list<array<string, mixed>>  $plans */
    private function seedTransfers(array $plans): void
    {
        if (DB::table('deployment_transfers')->count() >= 40) {
            return;
        }

        $rows = DB::table('deployments')->orderBy('id')->get()->values();
        $byGuard = [];
        foreach ($rows as $row) {
            $byGuard[$row->guard_id][] = $row;
        }

        $transfers = [];
        $actor = User::query()->where('email', 'shifts@platinumsecurity.local')->value('id');
        foreach ($byGuard as $guardId => $guardRows) {
            if (count($transfers) >= 50) {
                break;
            }
            for ($index = 0; $index < count($guardRows) - 1 && count($transfers) < 50; $index++) {
                $from = $guardRows[$index];
                $to = $guardRows[$index + 1];
                if ((int) $from->site_id === (int) $to->site_id || $from->is_temporary || $to->is_temporary || $from->end_date === null) {
                    continue;
                }
                if ($this->addDays($from->end_date, 1) !== $to->start_date) {
                    continue;
                }
                DB::table('deployments')->where('id', $from->id)->update([
                    'status' => DeploymentStatus::Transferred->value,
                    'is_current' => false,
                    'end_date' => $from->end_date,
                ]);
                $transfers[] = [
                    'guard_id' => $guardId,
                    'from_deployment_id' => $from->id,
                    'to_deployment_id' => $to->id,
                    'from_site_id' => $from->site_id,
                    'to_site_id' => $to->site_id,
                    'reason' => 'Client requested a change of post.',
                    'notes' => 'Transfer recorded in '.$this->yearOf($to->start_date).'.',
                    'transferred_by' => $actor,
                    'effective_at' => $to->start_date.' 06:00:00',
                    'created_at' => $to->start_date.' 06:00:00',
                    'updated_at' => $to->start_date.' 06:00:00',
                ];
            }
        }

        if ($transfers !== []) {
            DB::table('deployment_transfers')->insert($transfers);
        }

        $this->command?->info('Transfers: '.count($transfers).'.');
    }

    private function seedReplacements(): void
    {
        if (DB::table('shift_replacements')->count() >= 10) {
            return;
        }

        $span = max(1, (int) DB::table('shifts')->max('id'));
        $step = max(1, intdiv($span, 30));
        $originals = DB::table('shifts')
            ->where('status', ShiftStatus::Recorded->value)
            ->whereRaw('MOD(id, ?) = 0', [$step])
            ->limit(15)
            ->get();
        $actor = User::query()->where('email', 'shifts@platinumsecurity.local')->value('id');
        $made = 0;

        foreach ($originals as $original) {
            $replacementGuard = DB::table('guards')
                ->where('region_id', $original->region_id)
                ->where('id', '!=', $original->guard_id)
                ->where('employment_status', EmploymentStatus::Active->value)
                ->whereNotExists(function ($query) use ($original): void {
                    $query->selectRaw('1')
                        ->from('shifts')
                        ->whereColumn('shifts.guard_id', 'guards.id')
                        ->where('shifts.shift_date', $original->shift_date)
                        ->where('shifts.period', $original->period)
                        ->whereNotNull('shifts.same_shift_slot');
                })
                ->value('id');
            if ($replacementGuard === null) {
                continue;
            }

            DB::table('shifts')->where('id', $original->id)->update([
                'status' => ShiftStatus::Replaced->value,
                'same_shift_slot' => null,
            ]);
            $replacementId = DB::table('shifts')->insertGetId([
                'reference' => 'SHR'.$original->id,
                'guard_id' => $replacementGuard,
                'site_id' => $original->site_id,
                'region_id' => $original->region_id,
                'supervisor_id' => $original->supervisor_id,
                'deployment_id' => null,
                'replaced_shift_id' => $original->id,
                'shift_date' => $original->shift_date,
                'starts_at' => $original->starts_at,
                'ends_at' => $original->ends_at,
                'period' => $original->period,
                'shift_type' => ShiftType::Replacement->value,
                'guard_classification' => GuardClassification::Unarmed->value,
                'status' => ShiftStatus::Recorded->value,
                'same_shift_slot' => $replacementGuard.'|'.$original->shift_date.'|'.$original->period,
                'is_overnight' => $original->is_overnight,
                'notes' => 'Relief for an absent guard.',
                'created_by' => $actor,
                'created_at' => $original->starts_at,
                'updated_at' => $original->starts_at,
            ]);
            DB::table('shift_replacements')->insert([
                'original_shift_id' => $original->id,
                'original_guard_id' => $original->guard_id,
                'replacement_guard_id' => $replacementGuard,
                'replacement_shift_id' => $replacementId,
                'site_id' => $original->site_id,
                'reason' => 'absence',
                'notes' => 'Same-day replacement.',
                'authorized_by' => $actor,
                'replaced_at' => $original->starts_at,
                'created_by' => $actor,
                'created_at' => $original->starts_at,
                'updated_at' => $original->starts_at,
            ]);
            $made++;
        }

        $this->command?->info('Replacements: '.$made.'.');
    }

    private function seedAttendanceAndAbsence(): void
    {
        if (DB::table('attendances')->count() < 1000) {
            $actor = User::query()->where('email', 'shifts@platinumsecurity.local')->value('id');
            $shifts = DB::table('shifts')
                ->where('status', ShiftStatus::Recorded->value)
                ->whereRaw('MOD(id, 1100) = 1')
                ->limit(1200)
                ->get(['id', 'guard_id', 'site_id', 'starts_at']);
            $rows = [];
            foreach ($shifts as $shift) {
                $rows[] = [
                    'guard_id' => $shift->guard_id,
                    'site_id' => $shift->site_id,
                    'shift_id' => $shift->id,
                    'event_type' => 'check_in',
                    'source' => 'manual',
                    'occurred_at' => $shift->starts_at,
                    'notes' => 'Arrival recorded for the duty.',
                    'recorded_by' => $actor,
                    'created_by' => $actor,
                    'created_at' => $shift->starts_at,
                    'updated_at' => $shift->starts_at,
                ];
            }
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('attendances')->insert($chunk);
            }
            $this->command?->info('Attendance: '.count($rows).'.');
        }

        if (DB::table('absences')->count() < 200) {
            $actor = User::query()->where('email', 'shifts@platinumsecurity.local')->value('id');
            $missed = DB::table('shifts')
                ->where('status', ShiftStatus::Missed->value)
                ->limit(400)
                ->get(['id', 'guard_id', 'site_id', 'shift_date']);
            $rows = [];
            foreach ($missed as $shift) {
                $rows[] = [
                    'guard_id' => $shift->guard_id,
                    'site_id' => $shift->site_id,
                    'shift_id' => $shift->id,
                    'absence_date' => $shift->shift_date,
                    'reason' => 'no_show',
                    'action_taken' => 'Marked absent for the duty.',
                    'replacement_required' => false,
                    'notes' => 'No arrival was recorded.',
                    'reported_by' => $actor,
                    'reported_at' => $shift->shift_date.' 09:00:00',
                    'created_by' => $actor,
                    'created_at' => $shift->shift_date.' 09:00:00',
                    'updated_at' => $shift->shift_date.' 09:00:00',
                ];
            }
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('absences')->insert($chunk);
            }
        }
    }

    private function refreshGuardPostings(): void
    {
        DB::update(
            'UPDATE guards g
             LEFT JOIN deployments d ON d.guard_id = g.id AND d.is_current = 1 AND d.is_temporary = 0
             LEFT JOIN supervisors s ON s.guard_id = g.id
             SET g.current_site_id = d.site_id,
                 g.current_supervisor_id = d.supervisor_id,
                 g.operational_status = CASE
                    WHEN s.id IS NOT NULL THEN ?
                    WHEN g.employment_status <> ? THEN ?
                    WHEN d.id IS NULL THEN ?
                    ELSE ?
                 END',
            [
                OperationalStatus::OffDuty->value,
                EmploymentStatus::Active->value,
                OperationalStatus::OffDuty->value,
                OperationalStatus::AwaitingDeployment->value,
                OperationalStatus::OnDuty->value,
            ],
        );
    }

    private function seedPayroll(): void
    {
        $payroll = app(PayrollRunService::class);
        $finance = User::query()->where('email', 'finance@platinumsecurity.local')->first();
        $director = User::query()->where('email', 'md@platinumsecurity.local')->first();
        Auth::login($finance ?? Auth::user());
        $cursor = $this->seedStart()->copy()->startOfMonth();
        $last = PayrollRunService::lastClosedPeriod()->startOfMonth();
        $cancel = ['2026-01', '2026-02', '2026-03', '2026-04'];

        while ($cursor->lte($last)) {
            $label = $cursor->format('Y-m');
            $exists = DB::table('payroll_runs')
                ->where('period_year', $cursor->year)
                ->where('period_month', $cursor->month)
                ->where('status', '!=', 'cancelled')
                ->exists();
            if ($exists) {
                $cursor->addMonth();

                continue;
            }

            if (in_array($label, $cancel, true)) {
                $draft = $payroll->createDraft([
                    'period_year' => $cursor->year,
                    'period_month' => $cursor->month,
                    'notes' => 'Superseded draft for '.$cursor->format('F Y').'.',
                ], $finance);
                $payroll->cancel($draft);
            }

            $run = $payroll->createDraft([
                'period_year' => $cursor->year,
                'period_month' => $cursor->month,
                'notes' => 'Company payroll for '.$cursor->format('F Y').'.',
            ], $finance);
            $run = $payroll->calculate($run);
            if ($run->payslips()->count() === 0) {
                $payroll->cancel($run);
                $cursor->addMonth();

                continue;
            }
            $run = $payroll->submit($run, $finance);
            $run = $payroll->approve($run, $director);
            if ($cursor->lt($last)) {
                $payroll->markPaid($run, $finance);
            }
            $this->command?->info('Payroll closed: '.$cursor->format('F Y').'.');
            $cursor->addMonth();
        }

        $this->copyCancelledPayslips();
    }

    private function copyCancelledPayslips(): void
    {
        $columns = array_values(array_filter(
            Schema::getColumnListing('payroll_payslips'),
            fn (string $column) => $column !== 'id',
        ));
        $cancelledRuns = DB::table('payroll_runs')->where('status', 'cancelled')->orderBy('id')->get();

        foreach ($cancelledRuns as $cancelled) {
            if (DB::table('payroll_payslips')->where('payroll_run_id', $cancelled->id)->exists()) {
                continue;
            }

            $source = DB::table('payroll_runs')
                ->where('period_year', $cancelled->period_year)
                ->where('period_month', $cancelled->period_month)
                ->where('status', '!=', 'cancelled')
                ->first();
            if ($source === null) {
                continue;
            }

            DB::table('payroll_payslips')
                ->where('payroll_run_id', $source->id)
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($cancelled, $columns): void {
                    $insert = [];
                    foreach ($rows as $row) {
                        $data = ['payroll_run_id' => $cancelled->id];
                        foreach ($columns as $column) {
                            if ($column === 'payroll_run_id') {
                                continue;
                            }
                            $data[$column] = $row->{$column};
                        }
                        $data['notes'] = 'Superseded draft replaced by the approved payroll.';
                        $insert[] = $data;
                    }
                    if ($insert !== []) {
                        DB::table('payroll_payslips')->insert($insert);
                    }
                });

            $totals = DB::table('payroll_payslips')
                ->where('payroll_run_id', $cancelled->id)
                ->selectRaw('COUNT(*) as guard_count, COALESCE(SUM(gross_pay), 0) as gross_total, COALESCE(SUM(total_deductions), 0) as deductions_total, COALESCE(SUM(net_pay), 0) as net_total')
                ->first();
            DB::table('payroll_runs')->where('id', $cancelled->id)->update([
                'guard_count' => (int) ($totals->guard_count ?? 0),
                'gross_total' => $totals->gross_total ?? 0,
                'deductions_total' => $totals->deductions_total ?? 0,
                'net_total' => $totals->net_total ?? 0,
            ]);
        }
    }

    /** @param  list<array{id: int, client_id: int, opened: string, day: int, night: int}>  $sites */
    private function seedBillingProfiles(array $sites): void
    {
        $this->command?->info('Billing profiles: '.DB::table('billing_profiles')->count().'.');
    }

    /**
     * @param  list<array{id: int, opened: string}>  $clients
     * @param  list<array{id: int, client_id: int, opened: string, day: int, night: int}>  $sites
     */
    private function seedInvoices(array $clients, array $sites): void
    {
        if (DB::table('invoices')->count() >= 900) {
            return;
        }

        $financeId = User::query()->where('email', 'finance@platinumsecurity.local')->value('id');
        $lastClosed = PayrollRunService::lastClosedPeriod()->startOfMonth();
        $byClient = [];
        foreach ($sites as $site) {
            $byClient[$site['client_id']][] = $site;
        }

        $slots = [];
        foreach ($clients as $client) {
            $clientSites = $byClient[$client['id']] ?? [];
            if ($clientSites === []) {
                continue;
            }
            $first = collect($clientSites)->min('opened');
            $month = Carbon::parse($first)->startOfMonth();
            while ($month->lte($lastClosed)) {
                $openSites = array_values(array_filter(
                    $clientSites,
                    fn (array $site) => substr($site['opened'], 0, 7) <= $month->format('Y-m'),
                ));
                if ($openSites !== []) {
                    $slots[] = ['client' => $client, 'month' => $month->copy(), 'sites' => $openSites];
                }
                $month->addMonth();
            }
        }

        if (count($slots) > 1100) {
            $keep = 1050;
            $step = count($slots) / $keep;
            $picked = [];
            for ($i = 0; $i < $keep; $i++) {
                $picked[] = $slots[(int) floor($i * $step)];
            }
            $slots = $picked;
        }

        $partialUsed = false;
        $sequence = 0;
        foreach ($slots as $slot) {
            $sequence++;
            $month = $slot['month'];
            $reference = sprintf('INV-%d-%02d-%04d', $month->year, $month->month, $sequence);
            if (DB::table('invoices')->where('reference', $reference)->exists()) {
                continue;
            }

            $amount = 0;
            foreach ($slot['sites'] as $site) {
                $amount += 450000 * max(1, (int) $site['day'] + (int) $site['night']);
            }
            $issue = $month->copy()->endOfMonth()->toDateString();
            $due = $month->copy()->endOfMonth()->addDays(14)->toDateString();
            $latest = $month->equalTo($lastClosed);
            $overdue = ! $latest && $sequence % 17 === 0;
            $partial = ! $latest && ! $overdue && ! $partialUsed;
            if ($partial) {
                $partialUsed = true;
            }

            $status = $latest
                ? InvoiceStatus::Issued
                : ($overdue ? InvoiceStatus::Overdue : ($partial ? InvoiceStatus::PartiallyPaid : InvoiceStatus::Paid));
            $paid = $status === InvoiceStatus::Paid ? $amount : ($partial ? (int) round($amount / 2) : 0);
            $invoiceId = DB::table('invoices')->insertGetId([
                'reference' => $reference,
                'client_id' => $slot['client']['id'],
                'site_id' => $slot['sites'][0]['id'],
                'status' => $status->value,
                'period_start' => $month->toDateString(),
                'period_end' => $month->copy()->endOfMonth()->toDateString(),
                'issue_date' => $issue,
                'due_date' => $due,
                'currency' => 'UGX',
                'subtotal' => $amount,
                'tax_amount' => 0,
                'total' => $amount,
                'amount_paid' => $paid,
                'balance' => $amount - $paid,
                'notes' => 'Monthly guarding invoice for '.$month->format('F Y').'.',
                'approved_by' => $financeId,
                'approved_at' => $issue.' 10:00:00',
                'created_by' => $financeId,
                'created_at' => $issue.' 10:00:00',
                'updated_at' => $issue.' 10:00:00',
            ]);
            DB::table('invoice_lines')->insert([
                'invoice_id' => $invoiceId,
                'site_id' => $slot['sites'][0]['id'],
                'description' => 'Guarding posts for '.$month->format('F Y'),
                'quantity' => 1,
                'unit_price' => $amount,
                'line_total' => $amount,
                'sort_order' => 1,
                'created_at' => $issue.' 10:00:00',
                'updated_at' => $issue.' 10:00:00',
            ]);
            if ($paid > 0) {
                $paidOn = $month->copy()->endOfMonth()->addDays(10)->toDateString();
                if ($paidOn > $this->seedEnd()->toDateString()) {
                    $paidOn = $this->seedEnd()->toDateString();
                }
                DB::table('payments')->insert([
                    'reference' => 'PAY-'.$reference,
                    'invoice_id' => $invoiceId,
                    'client_id' => $slot['client']['id'],
                    'amount' => $paid,
                    'payment_date' => $paidOn,
                    'method' => 'bank_transfer',
                    'external_reference' => 'BANK-'.$sequence,
                    'notes' => $partial ? 'Part payment on the monthly invoice.' : 'Invoice settled in full.',
                    'recorded_by' => $financeId,
                    'created_by' => $financeId,
                    'created_at' => $paidOn.' 11:00:00',
                    'updated_at' => $paidOn.' 11:00:00',
                ]);
            }
        }

        $this->addSupplementalInvoices();

        $this->command?->info('Invoices: '.DB::table('invoices')->count().', payments: '.DB::table('payments')->count().'.');
    }

    private function addSupplementalInvoices(): void
    {
        $target = 1000;
        $have = (int) DB::table('invoices')->count();
        if ($have >= $target) {
            return;
        }

        $financeId = User::query()->where('email', 'finance@platinumsecurity.local')->value('id');
        $base = DB::table('invoices')
            ->where('status', 'paid')
            ->orderBy('id')
            ->get(['id', 'client_id', 'site_id', 'period_start', 'period_end', 'issue_date', 'due_date', 'total']);
        $sequence = (int) DB::table('invoices')->max('id');

        foreach ($base as $invoice) {
            if ($have >= $target) {
                break;
            }
            if ($sequence % 5 !== 0) {
                $sequence++;

                continue;
            }

            $amount = max(450000, (int) round(((float) $invoice->total) * 0.1));
            $reference = 'INV-OT-'.$invoice->id;
            if (DB::table('invoices')->where('reference', $reference)->exists()) {
                $sequence++;

                continue;
            }

            $invoiceId = DB::table('invoices')->insertGetId([
                'reference' => $reference,
                'client_id' => $invoice->client_id,
                'site_id' => $invoice->site_id,
                'status' => 'paid',
                'period_start' => $invoice->period_start,
                'period_end' => $invoice->period_end,
                'issue_date' => $invoice->issue_date,
                'due_date' => $invoice->due_date,
                'currency' => 'UGX',
                'subtotal' => $amount,
                'tax_amount' => 0,
                'total' => $amount,
                'amount_paid' => $amount,
                'balance' => 0,
                'notes' => 'Supplemental overtime invoice for the same guarding month.',
                'approved_by' => $financeId,
                'approved_at' => $invoice->issue_date.' 15:00:00',
                'created_by' => $financeId,
                'created_at' => $invoice->issue_date.' 15:00:00',
                'updated_at' => $invoice->issue_date.' 15:00:00',
            ]);
            DB::table('invoice_lines')->insert([
                'invoice_id' => $invoiceId,
                'site_id' => $invoice->site_id,
                'description' => 'Overtime and special-duty posts',
                'quantity' => 1,
                'unit_price' => $amount,
                'line_total' => $amount,
                'sort_order' => 1,
                'created_at' => $invoice->issue_date.' 15:00:00',
                'updated_at' => $invoice->issue_date.' 15:00:00',
            ]);
            DB::table('payments')->insert([
                'reference' => 'PAY-'.$reference,
                'invoice_id' => $invoiceId,
                'client_id' => $invoice->client_id,
                'amount' => $amount,
                'payment_date' => $invoice->due_date,
                'method' => 'bank_transfer',
                'external_reference' => 'BANK-OT-'.$invoice->id,
                'notes' => 'Supplemental invoice settled in full.',
                'recorded_by' => $financeId,
                'created_by' => $financeId,
                'created_at' => $invoice->due_date.' 11:00:00',
                'updated_at' => $invoice->due_date.' 11:00:00',
            ]);
            $have++;
            $sequence++;
        }
    }

    private function seedAuditAndNotifications(): void
    {
        $users = User::query()->orderBy('id')->pluck('id')->all();
        if ($users === []) {
            return;
        }

        $since = $this->seedEnd()->copy()->subDays(90)->toDateTimeString();
        $readBefore = $this->seedEnd()->copy()->subDays(14)->toDateTimeString();
        $userCount = count($users);
        $states = [];

        DB::table('audit_logs')
            ->where('created_at', '>=', $since)
            ->orderBy('id')
            ->select(['id', 'created_at'])
            ->chunkById(500, function ($audits) use (&$states, $users, $userCount, $readBefore): void {
                foreach ($audits as $audit) {
                    $recipient = $users[$audit->id % $userCount];
                    $states[] = [
                        'user_id' => $recipient,
                        'audit_log_id' => $audit->id,
                        'priority' => 'normal',
                        'delivery_status' => 'delivered',
                        'delivered_at' => $audit->created_at,
                        'read_at' => $audit->created_at < $readBefore ? $audit->created_at : null,
                        'dismissed_at' => null,
                        'pinned_unread' => false,
                        'created_at' => $audit->created_at,
                        'updated_at' => $audit->created_at,
                    ];
                }

                if ($states !== []) {
                    DB::table('notification_states')->insertOrIgnore($states);
                    $states = [];
                }
            });

        $this->command?->info('Audit records: '.DB::table('audit_logs')->count().', notifications: '.DB::table('notification_states')->count().'.');
    }

    /** @param  list<array{id: int, hired: string}>  $guards */
    private function seedUniformExemptions(array $guards): void
    {
        $hr = User::query()->where('email', 'hr@platinumsecurity.local')->first();
        if ($hr === null) {
            return;
        }

        Auth::login($hr);
        $service = app(UniformChargeExemptionService::class);
        $recorded = 0;

        foreach ($guards as $index => $guard) {
            if ($index % 18 !== 0 || $recorded >= 16) {
                continue;
            }

            $model = Guard::query()->find($guard['id']);
            if ($model === null) {
                continue;
            }

            $from = $this->addDays($guard['hired'], 30);
            if ($from > $this->seedEnd()->toDateString()) {
                continue;
            }

            $service->record(
                $model,
                UniformChargeStatus::Exempt,
                Carbon::parse($from),
                'Uniform was issued at engagement and is not charged again.',
                'Fictional exemption for payroll testing.',
                $hr,
            );
            $recorded++;
        }

        $this->command?->info('Uniform exemptions: '.$recorded.'.');
    }

    private function noteRosterVariety(): void
    {
        $ids = Guard::query()
            ->where('employment_status', EmploymentStatus::Active->value)
            ->whereDoesntHave('supervisorProfile')
            ->whereDoesntHave('deployments', fn ($query) => $query->where('is_current', true))
            ->orderBy('id')
            ->limit(8)
            ->pluck('id');
        $statuses = [
            OperationalStatus::OnLeave,
            OperationalStatus::Training,
            OperationalStatus::SickUnavailable,
            OperationalStatus::OnLeave,
        ];
        $service = app(GuardService::class);

        foreach ($ids as $offset => $id) {
            $guard = Guard::query()->find($id);
            if ($guard === null) {
                continue;
            }

            $service->updateGuard($guard, [
                'operational_status' => $statuses[$offset % count($statuses)]->value,
            ], 'roster_variety');
        }
    }

    private function seedIncidents(): void
    {
        if (DB::table('incidents')->count() >= 50) {
            return;
        }

        $posts = DB::table('deployments')->orderBy('id')->get(['guard_id', 'site_id', 'start_date']);
        if ($posts->isEmpty()) {
            return;
        }

        $types = IncidentType::cases();
        $severities = IncidentSeverity::cases();
        $statuses = [IncidentStatus::Closed, IncidentStatus::Resolved, IncidentStatus::Investigating, IncidentStatus::Reported];
        $titles = [
            'Late arrival at the gate',
            'Client reported a missed patrol',
            'Visitor refused to sign in',
            'Perimeter light not working',
            'Unattended package at reception',
            'Guard absent at shift start',
            'Alarm activated in the warehouse',
            'Dispute at the vehicle gate',
        ];
        $reporter = User::query()->where('email', 'operations@platinumsecurity.local')->value('id');
        $rows = [];
        $target = 70;
        $step = max(1, intdiv($posts->count(), $target));

        for ($n = 0; $n < $target; $n++) {
            $post = $posts[min($posts->count() - 1, $n * $step)];
            $when = $post->start_date.' '.sprintf('%02d:15:00', 8 + ($n % 10));
            $rows[] = [
                'reference' => 'INC-'.$this->yearOf($post->start_date).'-'.str_pad((string) ($n + 1), 4, '0', STR_PAD_LEFT),
                'site_id' => $post->site_id,
                'guard_id' => $post->guard_id,
                'shift_id' => null,
                'incident_type' => $types[$n % count($types)]->value,
                'severity' => $severities[$n % count($severities)]->value,
                'status' => $statuses[$n % count($statuses)]->value,
                'occurred_at' => $when,
                'reported_at' => $when,
                'title' => $titles[$n % count($titles)],
                'description' => 'Fictional occurrence recorded for operations testing.',
                'action_taken' => 'Supervisor notified and the occurrence book updated.',
                'client_notified' => $n % 4 === 0,
                'reported_by' => $reporter,
                'created_by' => $reporter,
                'created_at' => $when,
                'updated_at' => $when,
            ];
        }

        DB::table('incidents')->insert($rows);
        $this->command?->info('Incidents: '.count($rows).'.');
    }

    private function assertOperationalDataset(): void
    {
        $duplicateIds = DB::table('guards')
            ->select('employment_id')
            ->whereNotNull('employment_id')
            ->groupBy('employment_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('employment_id');
        if ($duplicateIds->isNotEmpty()) {
            throw new \RuntimeException('Duplicate guard employment IDs: '.$duplicateIds->implode(', '));
        }

        $missingIds = DB::table('guards')->where(function ($query): void {
            $query->whereNull('employment_id')->orWhere('employment_id', '');
        })->count();
        if ($missingIds > 0) {
            throw new \RuntimeException($missingIds.' guards have no employment ID.');
        }

        $beforeHire = DB::table('deployments')
            ->join('guards', 'guards.id', '=', 'deployments.guard_id')
            ->whereColumn('deployments.start_date', '<', 'guards.date_employed')
            ->count();
        if ($beforeHire > 0) {
            throw new \RuntimeException($beforeHire.' deployments start before the guard was employed.');
        }

        $twoCurrent = DB::table('deployments')
            ->select('guard_id')
            ->where('is_current', true)
            ->groupBy('guard_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        if ($twoCurrent > 0) {
            throw new \RuntimeException($twoCurrent.' guards have more than one current posting.');
        }

        $sites = DB::table('sites')->where('status', SiteStatus::Active->value)->whereNull('deleted_at')->get();
        foreach ($sites as $site) {
            if ((int) $site->required_guards !== (int) $site->required_day_guards + (int) $site->required_night_guards) {
                throw new \RuntimeException('Site '.$site->code.' day and night requirements do not add up to the total.');
            }
            $hasManpower = DB::table('site_manpower_requirements')->where('site_id', $site->id)->exists();
            if (! $hasManpower) {
                throw new \RuntimeException('Site '.$site->code.' has no manpower requirement.');
            }
        }

        $orphanDeployments = DB::table('deployments')
            ->leftJoin('guards', 'guards.id', '=', 'deployments.guard_id')
            ->leftJoin('sites', 'sites.id', '=', 'deployments.site_id')
            ->where(function ($query): void {
                $query->whereNull('guards.id')->orWhereNull('sites.id');
            })
            ->count();
        if ($orphanDeployments > 0) {
            throw new \RuntimeException($orphanDeployments.' deployments point at a missing guard or site.');
        }

        $this->command?->info('Operational dataset checks passed.');
    }

    private function targetGuards(): int
    {
        return max(50, (int) config('psg.seed.guards', 300));
    }

    /** @return list<int> */
    private function regionShares(int $regions, int $total): array
    {
        $weights = [22, 18, 16, 15, 15, 14];
        $counts = [];
        $assigned = 0;
        for ($index = 0; $index < $regions; $index++) {
            $weight = $weights[$index] ?? (int) floor(100 / max(1, $regions));
            $counts[$index] = (int) floor($total * $weight / 100);
            $assigned += $counts[$index];
        }
        if ($counts !== []) {
            $counts[0] += $total - $assigned;
        }

        return $counts;
    }

    private function isFemale(int $index): bool
    {
        return $index % 3 === 0;
    }

    private function givenName(int $index): string
    {
        $pool = $this->isFemale($index) ? $this->femaleNames : $this->maleNames;

        return $pool[abs($index) % count($pool)];
    }

    private function familyName(int $index): string
    {
        return $this->lastNames[abs($index) % count($this->lastNames)];
    }

    private function printReport(): void
    {
        $lines = [
            'Users '.User::query()->count(),
            'Regions '.Region::query()->count(),
            'Clients '.DB::table('clients')->count(),
            'Sites '.Site::query()->count(),
            'Supervisors '.Supervisor::query()->count(),
            'Staff '.Staff::query()->count(),
            'Guards '.Guard::query()->count(),
            'Active guards '.Guard::query()->where('employment_status', EmploymentStatus::Active->value)->count(),
            'Inactive guards '.Guard::query()->where('employment_status', '!=', EmploymentStatus::Active->value)->count(),
            'Currently deployed '.DB::table('deployments')->where('is_current', true)->count(),
            'Available guards '.Guard::query()->where('operational_status', OperationalStatus::AwaitingDeployment->value)->count(),
            'Deployments '.DB::table('deployments')->count(),
            'Overtime deployments '.DB::table('deployments')->where('duty_type', ShiftType::Overtime->value)->count(),
            'Transfers '.DB::table('deployment_transfers')->count(),
            'Shifts '.DB::table('shifts')->count(),
            'Overtime shifts '.DB::table('shifts')->where('shift_type', ShiftType::Overtime->value)->count(),
            'Replacements '.DB::table('shift_replacements')->count(),
            'Leave '.DB::table('leaves')->count(),
            'Attendance '.DB::table('attendances')->count(),
            'Absences '.DB::table('absences')->count(),
            'Payroll runs '.DB::table('payroll_runs')->count(),
            'Payslips '.DB::table('payroll_payslips')->count(),
            'Billing profiles '.DB::table('billing_profiles')->count(),
            'Invoices '.DB::table('invoices')->count(),
            'Payments '.DB::table('payments')->count(),
            'Incidents '.DB::table('incidents')->count(),
            'Day shortage '.$this->shortage(DeploymentShiftType::Day->value, 'required_day_guards'),
            'Night shortage '.$this->shortage(DeploymentShiftType::Night->value, 'required_night_guards'),
            'Audit '.DB::table('audit_logs')->count(),
            'Notifications '.DB::table('notification_states')->count(),
        ];
        foreach ($lines as $line) {
            $this->command?->info($line);
        }
    }

    private function shortage(string $shiftType, string $column): int
    {
        $required = (int) DB::table('sites')
            ->where('status', SiteStatus::Active->value)
            ->whereNull('deleted_at')
            ->sum($column);
        $deployed = (int) DB::table('deployments')
            ->where('is_current', true)
            ->where('status', DeploymentStatus::Active->value)
            ->where('shift_type', $shiftType)
            ->count();

        return max(0, $required - $deployed);
    }

    /** @param  list<array{id: int, opened: string}>  $clients */
    private function clientOpenBy(array $clients, string $date, int $sequence): array
    {
        $open = array_values(array_filter($clients, fn (array $client) => $client['opened'] <= $date));
        if ($open === []) {
            return $clients[0];
        }

        return $open[$sequence % count($open)];
    }

    private function guardHireDate(int $index, int $total): string
    {
        $start = $this->seedStart();
        $end = $this->seedEnd()->copy()->subDays(14);
        $fraction = $total <= 1 ? 0 : $index / ($total - 1);

        return $start->copy()->addDays((int) floor($start->diffInDays($end) * $fraction))->toDateString();
    }

    private function spreadDate(int $index, int $total, string $start, string $end): string
    {
        $from = Carbon::parse($start);
        $days = max(1, $from->diffInDays(Carbon::parse($end)));
        $fraction = $total <= 1 ? 0 : $index / ($total - 1);

        return $from->copy()->addDays((int) floor($days * $fraction))->toDateString();
    }

    private function addDays(string $date, int $days): string
    {
        $sign = $days >= 0 ? '+' : '';

        return (new \DateTimeImmutable($date))->modify($sign.$days.' days')->format('Y-m-d');
    }

    private function inclusiveDays(string $start, string $end): int
    {
        if ($end < $start) {
            return 0;
        }

        return (int) (new \DateTimeImmutable($start))->diff(new \DateTimeImmutable($end))->days + 1;
    }

    private function yearOf(string $date): string
    {
        return substr($date, 0, 4);
    }
}
