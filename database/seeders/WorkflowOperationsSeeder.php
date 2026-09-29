<?php

namespace Database\Seeders;

use App\Enums\BillingMode;
use App\Enums\CompensationType;
use App\Enums\ContractStatus;
use App\Enums\DeploymentShiftType;
use App\Enums\EmploymentStatus;
use App\Enums\GuardClassification;
use App\Enums\GuardGender;
use App\Enums\OperationalStatus;
use App\Enums\RegionStatus;
use App\Enums\PayrollRunStatus;
use App\Enums\ReplacementReason;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\SiteStatus;
use App\Enums\StaffSalaryChangeType;
use App\Enums\SupervisorStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Invoice;
use App\Models\Leave;
use App\Models\LeaveTypeConfig;
use App\Models\Payment;
use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Models\Region;
use App\Models\Shift;
use App\Models\ShiftReplacement;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Supervisor;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\DeploymentService;
use App\Services\Finance\BillingService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\PayrollRunService;
use App\Services\GuardSalaryService;
use App\Services\GuardService;
use App\Services\LeaveService;
use App\Services\OrganizationService;
use App\Services\ReplacementService;
use App\Services\ShiftService;
use App\Services\StaffSalaryService;
use App\Services\StaffService;
use App\Services\SystemSettingService;
use App\Services\SupervisorGuardService;
use App\Services\UserAccessService;
use App\Support\Access\RolePermissionService;
use Carbon\Carbon;
use Database\Seeders\Workflow\WorkflowSeedVerifier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Builds a company through the same services operators use.
 *
 * Modes (PSG_SEED_MODE):
 * - story: a small coherent company, default
 * - full: three regions with different manpower
 * - load: about 1,000 guards, with a worked cohort for shifts, payroll, and billing
 *
 * Duties, payroll, and invoices run from 1 January 2026 through the current date.
 * Payroll and invoices stop at the last closed month.
 * Every shift guard is on a monthly gross of UGX 170,000 for the whole period.
 * In August, some guards work the 31st normal shift and others work overtime.
 */
class WorkflowOperationsSeeder extends Seeder
{
    private const PASSWORD = 'Password@123';

    private const GUARD_MONTHLY_GROSS = 170000;

    /** @var list<string> */
    private array $firstNames = ['Musa', 'Esther', 'Brian', 'Irene', 'Peter', 'Grace', 'Samuel', 'Peace', 'Robert', 'Scovia', 'Denis', 'Janet', 'Francis', 'Doreen', 'Isaac', 'Naomi', 'Daniel', 'Amina', 'Joseph', 'Ruth'];

    /** @var list<string> */
    private array $lastNames = ['Kakooza', 'Nakato', 'Ssempala', 'Achieng', 'Okello', 'Nabwire', 'Turyamureeba', 'Kyomuhendo', 'Bwambale', 'Akello', 'Muhwezi', 'Katusiime', 'Byaruhanga', 'Ninsiima', 'Otim', 'Namuli', 'Kiprotich', 'Juma', 'Mugisha', 'Asiimwe'];

    public function run(): void
    {
        $this->silenceOutboundMail();

        $mode = (string) env('PSG_SEED_MODE', 'story');
        $profile = $this->profile($mode);

        if (Region::query()->where('code', 'KLA')->exists()) {
            $this->seedUsers();
            $this->seedSupervisorUsers();
            $this->seedSupervisorSalaries();
            if ($this->seedGuardGrossSalaries() > 0) {
                $this->rebuildClosedPayroll();
            }
            if ($this->seedAugustVariety() > 0) {
                $this->rebuildPayrollMonth(2026, 8);
            }
            $this->command?->info('Login accounts are in place. Password for every seeded account: '.self::PASSWORD);
            $this->command?->info('Users: '.User::query()->count());

            return;
        }

        $this->seedUsers();
        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->firstOrFail();
        $hr = User::query()->where('email', 'hr@platinumsecurity.local')->firstOrFail();
        $shifts = User::query()->where('email', 'shifts@platinumsecurity.local')->firstOrFail();
        $finance = User::query()->where('email', 'finance@platinumsecurity.local')->firstOrFail();
        Auth::login($hr);

        $organization = app(OrganizationService::class);
        $regions = $this->seedRegions($organization, $profile);
        $this->seedSupervisorUsers();
        $this->seedSupervisorSalaries();
        $guards = $this->seedGuards($regions, $profile);
        $this->seedStaff($profile['staff']);

        Auth::login($shifts);
        $this->seedHistoricalDuties($regions);
        $this->seedAugustVariety();
        $this->seedCurrentPostings($regions);
        $this->seedTransfer($regions);
        $this->seedOvertimeCover($regions);
        $this->seedSupervisorCover($regions);
        $this->seedLeaveAndReplacement($guards);

        Auth::login($finance);
        $this->seedPayroll();
        $this->seedBilling($regions);

        Auth::logout();

        $report = (new WorkflowSeedVerifier)->assertClean();
        $this->printReport($mode, $report);
    }

    private function silenceOutboundMail(): void
    {
        config([
            'mail.default' => 'log',
            'psg.notifications.workflow_email_enabled' => false,
        ]);
        Mail::fake();
    }

    /** @return array{regions: list<array{code: string, name: string, sites: int, guards: int, supervisors: int}>, staff: int} */
    private function profile(string $mode): array
    {
        $regions = match ($mode) {
            'load' => [
                ['code' => 'KLA', 'name' => 'Kampala', 'sites' => 8, 'guards' => 400, 'supervisors' => 2],
                ['code' => 'WES', 'name' => 'Western', 'sites' => 12, 'guards' => 350, 'supervisors' => 3],
                ['code' => 'NTH', 'name' => 'Northern', 'sites' => 6, 'guards' => 250, 'supervisors' => 2],
            ],
            'full' => [
                ['code' => 'KLA', 'name' => 'Kampala', 'sites' => 8, 'guards' => 24, 'supervisors' => 2],
                ['code' => 'WES', 'name' => 'Western', 'sites' => 6, 'guards' => 18, 'supervisors' => 2],
                ['code' => 'NTH', 'name' => 'Northern', 'sites' => 4, 'guards' => 12, 'supervisors' => 1],
            ],
            default => [
                ['code' => 'KLA', 'name' => 'Kampala', 'sites' => 4, 'guards' => 12, 'supervisors' => 2],
                ['code' => 'WES', 'name' => 'Western', 'sites' => 3, 'guards' => 8, 'supervisors' => 1],
                ['code' => 'NTH', 'name' => 'Northern', 'sites' => 2, 'guards' => 6, 'supervisors' => 1],
            ],
        };

        return [
            'regions' => $regions,
            'staff' => $mode === 'load' ? 40 : ($mode === 'full' ? 12 : 6),
        ];
    }

    private function seedUsers(): void
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
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'phone' => '+256700000'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'password' => Hash::make(self::PASSWORD),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }

        app(RolePermissionService::class)->mergeMissingPermissions();
    }

    private function seedSupervisorUsers(): void
    {
        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first();
        if ($admin !== null) {
            Auth::login($admin);
        }

        $access = app(UserAccessService::class);

        Supervisor::query()->orderBy('id')->each(function (Supervisor $supervisor) use ($access): void {
            $alreadyLinked = User::query()->where(function ($query) use ($supervisor): void {
                $query->where('supervisor_id', $supervisor->id)
                    ->orWhere('email', $supervisor->email);
            })->exists();

            if ($alreadyLinked) {
                return;
            }

            $access->create([
                'name' => $supervisor->name,
                'email' => $supervisor->email,
                'phone' => $supervisor->phone,
                'role' => UserRole::RegionSupervisor->value,
                'supervisor_id' => $supervisor->id,
                'password' => self::PASSWORD,
                'is_active' => true,
            ]);
        });
    }

    private function seedSupervisorSalaries(): void
    {
        $hr = User::query()->where('email', 'hr@platinumsecurity.local')->first();
        if ($hr !== null) {
            Auth::login($hr);
        }

        $salaries = [1200000, 1350000, 1500000, 1100000, 1600000, 1450000, 1250000];
        $service = app(StaffSalaryService::class);
        $index = 0;

        Supervisor::query()->with('staffProfile')->orderBy('id')->each(function (Supervisor $supervisor) use (&$index, $salaries, $service, $hr): void {
            $staff = $supervisor->staffProfile;
            if ($staff === null || $staff->salaryRevisions()->exists()) {
                return;
            }

            $opening = $salaries[$index % count($salaries)];
            $from = $staff->date_employed?->copy()->startOfDay() ?? Carbon::parse('2026-01-06');
            $service->recordOpening(
                $staff,
                $opening,
                $from,
                $hr,
                'Supervisor',
                null,
                'Opening salary for the field supervisor post.',
            );

            if ($index === 0) {
                $service->change(
                    $staff->fresh(),
                    $opening + 150000,
                    Carbon::parse('2026-07-01'),
                    StaffSalaryChangeType::Increment,
                    'Annual review. January to June salary remains on the earlier revision.',
                    $hr,
                );
            }

            $index++;
        });
    }

    /**
     * @param  array{regions: list<array{code: string, name: string, sites: int, guards: int, supervisors: int}>}  $profile
     * @return list<array{region: Region, sites: list<Site>, guards: list<Guard>}>
     */
    private function seedRegions(OrganizationService $organization, array $profile): array
    {
        $built = [];
        $guardService = app(GuardService::class);
        $profiles = app(SupervisorGuardService::class);
        $billing = app(BillingService::class);
        $guardCursor = 0;

        foreach ($profile['regions'] as $regionIndex => $def) {
            $region = Region::query()->create([
                'code' => $def['code'],
                'name' => $def['name'],
                'manager_name' => $this->firstNames[$regionIndex].' '.$this->lastNames[$regionIndex],
                'manager_phone' => '+256701000'.str_pad((string) ($regionIndex + 1), 3, '0', STR_PAD_LEFT),
                'description' => $def['name'].' operating area, opened January 2026.',
                'status' => RegionStatus::Active,
            ]);

            $supervisors = [];
            for ($s = 0; $s < $def['supervisors']; $s++) {
                $supervisor = Supervisor::query()->create([
                    'supervisor_code' => $def['code'].'-S'.$s,
                    'name' => $this->firstNames[($regionIndex + $s + 3) % 20].' '.$this->lastNames[($regionIndex + $s + 5) % 20],
                    'phone' => '+256702'.str_pad((string) (($regionIndex * 10) + $s), 6, '0', STR_PAD_LEFT),
                    'email' => strtolower($def['code']).'.s'.$s.'@platinumsecurity.local',
                    'region_id' => $region->id,
                    'status' => SupervisorStatus::Active,
                    'assignment_date' => '2026-01-06',
                    'notes' => 'Assigned when '.$def['name'].' opened.',
                ]);
                $organization->recordSupervisorAssignment($supervisor, null, (int) $region->id, 'initial_assignment', 'Opening assignment', 'Region opened.');
                $profiles->ensureEmployeeProfiles($supervisor->fresh());
                $supervisors[] = $supervisor->fresh();
            }

            $sites = [];
            for ($i = 0; $i < $def['sites']; $i++) {
                $pattern = [[6, 3, 3], [4, 2, 2], [8, 4, 4], [3, 2, 1], [5, 2, 3]][$i % 5];
                $client = Client::query()->create([
                    'name' => $def['name'].' Client '.($i + 1),
                    'contact_person' => $this->firstNames[$i % 20].' '.$this->lastNames[($i + 4) % 20],
                    'phone' => '+256703'.str_pad((string) (($regionIndex * 100) + $i), 6, '0', STR_PAD_LEFT),
                    'email' => strtolower($def['code']).'.client'.($i + 1).'@example.test',
                    'address' => $def['name'].' industrial area',
                    'contract_start_date' => '2026-01-01',
                    'contract_end_date' => '2026-12-31',
                    'contract_status' => ContractStatus::Active,
                    'notes' => 'Seeded client contract.',
                ]);

                $site = Site::query()->create([
                    'name' => $def['name'].' Site '.($i + 1),
                    'code' => $def['code'].'-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                    'client_id' => $client->id,
                    'region_id' => $region->id,
                    'supervisor_id' => $supervisors[$i % count($supervisors)]->id,
                    'physical_location' => $def['name'].' plot '.($i + 1),
                    'site_contact_person' => $client->contact_person,
                    'site_contact_phone' => $client->phone,
                    'contract_start_date' => '2026-01-01',
                    'contract_end_date' => '2026-12-31',
                    'required_guards' => $pattern[0],
                    'required_day_guards' => $pattern[1],
                    'required_day_armed_guards' => 0,
                    'required_day_unarmed_guards' => $pattern[1],
                    'required_night_guards' => $pattern[2],
                    'required_night_armed_guards' => 0,
                    'required_night_unarmed_guards' => $pattern[2],
                    'number_of_posts' => max($pattern[1], $pattern[2]),
                    'status' => SiteStatus::Active,
                    'notes' => 'Manpower set when the site contract started.',
                ]);
                $organization->syncSiteManpower($site, 'Opening manpower requirement');
                $billing->create([
                    'client_id' => $client->id,
                    'site_id' => $site->id,
                    'billing_mode' => BillingMode::Monthly->value,
                    'monthly_rate_per_unarmed_guard' => 180000 + ($i * 10000),
                    'monthly_rate_per_armed_guard' => 220000,
                    'effective_from' => '2026-01-01',
                ]);
                $sites[] = $site;
            }

            $regionGuards = [];
            $deployCap = min($def['guards'], max(8, $def['sites'] * 3));
            for ($g = 0; $g < $def['guards']; $g++) {
                $hire = Carbon::parse('2026-01-06')->addDays(($guardCursor * 3) % 160);
                $regionGuards[] = $guardService->createGuard([
                    'first_name' => $this->firstNames[$guardCursor % 20],
                    'last_name' => $this->lastNames[($guardCursor * 3) % 20],
                    'region_id' => $region->id,
                    'gender' => $guardCursor % 2 === 0 ? GuardGender::Male->value : GuardGender::Female->value,
                    'phone' => '+256704'.str_pad((string) $guardCursor, 6, '0', STR_PAD_LEFT),
                    'date_employed' => $hire->toDateString(),
                    'employment_status' => EmploymentStatus::Active->value,
                    'operational_status' => OperationalStatus::AwaitingDeployment->value,
                    'rank_designation' => 'Security Guard',
                    'guard_classification' => GuardClassification::Unarmed->value,
                    'address' => $def['name'].', Uganda',
                    'compensation_type' => CompensationType::Shift->value,
                    'base_shift_rate' => self::GUARD_MONTHLY_GROSS,
                    'bank_name' => 'Centenary Bank',
                    'bank_account' => '30'.str_pad((string) $guardCursor, 8, '0', STR_PAD_LEFT),
                    'nssf_number' => 'NSSF'.str_pad((string) $guardCursor, 6, '0', STR_PAD_LEFT),
                ]);
                $guardCursor++;
            }

            $built[] = [
                'region' => $region,
                'sites' => $sites,
                'guards' => array_slice($regionGuards, 0, $deployCap),
                'pool' => $regionGuards,
            ];
        }

        return $built;
    }

    /**
     * @param  list<array{region: Region, sites: list<Site>, guards: list<Guard>, pool: list<Guard>}>  $regions
     * @return list<Guard>
     */
    private function seedGuards(array $regions, array $profile): array
    {
        return collect($regions)->flatMap(fn (array $row) => $row['guards'])->values()->all();
    }

    private function seedStaff(int $count): void
    {
        $service = app(StaffService::class);
        $titles = [
            ['Finance Officer', 'Finance', 900000],
            ['Admin Assistant', 'Administration', 700000],
            ['Operations Clerk', 'Operations', 750000],
            ['HR Assistant', 'Human Resources', 680000],
        ];

        for ($i = 0; $i < $count; $i++) {
            [$title, $department, $salary] = $titles[$i % count($titles)];
            $service->createStaff([
                'first_name' => $this->firstNames[($i + 7) % 20],
                'last_name' => $this->lastNames[($i + 11) % 20].' Staff',
                'job_title' => $title,
                'department' => $department,
                'monthly_salary' => $salary + ($i * 5000),
                'employment_id' => $service->nextEmploymentId(),
                'phone' => '075'.str_pad((string) $i, 7, '0', STR_PAD_LEFT),
                'email' => 'staff'.$i.'@platinumsecurity.local',
                'bank_name' => 'Centenary Bank',
                'bank_account' => '32'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'date_employed' => Carbon::parse('2026-02-01')->addDays($i % 40)->toDateString(),
                'employment_status' => EmploymentStatus::Active->value,
            ]);
        }
    }

    /**
     * Shift guards stay on one monthly gross for the whole employment period.
     * Supervisor salary records are left as they are.
     */
    private function seedGuardGrossSalaries(): int
    {
        $hr = User::query()->where('email', 'hr@platinumsecurity.local')->first();
        if ($hr !== null) {
            Auth::login($hr);
        }

        $settings = app(SystemSettingService::class);
        $current = $settings->current();
        if (abs((float) $current->payroll_default_base_shift_rate - self::GUARD_MONTHLY_GROSS) > 0.009) {
            $settings->update([
                'payroll_default_base_shift_rate' => self::GUARD_MONTHLY_GROSS,
            ]);
        }

        $salaries = app(GuardSalaryService::class);
        $updated = 0;

        Guard::query()
            ->whereDoesntHave('supervisorProfile')
            ->where('compensation_type', CompensationType::Shift->value)
            ->orderBy('id')
            ->each(function (Guard $guard) use ($salaries, $hr, &$updated): void {
                $revisions = $guard->salaryRevisions()->reorder()->orderBy('effective_from')->orderBy('id')->get();
                $single = $revisions->count() === 1 ? $revisions->first() : null;

                if (
                    $single !== null
                    && $single->effective_to === null
                    && abs((float) $single->salary - self::GUARD_MONTHLY_GROSS) < 0.01
                    && abs((float) $guard->base_shift_rate - self::GUARD_MONTHLY_GROSS) < 0.01
                ) {
                    return;
                }

                $guard->salaryRevisions()->delete();
                $salaries->recordOpening(
                    $guard,
                    self::GUARD_MONTHLY_GROSS,
                    $guard->date_employed ?? Carbon::parse('2026-01-06'),
                    $hr,
                    'Monthly gross of UGX 170,000 for the full employment period.',
                );
                $updated++;
            });

        $this->command?->info('Shift guards set to UGX 170,000 throughout: '.$updated.' updated.');

        return $updated;
    }

    private function rebuildClosedPayroll(): void
    {
        $closed = PayrollRunService::lastClosedPeriod()->startOfMonth();
        $cursor = Carbon::parse('2026-01-01')->startOfMonth();

        while ($cursor->lte($closed)) {
            $this->rebuildPayrollMonth((int) $cursor->year, (int) $cursor->month);
            $cursor->addMonth();
        }
    }

    private function rebuildPayrollMonth(int $year, int $month): void
    {
        $payroll = app(PayrollRunService::class);
        $finance = User::query()->where('email', 'finance@platinumsecurity.local')->firstOrFail();
        $approver = User::query()->where('email', 'md@platinumsecurity.local')->firstOrFail();
        Auth::login($finance);

        PayrollRun::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->whereNull('region_id')
            ->whereNull('site_id')
            ->where('status', '!=', PayrollRunStatus::Cancelled->value)
            ->orderBy('id')
            ->each(function (PayrollRun $existing) use ($payroll): void {
                $payroll->cancel($existing);
            });

        $label = Carbon::create($year, $month, 1)->format('F Y');
        $this->command?->line('Recalculating '.$label.' payroll…');
        $run = $payroll->createDraft([
            'period_year' => $year,
            'period_month' => $month,
            'notes' => $label.' payroll from recorded duties and a monthly gross of UGX 170,000.',
        ], $finance);
        $run = $payroll->calculate($run);
        $run = $payroll->submit($run, $finance);
        $payroll->approve($run, $approver);
    }

    /**
     * Most August duties stay on the 30-shift basis. A few guards also work 31 August,
     * and a different few work overtime on 10–12 August.
     */
    private function seedAugustVariety(): int
    {
        $actor = User::query()->where('email', 'admin@platinumsecurity.local')->first()
            ?? User::query()->where('email', 'shifts@platinumsecurity.local')->first();
        if ($actor !== null) {
            Auth::login($actor);
        }

        $eligible = Guard::query()
            ->whereDoesntHave('supervisorProfile')
            ->where('compensation_type', CompensationType::Shift->value)
            ->whereDate('date_employed', '<=', '2026-08-01')
            ->orderBy('employment_id')
            ->get()
            ->filter(fn (Guard $guard): bool => $this->augustAnchorShift($guard) !== null)
            ->values();

        $created = 0;
        $deployments = app(DeploymentService::class);

        foreach ($eligible->take(5) as $guard) {
            if ($this->hasRecordedAugustShift($guard, '2026-08-31', ShiftType::Normal)) {
                continue;
            }

            $anchor = $this->augustAnchorShift($guard);
            if ($anchor === null) {
                continue;
            }

            try {
                $deployments->deployTemporaryCoverage([
                    'guard_id' => $guard->id,
                    'site_id' => $anchor->site_id,
                    'shift_type' => $this->deploymentShiftFor($anchor)->value,
                    'duty_type' => ShiftType::Normal->value,
                    'start_date' => '2026-08-31',
                    'duty_date_to' => '2026-08-31',
                    'notes' => 'August 31 normal duty. This guard worked 31 normal shifts in August.',
                ]);
                $created++;
                $this->command?->line($guard->employment_id.' worked 31 normal shifts in August.');
            } catch (\Throwable $exception) {
                $this->command?->warn($guard->employment_id.' August 31 normal duty was not recorded: '.$exception->getMessage());
            }
        }

        foreach ($eligible->slice(5, 5) as $guard) {
            if ($this->hasRecordedAugustShift($guard, null, ShiftType::Overtime)) {
                continue;
            }

            $anchor = $this->augustAnchorShift($guard);
            if ($anchor === null) {
                continue;
            }

            $opposite = $this->deploymentShiftFor($anchor) === DeploymentShiftType::Night
                ? DeploymentShiftType::Day
                : DeploymentShiftType::Night;

            try {
                $deployments->deployTemporaryCoverage([
                    'guard_id' => $guard->id,
                    'site_id' => $anchor->site_id,
                    'shift_type' => $opposite->value,
                    'duty_type' => ShiftType::Overtime->value,
                    'start_date' => '2026-08-10',
                    'duty_date_to' => '2026-08-12',
                    'notes' => 'August overtime on the opposite shift, 10–12 August. Does not replace the normal posting.',
                ]);
                $created++;
                $this->command?->line($guard->employment_id.' worked overtime on 10–12 August.');
            } catch (\Throwable $exception) {
                $this->command?->warn($guard->employment_id.' August overtime was not recorded: '.$exception->getMessage());
            }
        }

        $this->command?->info('August variety recorded: '.$created.' guards.');

        return $created;
    }

    private function augustAnchorShift(Guard $guard): ?Shift
    {
        return Shift::query()
            ->where('guard_id', $guard->id)
            ->whereBetween('shift_date', ['2026-08-01', '2026-08-30'])
            ->where('shift_type', ShiftType::Normal->value)
            ->where('status', ShiftStatus::Recorded->value)
            ->orderBy('shift_date')
            ->first();
    }

    private function hasRecordedAugustShift(Guard $guard, ?string $date, ShiftType $type): bool
    {
        return Shift::query()
            ->where('guard_id', $guard->id)
            ->when(
                $date !== null,
                fn ($query) => $query->whereDate('shift_date', $date),
                fn ($query) => $query->whereBetween('shift_date', ['2026-08-01', '2026-08-31']),
            )
            ->where('shift_type', $type->value)
            ->where('status', ShiftStatus::Recorded->value)
            ->exists();
    }

    private function deploymentShiftFor(Shift $shift): DeploymentShiftType
    {
        return $shift->period === ShiftPeriod::Night
            ? DeploymentShiftType::Night
            : DeploymentShiftType::Day;
    }

    /**
     * Stable site posts used for every month.
     * The first Kampala site stays one day post short. The next site keeps one day opening for a transfer.
     *
     * @param  list<array{sites: list<Site>, pool: list<Guard>}>  $regions
     * @return list<array{guard: Guard, site: Site, shift: DeploymentShiftType}>
     */
    private function standingPosts(array $regions): array
    {
        $posts = [];

        foreach ($regions as $regionIndex => $row) {
            $used = [];

            foreach ($row['sites'] as $siteIndex => $site) {
                $dayNeeded = (int) $site->required_day_guards;
                $nightNeeded = (int) $site->required_night_guards;

                if ($regionIndex === 0 && $siteIndex === 0) {
                    $dayNeeded = max(0, $dayNeeded - 1);
                }

                if ($regionIndex === 0 && $siteIndex === 1) {
                    $dayNeeded = max(0, $dayNeeded - 1);
                }

                foreach ([DeploymentShiftType::Day->value => $dayNeeded, DeploymentShiftType::Night->value => $nightNeeded] as $shiftValue => $needed) {
                    $taken = 0;

                    foreach ($row['pool'] as $guard) {
                        if ($taken >= $needed || isset($used[$guard->id])) {
                            continue;
                        }

                        if ((int) $guard->region_id !== (int) $site->region_id) {
                            continue;
                        }

                        $used[$guard->id] = true;
                        $posts[] = [
                            'guard' => $guard,
                            'site' => $site,
                            'shift' => DeploymentShiftType::from($shiftValue),
                        ];
                        $taken++;
                    }
                }
            }
        }

        return $posts;
    }

    /** @param  list<array{sites: list<Site>, pool: list<Guard>}>  $regions */
    private function seedHistoricalDuties(array $regions): void
    {
        $deployments = app(DeploymentService::class);
        $posts = $this->standingPosts($regions);
        $end = now()->subDay()->startOfDay();
        $cursor = Carbon::parse('2026-01-01')->startOfMonth();

        if ($end->lt($cursor)) {
            return;
        }

        while ($cursor->lte($end)) {
            $monthEnd = $cursor->copy()->endOfMonth()->startOfDay();
            if ($monthEnd->gt($end)) {
                $monthEnd = $end->copy();
            }

            $this->command?->line('Recording '.$cursor->format('F Y').' duties ('.count($posts).' posts)…');

            foreach ($posts as $post) {
                $from = $cursor->copy();
                $hired = $post['guard']->date_employed?->copy()->startOfDay();
                if ($hired !== null && $hired->gt($from)) {
                    $from = $hired->copy();
                }

                if ($from->gt($monthEnd)) {
                    continue;
                }

                $basis = max(1, (int) config('psg.payroll.standard_shifts_per_month', 30));
                $dutyTo = $monthEnd->copy();
                $basisEnd = $from->copy()->addDays($basis - 1);
                if ($basisEnd->lt($dutyTo)) {
                    $dutyTo = $basisEnd;
                }

                $deployments->deploy([
                    'guard_id' => $post['guard']->id,
                    'site_id' => $post['site']->id,
                    'shift_type' => $post['shift']->value,
                    'start_date' => $from->toDateString(),
                    'duty_date_to' => $dutyTo->toDateString(),
                    'notes' => 'Duties for '.$from->format('F Y').' entered on '.now()->toDateString().'. Operational dates stay in '.$from->format('F').'.',
                    'correction_reason' => 'Historical duties for '.$from->format('F Y').'.',
                ]);
            }

            $cursor->addMonth()->startOfMonth();
        }
    }

    /** @param  list<array{sites: list<Site>, pool: list<Guard>}>  $regions */
    private function seedCurrentPostings(array $regions): void
    {
        $deployments = app(DeploymentService::class);
        $today = now()->toDateString();

        foreach ($this->standingPosts($regions) as $post) {
            if ($post['guard']->date_employed !== null && $post['guard']->date_employed->toDateString() > $today) {
                continue;
            }

            if (Deployment::query()->current()->permanent()->where('guard_id', $post['guard']->id)->exists()) {
                continue;
            }

            $deployments->deploy([
                'guard_id' => $post['guard']->id,
                'site_id' => $post['site']->id,
                'shift_type' => $post['shift']->value,
                'start_date' => $today,
                'notes' => 'Current posting, continuing the assignment that started in 2026.',
            ]);
        }
    }

    /** @param  list<array{sites: list<Site>}>  $regions */
    private function seedTransfer(array $regions): void
    {
        $sites = $regions[0]['sites'] ?? [];
        if (count($sites) < 2) {
            return;
        }

        $deployment = Deployment::query()->current()->permanent()->where('site_id', $sites[0]->id)->first();
        if ($deployment === null) {
            return;
        }

        $destination = collect($sites)->first(function (Site $site) use ($deployment) {
            if ((int) $site->id === (int) $deployment->site_id) {
                return false;
            }

            $required = $deployment->shift_type === \App\Enums\DeploymentShiftType::Night
                ? (int) $site->required_night_guards
                : (int) $site->required_day_guards;
            $posted = Deployment::query()->current()->permanent()
                ->where('site_id', $site->id)
                ->where('shift_type', $deployment->shift_type)
                ->count();

            return $posted < $required;
        });

        if ($destination === null) {
            return;
        }

        app(DeploymentService::class)->transfer($deployment, [
            'site_id' => $destination->id,
            'effective_date' => now()->toDateString(),
            'reason' => 'Client requested the guard at a site with open capacity.',
        ]);
    }

    /** @param  list<array{sites: list<Site>, pool: list<Guard>}>  $regions */
    private function seedOvertimeCover(array $regions): void
    {
        $site = $regions[0]['sites'][0] ?? null;
        if ($site === null) {
            return;
        }

        $guard = collect($regions[0]['pool'])->first(function (Guard $guard) {
            return ! Deployment::query()->current()->where('guard_id', $guard->id)->exists();
        });

        if ($guard === null) {
            return;
        }

        app(DeploymentService::class)->deployTemporaryCoverage([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'shift_type' => DeploymentShiftType::Night->value,
            'duty_type' => ShiftType::Overtime->value,
            'start_date' => now()->toDateString(),
            'duty_date_to' => now()->toDateString(),
            'notes' => 'Overtime cover for the permanent night shortage. Does not add a permanent post.',
        ]);
    }

    /** @param  list<array{sites: list<Site>}>  $regions */
    private function seedSupervisorCover(array $regions): void
    {
        $site = $regions[0]['sites'][0] ?? null;
        $supervisor = $site ? Supervisor::query()->find($site->supervisor_id) : null;
        if ($site === null || $supervisor === null) {
            return;
        }

        try {
            app(DeploymentService::class)->deploySupervisor($supervisor, [
                'site_id' => $site->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'start_date' => now()->toDateString(),
                'notes' => 'Supervisor day cover for the remaining manpower gap.',
            ]);
        } catch (\Throwable $exception) {
            $this->command?->warn('Supervisor cover was not posted: '.$exception->getMessage());
        }
    }

    /** @param  list<Guard>  $guards */
    private function seedLeaveAndReplacement(array $guards): void
    {
        $type = LeaveTypeConfig::query()->where('code', 'annual')->where('is_active', true)->first();
        $guard = $guards[0] ?? null;
        $relief = $guards[1] ?? null;
        if ($type === null || $guard === null || $relief === null) {
            return;
        }

        $site = Site::query()->where('region_id', $guard->region_id)->first();
        if ($site === null) {
            return;
        }

        $when = now()->addDays(4)->toDateString();
        $shift = app(ShiftService::class)->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'shift_date' => $when,
            'start_time' => '18:00',
            'end_time' => '06:00',
            'period' => ShiftPeriod::Night->value,
            'status' => ShiftStatus::Scheduled->value,
            'acknowledge_warnings' => true,
            'notes' => 'Upcoming night shift, one continuous 18:00–06:00 duty.',
        ]);

        $leave = app(LeaveService::class)->create([
            'guard_id' => $guard->id,
            'leave_type_id' => $type->id,
            'start_date' => $when,
            'end_date' => $when,
            'reason' => 'Family commitment.',
        ]);
        app(LeaveService::class)->approve($leave, 'Approved. Shift conflict needs a replacement.');

        app(ReplacementService::class)->record([
            'original_shift_id' => $shift->id,
            'replacement_guard_id' => $relief->id,
            'reason' => ReplacementReason::Leave->value,
            'notes' => 'Relief arranged because approved leave covers this night shift.',
            'acknowledge_warnings' => true,
        ]);

        $pending = app(LeaveService::class)->create([
            'guard_id' => $relief->id,
            'leave_type_id' => $type->id,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(21)->toDateString(),
            'reason' => 'Personal errand.',
        ]);
        app(LeaveService::class)->reject($pending, 'Cover is not available on those dates.');

        app(AttendanceService::class)->record([
            'guard_id' => $guard->id,
            'event_type' => 'on_duty',
            'occurred_at' => '2026-08-03 06:05:00',
            'site_id' => $site->id,
            'notes' => 'Checked on duty for the August historical shift.',
        ]);
    }

    private function seedPayroll(): void
    {
        $payroll = app(PayrollRunService::class);
        $finance = Auth::user();
        $approver = User::query()->where('email', 'md@platinumsecurity.local')->first();
        $closed = PayrollRunService::lastClosedPeriod()->startOfMonth();
        $cursor = Carbon::parse('2026-01-01')->startOfMonth();

        while ($cursor->lte($closed)) {
            $this->command?->line('Calculating '.$cursor->format('F Y').' payroll…');
            $run = $payroll->createDraft([
                'period_year' => (int) $cursor->year,
                'period_month' => (int) $cursor->month,
                'notes' => $cursor->format('F Y').' payroll from recorded duties and the salary in force that month.',
            ], $finance);
            $run = $payroll->calculate($run);
            $run = $payroll->submit($run, $finance);
            $payroll->approve($run, $approver);
            $cursor->addMonth();
        }
    }

    /** @param  list<array{sites: list<Site>}>  $regions */
    private function seedBilling(array $regions): void
    {
        $invoices = app(InvoiceService::class);
        $payments = app(PaymentService::class);
        $closed = PayrollRunService::lastClosedPeriod()->startOfMonth();
        $sites = collect($regions)->flatMap(fn (array $row) => $row['sites'])->values();

        $cursor = Carbon::parse('2026-01-01')->startOfMonth();
        while ($cursor->lte($closed)) {
            $this->command?->line('Invoicing '.$cursor->format('F Y').'…');
            $periodStart = $cursor->copy()->startOfMonth()->toDateString();
            $periodEnd = $cursor->copy()->endOfMonth()->toDateString();
            $issueDate = $cursor->copy()->addMonth()->startOfMonth()->toDateString();
            $isLatest = $cursor->isSameMonth($closed);
            $isPrevious = $cursor->isSameMonth($closed->copy()->subMonth());

            foreach ($sites as $siteIndex => $site) {
                $lines = $invoices->suggestLines((int) $site->client_id, (int) $site->id, $periodStart, $periodEnd);
                if ($lines === []) {
                    continue;
                }

                $dueDate = $cursor->copy()->addMonth()->day(15)->toDateString();
                if ($isLatest && $siteIndex === 1) {
                    $dueDate = now()->addDays(20)->toDateString();
                }
                if ($isPrevious && $siteIndex === 0) {
                    $dueDate = now()->addDays(12)->toDateString();
                }

                $invoice = $invoices->createDraft([
                    'client_id' => $site->client_id,
                    'site_id' => $site->id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'due_date' => $dueDate,
                    'notes' => $cursor->format('F Y').' invoice from the site billing profile.',
                    'lines' => $lines,
                ]);
                $invoice = $invoices->issue($invoice, $issueDate);

                $payInFull = ! $isLatest && ! ($isPrevious && $siteIndex === 0);
                $payHalf = $isPrevious && $siteIndex === 0;

                if ($payInFull || $payHalf) {
                    $amount = $payHalf
                        ? round(((float) $invoice->total) / 2, 2)
                        : (float) $invoice->total;
                    $payments->record([
                        'invoice_id' => $invoice->id,
                        'amount' => $amount,
                        'payment_date' => $cursor->copy()->addMonth()->day(10)->toDateString(),
                        'notes' => $payHalf
                            ? 'Part payment. Balance remains on this invoice.'
                            : 'Paid in full.',
                    ]);
                }
            }

            $cursor->addMonth();
        }

        $invoices->markOverdueInvoices();
    }

    /** @param  array<string, int>  $report */
    private function printReport(string $mode, array $report): void
    {
        $this->command?->newLine();
        $this->command?->info('Workflow seed ('.$mode.') from 1 January 2026 through '.now()->toDateString());
        foreach ($report as $label => $count) {
            $this->command?->line($label.': '.$count);
        }
        $this->command?->info('Login: admin@platinumsecurity.local / '.self::PASSWORD);
        $this->command?->info('Validation errors: 0');
    }
}
