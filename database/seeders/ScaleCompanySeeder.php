<?php

namespace Database\Seeders;

use App\Enums\BillingMode;
use App\Enums\CompensationType;
use App\Enums\ContractStatus;
use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\GuardClassification;
use App\Enums\GuardDocumentType;
use App\Enums\GuardGender;
use App\Enums\OperationalStatus;
use App\Enums\PayrollRunStatus;
use App\Enums\ReplacementReason;
use App\Enums\SalaryChangeReason;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\SiteStatus;
use App\Enums\StaffSalaryChangeType;
use App\Enums\SupervisorStatus;
use App\Enums\UniformChargeStatus;
use App\Enums\UserRole;
use App\Models\BillingProfile;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\GuardAttachment;
use App\Models\LeaveTypeConfig;
use App\Models\PayrollRun;
use App\Models\Position;
use App\Models\Region;
use App\Models\Shift;
use App\Models\Site;
use App\Models\SiteManpowerRequirement;
use App\Models\Staff;
use App\Models\Supervisor;
use App\Models\User;
use App\Models\Client;
use App\Services\AttendanceService;
use App\Services\DeploymentService;
use App\Services\EmployeePromotionService;
use App\Services\Finance\BillingService;
use App\Services\Finance\Ledger\LedgerPostingService;
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
use App\Services\SupervisorGuardService;
use App\Services\UniformChargeExemptionService;
use App\Services\UserAccessService;
use Carbon\Carbon;
use Database\Seeders\Workflow\ScaleDatasetValidator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Adds a production-scale company on top of the story dataset.
 *
 * Run: php artisan db:seed --class=Database\\Seeders\\ScaleCompanySeeder
 *
 * Guards, sites, shifts, leave, promotions, payroll, and invoices are created
 * through the application services where that stays within the real rules.
 * Historical duties are inserted in batches because one service call per shift
 * cannot finish a multi-year workforce in reasonable time. Those rows still
 * follow employment dates, one duty per guard/date/period, and payable status.
 *
 * Payroll for each closed month is calculated by PayrollRunService. An existing
 * company-wide run for that month is cancelled first so the new workforce is
 * included. September 2026 stays open until the month ends.
 */
class ScaleCompanySeeder extends Seeder
{
    private const NOTE = 'Scale company employee.';

    private const TARGET_GUARDS = 1000;

    private const SITE_COUNT = 216;

    private const HISTORY_FROM = '2025-01-01';

    private const HISTORY_UNTIL = '2026-09-30';

    /** @var list<string> */
    private array $firstNames = [
        'Musa', 'Esther', 'Brian', 'Irene', 'Peter', 'Grace', 'Samuel', 'Peace', 'Robert', 'Scovia',
        'Denis', 'Janet', 'Francis', 'Doreen', 'Isaac', 'Naomi', 'Daniel', 'Amina', 'Joseph', 'Ruth',
        'Hassan', 'Lydia', 'Patrick', 'Claire', 'Moses', 'Rebecca', 'Andrew', 'Florence', 'Simon', 'Harriet',
        'Emmanuel', 'Sarah', 'David', 'Agnes', 'John', 'Mary',
    ];

    /** @var list<string> */
    private array $lastNames = [
        'Kakooza', 'Nakato', 'Ssempala', 'Achieng', 'Okello', 'Nabwire', 'Turyamureeba', 'Kyomuhendo', 'Bwambale', 'Akello',
        'Muhwezi', 'Katusiime', 'Byaruhanga', 'Ninsiima', 'Otim', 'Namuli', 'Kiprotich', 'Juma', 'Mugisha', 'Asiimwe',
        'Wasswa', 'Namugga', 'Ochieng', 'Atim', 'Tumwesigye', 'Kemi', 'Lubega', 'Nabatanzi', 'Opio', 'Auma',
        'Ssebugwawo', 'Nankya', 'Mbabazi', 'Chebet', 'Odong', 'Nakimuli',
    ];

    /** @var list<string> */
    private array $clients = [
        'Lake View Mall', 'Equator Bank', 'Mbarara Hospital', 'Nile Grain Mill', 'Cresta Apartments', 'Horizon Logistics',
        'Pearl Telecom', 'Rwenzori Tea', 'City Fuel Depot', 'Greenfield School', 'Acacia Hotel', 'Summit Cement',
        'Victoria Fisheries', 'Kibo Warehousing', 'Redearth Mining', 'Savanna Retail', 'Bridge Court', 'Oak Medical Centre',
        'Falcon Aviation', 'Metro Water', 'Cedar University', 'Amber Residences', 'Portside Cargo', 'Silverline Insurance',
        'Highland Dairy', 'Unity Markets', 'Breeze FM', 'Iron Gate Steel', 'Palm Courts', 'Northwind Energy',
        'Kalungi Foods', 'Riverbank Clinic', 'Atlas Construction', 'Lighthouse Church', 'Zebra Parks', 'Crown Beverages',
    ];

    /** @var list<string> */
    private array $facets = ['Main gate', 'Warehouse', 'Reception', 'Plant', 'Car park', 'Stores'];

    public function run(): void
    {
        ini_set('memory_limit', '1G');
        set_time_limit(0);
        DB::connection()->disableQueryLog();
        $this->silenceOutboundMail();

        $hr = User::query()->where('email', 'hr@platinumsecurity.local')->first();
        if ($hr === null || ! Region::query()->where('code', 'KLA')->exists()) {
            throw new RuntimeException('Scale seed needs the story company logins and regions. Run the normal database seed first.');
        }

        Auth::login($hr);
        $this->seedOrganization();
        $this->seedPeople();
        $this->seedPostings();
        $this->seedBlockingLeave();
        $this->seedShifts();
        $this->seedWorkflowEvents();
        $this->seedPayroll();
        $this->seedInvoices();

        $this->releaseDuplicateCompletedDuties();

        $validator = new ScaleDatasetValidator;
        $errors = $validator->errors();
        $this->command?->newLine();
        foreach ($validator->counts() as $label => $count) {
            $this->command?->line($label.': '.$count);
        }
        $this->probe();

        if ($errors !== []) {
            throw new RuntimeException('Scale seed failed validation: '.implode('; ', $errors));
        }

        $this->command?->info('Scale seed complete. Login password remains Password@123.');
    }

    private function silenceOutboundMail(): void
    {
        config([
            'mail.default' => 'log',
            'psg.notifications.workflow_email_enabled' => false,
        ]);
        Mail::fake();
    }

    private function seedOrganization(): void
    {
        if ($this->done('organization')) {
            return;
        }

        $regions = Region::query()->whereIn('code', ['KLA', 'WES', 'NTH'])->orderBy('code')->get();
        if ($regions->count() < 3) {
            throw new RuntimeException('Kampala, Western, and Northern regions are required.');
        }

        $this->command?->line('Creating scale supervisors, clients, and sites…');
        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first() ?? Auth::user();
        Auth::login($admin);
        $this->seedExtraSupervisors($regions);

        Auth::login(User::query()->where('email', 'hr@platinumsecurity.local')->first());
        $billing = app(BillingService::class);
        $supervisors = Supervisor::query()->orderBy('id')->get()->groupBy('region_id');

        for ($i = Site::query()->where('code', 'like', 'SCL-%')->count(); $i < self::SITE_COUNT; $i++) {
            $clientName = $this->clients[intdiv($i, count($this->facets)) % count($this->clients)];
            $region = $regions[intdiv($i, count($this->facets)) % $regions->count()];
            $client = Client::query()->firstOrCreate(
                ['name' => $clientName],
                [
                    'contact_person' => $this->firstNames[$i % 36].' '.$this->lastNames[($i + 5) % 36],
                    'phone' => '+256703'.str_pad((string) (200000 + $i), 6, '0', STR_PAD_LEFT),
                    'email' => 'client'.$i.'@scale.platinumsecurity.local',
                    'address' => $region->name.' service address',
                    'contract_start_date' => self::HISTORY_FROM,
                    'contract_end_date' => '2027-12-31',
                    'contract_status' => ContractStatus::Active,
                    'notes' => 'Scale company client.',
                ],
            );

            $pattern = $this->manpowerPattern($i);
            $inactive = $i >= self::SITE_COUNT - 18;
            $code = 'SCL-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
            if (Site::query()->where('code', $code)->exists()) {
                continue;
            }

            $regionSupervisors = $supervisors->get($region->id, collect())->values();
            $supervisor = $regionSupervisors->get($i % max(1, $regionSupervisors->count()));
            $site = Site::query()->create([
                'name' => $i === 0 ? $clientName.' Coverage Example' : $clientName.' '.$this->facets[$i % count($this->facets)],
                'code' => $code,
                'client_id' => $client->id,
                'region_id' => $region->id,
                'supervisor_id' => $supervisor?->id,
                'physical_location' => $region->name.' plot '.($i + 1),
                'site_contact_person' => $client->contact_person,
                'site_contact_phone' => $client->phone,
                'contract_start_date' => self::HISTORY_FROM,
                'contract_end_date' => $inactive ? '2025-11-30' : '2027-12-31',
                'required_guards' => $pattern['day'] + $pattern['night'],
                'required_day_guards' => $pattern['day'],
                'required_day_armed_guards' => 0,
                'required_day_unarmed_guards' => $pattern['day'],
                'required_night_guards' => $pattern['night'],
                'required_night_armed_guards' => 0,
                'required_night_unarmed_guards' => $pattern['night'],
                'number_of_posts' => max($pattern['day'], $pattern['night']),
                'status' => $inactive
                    ? ($i % 2 === 0 ? SiteStatus::Closed : SiteStatus::ContractExpired)
                    : SiteStatus::Active,
                'notes' => $inactive ? 'Scale site closed November 2025.' : 'Scale company site.',
            ]);

            $this->recordManpower($site, $pattern, $i);
            if (! BillingProfile::query()->where('site_id', $site->id)->exists()) {
                $billing->create([
                    'client_id' => $client->id,
                    'site_id' => $site->id,
                    'billing_mode' => BillingMode::Monthly->value,
                    'monthly_rate_per_unarmed_guard' => 450000,
                    'monthly_rate_per_armed_guard' => 650000,
                    'effective_from' => self::HISTORY_FROM,
                    'effective_to' => $inactive ? '2025-11-30' : null,
                    'is_active' => ! $inactive,
                    'notes' => 'Scale company billing profile.',
                ]);
            }
        }

        $this->mark('organization');
    }

    /** @param  \Illuminate\Support\Collection<int, Region>  $regions */
    private function seedExtraSupervisors($regions): void
    {
        $organization = app(OrganizationService::class);
        $profiles = app(SupervisorGuardService::class);
        $salaries = app(StaffSalaryService::class);
        $access = app(UserAccessService::class);
        $hr = User::query()->where('email', 'hr@platinumsecurity.local')->first();
        $sequence = (int) Supervisor::query()->where('supervisor_code', 'like', '%-SCL-S%')->count();
        $this->topUpSupervisors($regions, $organization, $profiles, $salaries, $access, $hr, $sequence);

        $alreadyMoved = Supervisor::query()
            ->where('notes', 'Scale company supervisor.')
            ->whereHas('assignmentHistories', fn ($query) => $query->where('change_type', 'region_transfer'))
            ->exists();
        if ($alreadyMoved) {
            return;
        }

        $moving = Supervisor::query()
            ->where('notes', 'Scale company supervisor.')
            ->whereDoesntHave('sites')
            ->orderBy('id')
            ->first();
        $destination = $moving === null ? null : $regions->first(fn (Region $region) => (int) $region->id !== (int) $moving->region_id);
        if ($moving === null || $destination === null) {
            return;
        }

        $organization->transferSupervisor($moving, (int) $destination->id, Carbon::parse('2026-04-01'), 'Scale supervisor moved to '.$destination->name.' after the April review.');
        $this->topUpSupervisors($regions, $organization, $profiles, $salaries, $access, $hr, $sequence + 1);
    }

    /** @param  \Illuminate\Support\Collection<int, Region>  $regions */
    private function topUpSupervisors($regions, OrganizationService $organization, SupervisorGuardService $profiles, StaffSalaryService $salaries, UserAccessService $access, ?User $hr, int $sequence): void
    {
        foreach ($regions as $region) {
            while (Supervisor::query()->where('region_id', $region->id)->count() < 4) {
                $sequence++;
                $email = strtolower($region->code).'.scale.s'.$sequence.'@platinumsecurity.local';
                $code = $region->code.'-SCL-S'.$sequence;
                if (Supervisor::query()->where('supervisor_code', $code)->orWhere('email', $email)->exists()) {
                    continue;
                }

                $supervisor = Supervisor::query()->create([
                    'supervisor_code' => $code,
                    'name' => $this->firstNames[$sequence % 36].' '.$this->lastNames[($sequence + 7) % 36],
                    'phone' => '+256702'.str_pad((string) (300000 + $sequence), 6, '0', STR_PAD_LEFT),
                    'email' => $email,
                    'region_id' => $region->id,
                    'status' => SupervisorStatus::Active,
                    'assignment_date' => self::HISTORY_FROM,
                    'notes' => 'Scale company supervisor.',
                ]);
                $organization->recordSupervisorAssignment($supervisor, null, (int) $region->id, 'initial_assignment', 'Scale company opening assignment', 'Assigned for the scale dataset.', null, Carbon::parse(self::HISTORY_FROM));
                $profiles->ensureEmployeeProfiles($supervisor->fresh());
                $staff = $supervisor->fresh()->staffProfile;
                if ($staff !== null && ! $staff->salaryRevisions()->exists()) {
                    $opening = 1100000 + (($sequence % 4) * 120000);
                    $salaries->recordOpening($staff, $opening, Carbon::parse(self::HISTORY_FROM), $hr, 'Supervisor', null, 'Opening salary for a scale supervisor.');
                    if ($sequence % 4 === 1) {
                        $salaries->change($staff->fresh(), $opening + 150000, Carbon::parse('2026-04-01'), StaffSalaryChangeType::Increment, 'Annual supervisor review.', $hr);
                    }
                    if ($sequence % 4 === 2) {
                        $salaries->change($staff->fresh(), max(900000, $opening - 80000), Carbon::parse('2026-05-01'), StaffSalaryChangeType::Demotion, 'Supervisor grade reduced after the region review.', $hr);
                    }
                }
                if (! User::query()->where('email', $supervisor->email)->exists()) {
                    $access->create([
                        'name' => $supervisor->name,
                        'email' => $supervisor->email,
                        'phone' => $supervisor->phone,
                        'role' => UserRole::RegionSupervisor->value,
                        'supervisor_id' => $supervisor->id,
                        'password' => 'Password@123',
                        'is_active' => true,
                    ]);
                }
            }
        }
    }

    /** @return array{day: int, night: int, post_day: int, post_night: int, ot_day: int} */
    private function manpowerPattern(int $index): array
    {
        if ($index === 0) {
            return ['day' => 4, 'night' => 2, 'post_day' => 3, 'post_night' => 2, 'ot_day' => 1];
        }

        return match ($index % 5) {
            0 => ['day' => 2, 'night' => 2, 'post_day' => 2, 'post_night' => 2, 'ot_day' => 0],
            1 => ['day' => 4, 'night' => 4, 'post_day' => 4, 'post_night' => 4, 'ot_day' => 0],
            2 => ['day' => 4, 'night' => 4, 'post_day' => 3, 'post_night' => 4, 'ot_day' => 1],
            3 => ['day' => 4, 'night' => 2, 'post_day' => 2, 'post_night' => 1, 'ot_day' => 0],
            default => ['day' => 6, 'night' => 4, 'post_day' => 5, 'post_night' => 3, 'ot_day' => 0],
        };
    }

    /** @param  array{day: int, night: int}  $pattern */
    private function recordManpower(Site $site, array $pattern, int $index): void
    {
        if (SiteManpowerRequirement::query()->where('site_id', $site->id)->exists()) {
            return;
        }

        $openingDay = $pattern['day'];
        $openingNight = $pattern['night'];
        $changed = $index > 0 && $index % 11 === 0;
        if ($changed) {
            $openingDay = max(1, $pattern['day'] - 1);
            $openingNight = max(1, $pattern['night'] - 1);
        }

        SiteManpowerRequirement::query()->create([
            'site_id' => $site->id,
            'required_total' => $openingDay + $openingNight,
            'required_day' => $openingDay,
            'required_day_armed' => 0,
            'required_day_unarmed' => $openingDay,
            'required_night' => $openingNight,
            'required_night_armed' => 0,
            'required_night_unarmed' => $openingNight,
            'effective_from' => self::HISTORY_FROM,
            'effective_to' => $changed ? '2026-03-31' : null,
            'is_current' => ! $changed,
            'notes' => 'Scale company opening manpower.',
            'created_by' => Auth::id(),
        ]);

        if ($changed) {
            SiteManpowerRequirement::query()->create([
                'site_id' => $site->id,
                'required_total' => $pattern['day'] + $pattern['night'],
                'required_day' => $pattern['day'],
                'required_day_armed' => 0,
                'required_day_unarmed' => $pattern['day'],
                'required_night' => $pattern['night'],
                'required_night_armed' => 0,
                'required_night_unarmed' => $pattern['night'],
                'effective_from' => '2026-04-01',
                'effective_to' => null,
                'is_current' => true,
                'notes' => 'Manpower increased from April 2026.',
                'created_by' => Auth::id(),
            ]);
        }
    }

    private function seedPeople(): void
    {
        if ($this->done('people')) {
            return;
        }

        $guards = app(GuardService::class);
        $salaries = app(GuardSalaryService::class);
        $exemptions = app(UniformChargeExemptionService::class);
        $regions = Region::query()->whereIn('code', ['KLA', 'WES', 'NTH'])->orderBy('code')->get();
        $hr = User::query()->where('email', 'hr@platinumsecurity.local')->first();
        $finance = User::query()->where('email', 'finance@platinumsecurity.local')->first();
        Auth::login($hr);
        $existingScale = Guard::query()->where('notes', self::NOTE)->count();
        $needed = max(0, self::TARGET_GUARDS - Guard::query()->count());
        $this->command?->line('Creating '.$needed.' scale guards…');
        $banks = ['Centenary Bank', 'Stanbic Bank', 'DFCU Bank', 'Equity Bank'];
        $rates = [150000, 170000, 185000, 200000, 220000];

        for ($n = 0; $n < $needed; $n++) {
            $i = $existingScale + $n;
            $hire = Carbon::parse('2024-11-01')->addDays(($i * 3) % 600);
            if ($hire->greaterThan(Carbon::parse('2026-06-15'))) {
                $hire = Carbon::parse('2026-06-15')->subDays($i % 40);
            }
            $gender = match (true) {
                $i % 17 === 0 => GuardGender::Other,
                $i % 2 === 0 => GuardGender::Female,
                default => GuardGender::Male,
            };
            $rate = $rates[$i % count($rates)];
            $guard = $guards->createGuard([
                'first_name' => $this->firstNames[$i % 36],
                'middle_name' => $i % 5 === 0 ? $this->firstNames[($i + 9) % 36] : null,
                'last_name' => $this->lastNames[intdiv($i, 36) % 36],
                'gender' => $gender->value,
                'date_of_birth' => Carbon::parse('1978-01-15')->addYears($i % 24)->addDays($i % 27)->toDateString(),
                'phone' => '+25677'.str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT),
                'email' => 'scale.'.($i + 1).'@platinumsecurity.local',
                'address' => $regions[$i % $regions->count()]->name.', Uganda',
                'national_id' => 'CF'.str_pad((string) (800000000000 + $i), 12, '0', STR_PAD_LEFT),
                'date_employed' => $hire->toDateString(),
                'region_id' => $regions[$i % $regions->count()]->id,
                'employment_status' => EmploymentStatus::Active->value,
                'operational_status' => OperationalStatus::AwaitingDeployment->value,
                'rank_designation' => 'Security Guard',
                'guard_classification' => $i % 9 === 0 ? GuardClassification::Armed->value : GuardClassification::Unarmed->value,
                'compensation_type' => CompensationType::Shift->value,
                'base_shift_rate' => $rate,
                'bank_name' => $banks[$i % count($banks)],
                'bank_account' => '31'.str_pad((string) $i, 10, '0', STR_PAD_LEFT),
                'nssf_number' => 'NSSF'.str_pad((string) (100000 + $i), 8, '0', STR_PAD_LEFT),
                'emergency_contact_name' => $this->firstNames[($i + 3) % 36].' '.$this->lastNames[($i + 4) % 36],
                'emergency_contact_phone' => '+25675'.str_pad((string) (2000000 + $i), 7, '0', STR_PAD_LEFT),
                'notes' => self::NOTE,
            ]);

            $this->varyEmployment($guards, $guard, $i, $hire);
            $this->varySalary($salaries, $guard, $i, $hire, $rate, $hr);
            if ($finance !== null && $i % 12 === 0 && $hire->lt(Carbon::parse('2026-01-01'))) {
                try {
                    $exemptions->record($guard->fresh(), UniformChargeStatus::Exempt, Carbon::parse('2026-01-01'), 'Long service. Uniform was already issued for this guard.', 'Scale company exemption.', $finance);
                } catch (Throwable $exception) {
                    $this->command?->warn('Uniform exemption skipped for '.$guard->employment_id.': '.$exception->getMessage());
                }
            }
            if ($i % 4 === 0) {
                $this->attachDocument($guard, $i);
            }
            if ($i > 0 && $i % 100 === 0) {
                $this->command?->line('  '.$i.' guards created');
            }
        }

        $this->seedScaleStaff();
        $this->mark('people');
    }

    private function varyEmployment(GuardService $guards, Guard $guard, int $index, Carbon $hire): void
    {
        $bucket = $index % 100;
        if ($bucket <= 4) {
            $end = $hire->copy()->addDays(280);
            if ($end->greaterThan(now()->subDays(20))) {
                $end = now()->copy()->subDays(20 + ($index % 15));
            }
            if ($end->lt($hire)) {
                return;
            }
            $status = match (true) {
                $bucket === 0 => EmploymentStatus::Resigned,
                $bucket === 1 => EmploymentStatus::Terminated,
                $bucket === 2 => EmploymentStatus::Retired,
                default => EmploymentStatus::Suspended,
            };
            $guards->updateGuard($guard, [
                'employment_status' => $status === EmploymentStatus::Suspended ? EmploymentStatus::Active->value : $status->value,
                'employment_end_date' => $status === EmploymentStatus::Suspended ? null : $end->toDateString(),
                'operational_status' => $status === EmploymentStatus::Suspended ? OperationalStatus::Suspended->value : OperationalStatus::OffDuty->value,
                'guard_pay_until' => $end->toDateString(),
            ], $status === EmploymentStatus::Suspended ? 'suspended' : strtolower($status->value));
        }
    }

    private function varySalary(GuardSalaryService $salaries, Guard $guard, int $index, Carbon $hire, int $rate, ?User $hr): void
    {
        $effective = $hire->copy()->addYear()->startOfDay();
        if ($effective->greaterThan(Carbon::parse('2026-08-01'))) {
            return;
        }

        try {
            if ($index % 8 === 0) {
                $salaries->increment($guard->fresh(), $rate + 20000, $effective, SalaryChangeReason::LengthOfService, $hr, 'Length-of-service increase after twelve months.');
            } elseif ($index % 15 === 0) {
                $salaries->increment($guard->fresh(), max(120000, $rate - 15000), $effective, SalaryChangeReason::ContractChange, $hr, 'Contract rate reduced after the site review.');
            }
        } catch (Throwable $exception) {
            $this->command?->warn('Salary change skipped for '.$guard->employment_id.': '.$exception->getMessage());
        }
    }

    private function attachDocument(Guard $guard, int $index): void
    {
        if ($guard->attachments()->exists()) {
            return;
        }

        $path = 'guards/scale/identity-card.txt';
        if (! Storage::disk('local')->exists($path)) {
            Storage::disk('local')->put($path, 'Platinum Security identity document for the scale dataset.');
        }

        $types = [GuardDocumentType::NationalId, GuardDocumentType::License, GuardDocumentType::Medical, GuardDocumentType::Contract];
        $type = $types[$index % count($types)];
        GuardAttachment::query()->create([
            'guard_id' => $guard->id,
            'label' => $type->label(),
            'document_type' => $type->value,
            'expires_at' => $type === GuardDocumentType::Medical ? now()->subMonths(2)->toDateString() : now()->addYear()->toDateString(),
            'original_name' => str_replace(' ', '-', strtolower($type->label())).'.txt',
            'path' => $path,
            'mime_type' => 'text/plain',
            'size' => Storage::disk('local')->size($path),
            'uploaded_by' => Auth::id(),
        ]);
    }

    private function seedScaleStaff(): void
    {
        $service = app(StaffService::class);
        $salaries = app(StaffSalaryService::class);
        $hr = Auth::user();
        $titles = [
            ['Finance Officer', 'Finance', 900000],
            ['Admin Assistant', 'Administration', 700000],
            ['Operations Clerk', 'Operations', 750000],
            ['HR Assistant', 'Human Resources', 680000],
            ['Fleet Officer', 'Operations', 820000],
            ['Welfare Officer', 'Human Resources', 760000],
        ];
        $target = 48;
        $i = (int) Staff::query()->where('email', 'like', 'scale.staff%@platinumsecurity.local')->count();
        $created = 0;
        while (Staff::query()->count() < $target && $created < 80) {
            $email = 'scale.staff'.$i.'@platinumsecurity.local';
            if (Staff::query()->where('email', $email)->exists()) {
                $i++;
                continue;
            }
            [$title, $department, $salary] = $titles[$i % count($titles)];
            $amount = $salary + ($i * 8000);
            $member = $service->createStaff([
                'first_name' => $this->firstNames[($i + 4) % 36],
                'last_name' => $this->lastNames[($i + 11) % 36].' Office',
                'job_title' => $title,
                'department' => $department,
                'monthly_salary' => $amount,
                'employment_id' => $service->nextEmploymentId(),
                'phone' => '075'.str_pad((string) (8000000 + $i), 7, '0', STR_PAD_LEFT),
                'email' => $email,
                'bank_name' => 'Centenary Bank',
                'bank_account' => '33'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'date_employed' => Carbon::parse('2025-02-01')->addDays($i % 90)->toDateString(),
                'employment_status' => EmploymentStatus::Active->value,
            ]);
            if ($i % 3 === 0) {
                try {
                    $salaries->change($member->fresh(), $amount + 50000, Carbon::parse('2026-03-01'), StaffSalaryChangeType::Increment, 'Annual office review.', $hr);
                } catch (Throwable) {
                    // The opening date may already be on or after the review.
                }
            }
            if ($i % 11 === 0 && $i > 0) {
                try {
                    $salaries->change($member->fresh(), max(500000, $amount - 40000), Carbon::parse('2026-05-01'), StaffSalaryChangeType::Demotion, 'Role reduced after the department restructure.', $hr);
                } catch (Throwable) {
                    // A later revision may already cover this date on resume.
                }
            }
            $i++;
            $created++;
        }
    }

    private function seedPostings(): void
    {
        if ($this->done('postings')) {
            return;
        }

        $this->command?->line('Posting scale guards and applying promotions…');
        $this->seedPromotions();

        $pool = Guard::query()
            ->where('notes', self::NOTE)
            ->where('employment_status', EmploymentStatus::Active->value)
            ->whereNotIn('operational_status', [
                OperationalStatus::Suspended->value,
                OperationalStatus::OffDuty->value,
                OperationalStatus::Deserted->value,
            ])
            ->whereNull('guard_pay_until')
            ->orderBy('id')
            ->get()
            ->values();
        $cursor = 0;
        $sites = Site::query()->where('code', 'like', 'SCL-%')->where('status', SiteStatus::Active->value)->orderBy('id')->get();

        foreach ($sites as $offset => $site) {
            $pattern = $this->manpowerPattern($offset);
            if ($site->code === 'SCL-001') {
                $pattern = $this->manpowerPattern(0);
            }
            $rotating = $offset % 9 === 4 ? 1 : 0;
            $dayPosts = max(0, $pattern['post_day'] - $rotating);
            $nightPosts = max(0, $pattern['post_night'] - $rotating);
            $cursor = $this->postMany($pool, $cursor, $site, DeploymentShiftType::Day, $dayPosts, false);
            $cursor = $this->postMany($pool, $cursor, $site, DeploymentShiftType::Night, $nightPosts, false);
            $cursor = $this->postMany($pool, $cursor, $site, DeploymentShiftType::Day, $pattern['ot_day'], true);
            if ($rotating > 0) {
                $cursor = $this->postMany($pool, $cursor, $site, DeploymentShiftType::Rotating, 1, false);
            }
        }

        $this->postClosedHistories();
        $this->mark('postings');
    }

    private function seedPromotions(): void
    {
        $position = Position::query()->where('is_supervisor_position', true)->where('is_active', true)->first();
        if ($position === null) {
            $this->command?->warn('No supervisor position is configured, so promotions were skipped.');

            return;
        }

        $service = app(EmployeePromotionService::class);
        $hr = User::query()->where('email', 'hr@platinumsecurity.local')->first();
        $candidates = Guard::query()
            ->where('notes', self::NOTE)
            ->where('employment_status', EmploymentStatus::Active->value)
            ->whereDoesntHave('promotions')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        foreach ($candidates->values() as $index => $guard) {
            try {
                if ($index < 4) {
                    $promotion = $service->schedule($guard, $position, 480000, Carbon::parse('2026-07-01'), 'Promoted to supervisor after the mid-year review.', $hr, 'SCL-PRO-'.$guard->id, 'Applied from the scale dataset.', (int) $guard->region_id);
                    $service->apply($promotion, $hr);
                } else {
                    $service->schedule($guard, $position, 460000, Carbon::parse('2026-10-20'), 'Supervisor promotion scheduled for October.', $hr, 'SCL-SCH-'.$guard->id, 'Waiting for the effective date.', (int) $guard->region_id);
                }
            } catch (Throwable $exception) {
                $this->command?->warn('Promotion skipped for '.$guard->employment_id.': '.$exception->getMessage());
            }
        }
    }

    /** @param  \Illuminate\Support\Collection<int, Guard>  $pool */
    private function postMany($pool, int $cursor, Site $site, DeploymentShiftType $shift, int $count, bool $overtime): int
    {
        for ($n = 0; $n < $count; $n++) {
            $guard = $pool->get($cursor);
            if ($guard === null) {
                return $cursor;
            }
            $cursor++;
            $start = $this->postingStart($guard);
            if ($start->greaterThan(now()->startOfDay())) {
                continue;
            }

            if ($this->postingWouldExceedRequirement($site, $shift)) {
                continue;
            }

            Deployment::query()->create([
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'region_id' => $site->region_id,
                'supervisor_id' => $site->supervisor_id,
                'shift_type' => $shift->value,
                'status' => DeploymentStatus::Active->value,
                'start_date' => $start->toDateString(),
                'end_date' => null,
                'is_current' => true,
                'is_temporary' => $overtime,
                'duty_type' => $overtime ? ShiftType::Overtime->value : ShiftType::Normal->value,
                'notes' => $overtime ? 'Scale posting overtime cover.' : 'Scale posting.',
                'created_by' => Auth::id(),
            ]);
            $guard->update([
                'region_id' => $site->region_id,
                'current_site_id' => $overtime ? $guard->current_site_id : $site->id,
                'current_supervisor_id' => $site->supervisor_id,
                'operational_status' => OperationalStatus::OnDuty->value,
            ]);
        }

        return $cursor;
    }

    private function postingWouldExceedRequirement(Site $site, DeploymentShiftType $shift): bool
    {
        $periods = $shift === DeploymentShiftType::Rotating
            ? [DeploymentShiftType::Day, DeploymentShiftType::Night]
            : [$shift === DeploymentShiftType::Night ? DeploymentShiftType::Night : DeploymentShiftType::Day];

        foreach ($periods as $period) {
            $required = $period === DeploymentShiftType::Night
                ? (int) $site->required_night_guards
                : (int) $site->required_day_guards;
            if ($required <= 0) {
                continue;
            }

            $current = Deployment::query()
                ->current()
                ->where('site_id', $site->id)
                ->where(function ($query) use ($period): void {
                    $query->where('shift_type', $period)
                        ->orWhere('shift_type', DeploymentShiftType::Rotating);
                })
                ->count();

            if ($current >= $required) {
                return true;
            }
        }

        return false;
    }

    private function postingStart(Guard $guard): Carbon
    {
        $hire = $guard->date_employed?->copy()->startOfDay() ?? Carbon::parse(self::HISTORY_FROM);

        return $hire->greaterThan(Carbon::parse(self::HISTORY_FROM)) ? $hire : Carbon::parse(self::HISTORY_FROM);
    }

    private function postClosedHistories(): void
    {
        $sites = Site::query()->where('code', 'like', 'SCL-%')->where('status', SiteStatus::Active->value)->orderBy('id')->get();
        if ($sites->isEmpty()) {
            return;
        }

        Guard::query()
            ->where('notes', self::NOTE)
            ->where(function ($query): void {
                $query->whereNotNull('employment_end_date')->orWhereNotNull('guard_pay_until');
            })
            ->whereDoesntHave('deployments')
            ->orderBy('id')
            ->each(function (Guard $guard) use ($sites): void {
                $end = $guard->employment_end_date ?? $guard->guard_pay_until;
                if ($end === null) {
                    return;
                }
                $start = $this->postingStart($guard);
                if ($start->greaterThan($end)) {
                    return;
                }
                $site = $sites[$guard->id % $sites->count()];
                Deployment::query()->create([
                    'guard_id' => $guard->id,
                    'site_id' => $site->id,
                    'region_id' => $site->region_id,
                    'supervisor_id' => $site->supervisor_id,
                    'shift_type' => DeploymentShiftType::Day->value,
                    'status' => DeploymentStatus::Ended->value,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'is_current' => false,
                    'is_temporary' => false,
                    'duty_type' => ShiftType::Normal->value,
                    'notes' => 'Scale posting closed.',
                    'created_by' => Auth::id(),
                ]);
                $guard->update(['region_id' => $site->region_id]);
            });
    }

    private function seedBlockingLeave(): void
    {
        if ($this->done('availability')) {
            return;
        }

        $this->command?->line('Recording leave that blocks deployment days…');
        $annual = LeaveTypeConfig::query()->where('code', 'annual')->where('is_active', true)->first();
        $sick = LeaveTypeConfig::query()->where('code', 'sick')->where('is_active', true)->first();
        if ($annual === null) {
            $this->mark('availability');

            return;
        }

        $showcaseIds = Deployment::query()
            ->where('notes', 'like', 'Scale posting%')
            ->whereHas('site', fn ($query) => $query->where('code', 'SCL-001'))
            ->pluck('guard_id');
        $guards = Guard::query()
            ->where('notes', self::NOTE)
            ->where('employment_status', EmploymentStatus::Active->value)
            ->whereNotIn('id', $showcaseIds)
            ->orderBy('id')
            ->limit(80)
            ->get();
        $leave = app(LeaveService::class);
        Auth::login(User::query()->where('email', 'hr@platinumsecurity.local')->first());

        foreach ($guards->values() as $index => $guard) {
            $start = Carbon::parse('2025-03-02')->addDays(($index * 17) % 500);
            if ($guard->date_employed !== null && $start->lt($guard->date_employed)) {
                $start = $guard->date_employed->copy()->addDays(14);
            }
            if ($start->greaterThan(now()->subDays(10))) {
                $start = now()->copy()->subDays(10);
            }
            $this->tryLeave($leave, [
                'guard_id' => $guard->id,
                'leave_type_id' => $annual->id,
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addDays(3)->toDateString(),
                'reason' => 'Annual leave.',
                'correction_reason' => 'Historical leave entered for the scale company.',
            ], true);

            if ($sick !== null && $index % 4 === 0) {
                $sickStart = $start->copy()->addDays(40);
                if ($sickStart->lt(now()->subDay())) {
                    $this->tryLeave($leave, [
                        'guard_id' => $guard->id,
                        'leave_type_id' => $sick->id,
                        'start_date' => $sickStart->toDateString(),
                        'end_date' => $sickStart->copy()->addDay()->toDateString(),
                        'reason' => 'Clinic visit.',
                        'document_path' => 'guards/scale/identity-card.txt',
                        'correction_reason' => 'Historical sick leave entered for the scale company.',
                    ], true);
                }
            }

            if ($index < 18) {
                $this->tryLeave($leave, [
                    'guard_id' => $guard->id,
                    'leave_type_id' => $annual->id,
                    'start_date' => now()->toDateString(),
                    'end_date' => now()->copy()->addDays(3)->toDateString(),
                    'reason' => 'Leave in progress.',
                ], true);
            }
        }

        $this->mark('availability');
    }

    /** @param  array<string, mixed>  $data */
    private function tryLeave(LeaveService $leave, array $data, bool $approve): void
    {
        try {
            $record = $leave->create($data);
            if ($approve) {
                $leave->approve($record, 'Approved for the scale company roster.');
            }
        } catch (Throwable $exception) {
            $this->command?->warn('Leave skipped: '.$exception->getMessage());
        }
    }

    private function seedShifts(): void
    {
        if ($this->done('shifts')) {
            return;
        }

        $this->command?->line('Writing historical duties from '.self::HISTORY_FROM.' through '.self::HISTORY_UNTIL.'…');
        DB::table('shifts')->where('reference', 'like', 'SCL-%')->delete();
        $blocked = $this->blockedLeaveDays();
        $columns = array_flip(Schema::getColumnListing('shifts'));
        $yesterday = Carbon::parse(self::HISTORY_UNTIL)->startOfDay();
        $rows = [];
        $guards = 0;

        Deployment::query()
            ->where('notes', 'like', 'Scale posting%')
            ->with('assignedGuard:id,date_employed,employment_end_date,guard_pay_until,guard_classification')
            ->orderBy('guard_id')
            ->orderBy('start_date')
            ->chunk(200, function ($deployments) use (&$rows, &$guards, $blocked, $columns, $yesterday): void {
                foreach ($deployments as $deployment) {
                    $guards++;
                    $this->appendDutyRows($deployment, $blocked, $yesterday, $yesterday, $rows);
                    if (count($rows) >= 250) {
                        $this->insertShifts($rows, $columns);
                        $rows = [];
                    }
                }
            });

        if ($rows !== []) {
            $this->insertShifts($rows, $columns);
        }

        $this->command?->line('  duties written for '.$guards.' postings');
        $this->mark('shifts');
    }

    /** @return array<string, true> */
    private function blockedLeaveDays(): array
    {
        $blocked = [];
        DB::table('leaves')
            ->where('status', 'approved')
            ->whereNotNull('guard_id')
            ->orderBy('id')
            ->select('guard_id', 'start_date', 'end_date')
            ->chunk(500, function ($leaves) use (&$blocked): void {
                foreach ($leaves as $leave) {
                    $cursor = Carbon::parse($leave->start_date);
                    $end = Carbon::parse($leave->end_date);
                    while ($cursor->lte($end)) {
                        $blocked[$leave->guard_id.'|'.$cursor->toDateString()] = true;
                        $cursor->addDay();
                    }
                }
            });

        return $blocked;
    }

    /**
     * @param  array<string, true>  $blocked
     * @param  list<array<string, mixed>>  $rows
     */
    private function appendDutyRows(Deployment $deployment, array $blocked, Carbon $yesterday, Carbon $futureEnd, array &$rows, ?Carbon $notBefore = null): void
    {
        $guard = $deployment->assignedGuard;
        if ($guard === null) {
            return;
        }

        $start = Carbon::parse($deployment->start_date)->startOfDay();
        if ($notBefore !== null && $start->lt($notBefore)) {
            $start = $notBefore->copy();
        }
        $end = $deployment->end_date ? Carbon::parse($deployment->end_date)->startOfDay() : $futureEnd->copy();
        if ($guard->employment_end_date !== null && $guard->employment_end_date->lt($end)) {
            $end = $guard->employment_end_date->copy()->startOfDay();
        }
        if ($guard->guard_pay_until !== null && $guard->guard_pay_until->lt($end)) {
            $end = $guard->guard_pay_until->copy()->startOfDay();
        }
        if ($end->gt($futureEnd)) {
            $end = $futureEnd->copy();
        }
        if ($start->gt($end)) {
            return;
        }

        $overtime = (bool) $deployment->is_temporary;
        $rotating = $deployment->shift_type === DeploymentShiftType::Rotating;
        $occupied = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            if ($cursor->gt($yesterday)) {
                break;
            }
            $day = (int) $cursor->format('j');
            $worked = $overtime ? $day <= 8 : $day <= 26;
            $date = $cursor->toDateString();
            $period = $rotating
                ? (((int) $cursor->format('W') % 2 === 0) ? ShiftPeriod::Day : ShiftPeriod::Night)
                : ($deployment->shift_type === DeploymentShiftType::Night ? ShiftPeriod::Night : ShiftPeriod::Day);
            $slot = $guard->id.'|'.$date.'|'.$period->value;
            if ($worked && ! isset($blocked[$guard->id.'|'.$date]) && ! isset($occupied[$slot])) {
                $status = ShiftStatus::Recorded;
                if ($day === 20 && $guard->id % 23 === 0) {
                    $status = ShiftStatus::Cancelled;
                }
                $rows[] = $this->shiftRow($deployment, $guard, $cursor->copy(), $period, $overtime ? ShiftType::Overtime : ShiftType::Normal, $status);
                $occupied[$slot] = true;
            }
            if (! $overtime && $day === 27 && $cursor->daysInMonth >= 27 && ! isset($occupied[$guard->id.'|'.$date.'|missed'])) {
                $rows[] = $this->shiftRow($deployment, $guard, $cursor->copy(), $period, ShiftType::Normal, ShiftStatus::Missed);
            }
            $cursor->addDay();
        }
    }

    /** @return array<string, mixed> */
    private function shiftRow(Deployment $deployment, Guard $guard, Carbon $date, ShiftPeriod $period, ShiftType $type, ShiftStatus $status): array
    {
        $night = $period === ShiftPeriod::Night;
        $start = $date->copy()->setTime($night ? 18 : 6, 0);
        $end = $night ? $date->copy()->addDay()->setTime(6, 0) : $date->copy()->setTime(18, 0);
        $mark = $night ? 'N' : 'D';
        if ($status === ShiftStatus::Missed) {
            $mark .= 'M';
        }
        if ($status === ShiftStatus::Cancelled) {
            $mark .= 'C';
        }

        return [
            'reference' => 'SCL-'.$date->format('Ymd').'-'.$guard->id.'-'.$mark,
            'guard_id' => $guard->id,
            'site_id' => $deployment->site_id,
            'region_id' => $deployment->region_id,
            'supervisor_id' => $deployment->supervisor_id,
            'deployment_id' => $deployment->id,
            'shift_date' => $date->toDateString(),
            'starts_at' => $start->format('Y-m-d H:i:s'),
            'ends_at' => $end->format('Y-m-d H:i:s'),
            'period' => $period->value,
            'shift_type' => $type->value,
            'guard_classification' => $guard->guard_classification?->value ?? GuardClassification::Unarmed->value,
            'status' => $status->value,
            'same_shift_slot' => $status->blocksCalendarSlot() ? $guard->id.'|'.$date->toDateString().'|'.$period->value : null,
            'is_overnight' => $night,
            'notes' => 'Scale company duty.',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
            'created_at' => now()->format('Y-m-d H:i:s'),
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, int>  $columns
     */
    private function insertShifts(array $rows, array $columns): void
    {
        $payload = [];
        foreach ($rows as $row) {
            $payload[] = array_intersect_key($row, $columns);
        }
        foreach (array_chunk($payload, 200) as $chunk) {
            DB::table('shifts')->insert($chunk);
        }
    }

    private function seedWorkflowEvents(): void
    {
        if ($this->done('events')) {
            return;
        }

        $this->command?->line('Recording transfers, replacements, and attendance…');
        Auth::login(User::query()->where('email', 'shifts@platinumsecurity.local')->first() ?? Auth::user());
        $this->seedTransfers();
        $this->seedUpcomingDuties();
        $this->seedReplacementSamples();
        $this->seedPendingLeave();
        $this->seedAttendance();
        $this->mark('events');
    }

    private function seedTransfers(): void
    {
        $service = app(DeploymentService::class);
        $deployments = Deployment::query()
            ->where('notes', 'Scale posting.')
            ->where('is_current', true)
            ->where('is_temporary', false)
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        $done = 0;
        foreach ($deployments as $deployment) {
            if ($done >= 12) {
                break;
            }
            $destination = Site::query()
                ->where('region_id', $deployment->region_id)
                ->where('code', 'like', 'SCL-%')
                ->where('status', SiteStatus::Active->value)
                ->where('id', '!=', $deployment->site_id)
                ->where('code', '!=', 'SCL-001')
                ->inRandomOrder()
                ->first();
            if ($destination === null) {
                continue;
            }
            try {
                $service->transfer($deployment, [
                    'site_id' => $destination->id,
                    'shift_type' => $deployment->shift_type?->value ?? DeploymentShiftType::Day->value,
                    'effective_date' => now()->toDateString(),
                    'reason' => 'Client asked for this guard at a site with open capacity.',
                    'notes' => 'Scale company transfer.',
                ]);
                $done++;
            } catch (Throwable) {
                // Capacity or a closed period skips this guard.
            }
        }
    }

    private function seedUpcomingDuties(): void
    {
        $futureIds = DB::table('shifts')
            ->where('reference', 'like', 'SCL-%')
            ->where('shift_date', '>', self::HISTORY_UNTIL)
            ->pluck('id');

        foreach ($futureIds->chunk(500) as $ids) {
            DB::table('shift_replacements')->whereIn('original_shift_id', $ids)->delete();
            DB::table('shift_replacements')->whereIn('replacement_shift_id', $ids)->delete();
            DB::table('shifts')->whereIn('id', $ids)->delete();
        }
    }

    private function seedReplacementSamples(): void
    {
        $annual = LeaveTypeConfig::query()->where('code', 'annual')->where('is_active', true)->first();
        if ($annual === null) {
            return;
        }

        $when = now()->addDays(6)->toDateString();
        $shifts = Shift::query()
            ->where('notes', 'Scale company duty.')
            ->where('shift_date', $when)
            ->where('status', ShiftStatus::Scheduled->value)
            ->limit(8)
            ->get();
        $leave = app(LeaveService::class);
        $replacements = app(ReplacementService::class);
        $relief = Guard::query()
            ->where('notes', self::NOTE)
            ->where('operational_status', OperationalStatus::AwaitingDeployment->value)
            ->first();
        if ($relief === null) {
            return;
        }

        foreach ($shifts as $shift) {
            try {
                $record = $leave->create([
                    'guard_id' => $shift->guard_id,
                    'leave_type_id' => $annual->id,
                    'start_date' => $when,
                    'end_date' => $when,
                    'reason' => 'Family commitment on a scheduled duty.',
                ]);
                $leave->approve($record, 'Approved. The scheduled duty needs a replacement.');
                $replacements->record([
                    'original_shift_id' => $shift->id,
                    'replacement_guard_id' => $relief->id,
                    'reason' => ReplacementReason::Leave->value,
                    'notes' => 'Relief arranged from the scale roster.',
                    'acknowledge_warnings' => true,
                ]);
                break;
            } catch (Throwable $exception) {
                $this->command?->warn('Replacement sample skipped: '.$exception->getMessage());
            }
        }
    }

    private function seedPendingLeave(): void
    {
        $annual = LeaveTypeConfig::query()->where('code', 'annual')->where('is_active', true)->first();
        if ($annual === null) {
            return;
        }
        $leave = app(LeaveService::class);
        $guards = Guard::query()->where('notes', self::NOTE)->orderBy('id')->limit(12)->get();
        foreach ($guards->values() as $index => $guard) {
            $start = now()->copy()->addDays(25 + $index);
            $this->tryLeave($leave, [
                'guard_id' => $guard->id,
                'leave_type_id' => $annual->id,
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addDay()->toDateString(),
                'reason' => $index % 2 === 0 ? 'Personal errand awaiting cover.' : 'Travel request.',
            ], false);
            if ($index % 2 === 1) {
                try {
                    $pending = $leave->create([
                        'guard_id' => $guard->id,
                        'leave_type_id' => $annual->id,
                        'start_date' => $start->copy()->addDays(10)->toDateString(),
                        'end_date' => $start->copy()->addDays(11)->toDateString(),
                        'reason' => 'Dates the roster cannot release.',
                    ]);
                    $leave->reject($pending, 'Cover is not available on those dates.');
                } catch (Throwable) {
                    // Entitlement or overlap ends this sample.
                }
            }
        }
    }

    private function seedAttendance(): void
    {
        $service = app(AttendanceService::class);
        $shifts = Shift::query()
            ->where('notes', 'Scale company duty.')
            ->where('shift_date', '2026-08-03')
            ->where('status', ShiftStatus::Recorded->value)
            ->limit(24)
            ->get();
        foreach ($shifts as $index => $shift) {
            try {
                $service->record([
                    'guard_id' => $shift->guard_id,
                    'event_type' => $index % 2 === 0 ? 'on_duty' : 'off_duty',
                    'occurred_at' => '2026-08-03 '.($index % 2 === 0 ? '06:05:00' : '18:05:00'),
                    'site_id' => $shift->site_id,
                    'notes' => 'Scale company attendance for the August duty.',
                    'correction_reason' => 'Historical attendance entered for the scale company.',
                ]);
            } catch (Throwable) {
                // A closed attendance period skips the row.
            }
        }
    }

    private function seedPayroll(): void
    {
        if ($this->done('payroll')) {
            return;
        }

        $payroll = app(PayrollRunService::class);
        $finance = User::query()->where('email', 'finance@platinumsecurity.local')->firstOrFail();
        $approver = User::query()->where('email', 'md@platinumsecurity.local')->firstOrFail();
        Auth::login($finance);
        $closed = PayrollRunService::lastClosedPeriod()->startOfMonth();
        $cursor = Carbon::parse(self::HISTORY_FROM)->startOfMonth();

        while ($cursor->lte($closed)) {
            $existing = PayrollRun::query()
                ->where('period_year', $cursor->year)
                ->where('period_month', $cursor->month)
                ->whereNull('region_id')
                ->whereNull('site_id')
                ->where('status', '!=', PayrollRunStatus::Cancelled->value)
                ->first();
            if ($existing !== null && str_contains((string) $existing->notes, 'Scale company payroll')) {
                if (in_array($existing->status, [PayrollRunStatus::Approved, PayrollRunStatus::Paid], true)) {
                    app(LedgerPostingService::class)->postPayrollAccrual($existing, $approver);
                    $cursor->addMonth();
                    continue;
                }
                $this->command?->line('Finishing '.$cursor->format('F Y').' payroll…');
                if ($existing->status === PayrollRunStatus::Draft) {
                    $existing = $payroll->calculate($existing);
                }
                if ($existing->status === PayrollRunStatus::Calculated) {
                    $existing = $payroll->submit($existing, $finance);
                }
                if ($existing->status === PayrollRunStatus::Submitted) {
                    $payroll->approve($existing, $approver);
                }
                $cursor->addMonth();
                continue;
            }

            $this->command?->line('Calculating '.$cursor->format('F Y').' payroll…');
            if ($existing !== null) {
                $payroll->cancel($existing);
            }
            $run = $payroll->createDraft([
                'period_year' => (int) $cursor->year,
                'period_month' => (int) $cursor->month,
                'notes' => 'Scale company payroll from recorded duties and the salary in force that month.',
            ], $finance);
            $run = $payroll->calculate($run);
            $run = $payroll->submit($run, $finance);
            $payroll->approve($run, $approver);
            $cursor->addMonth();
        }

        $this->mark('payroll');
    }

    private function seedInvoices(): void
    {
        if ($this->done('invoices')) {
            return;
        }

        $this->command?->line('Invoicing scale sites from their billing profiles…');
        $invoices = app(InvoiceService::class);
        $payments = app(PaymentService::class);
        Auth::login(User::query()->where('email', 'finance@platinumsecurity.local')->first());
        $closed = PayrollRunService::lastClosedPeriod()->startOfMonth();
        $sites = Site::query()
            ->where('code', 'like', 'SCL-%')
            ->where('status', SiteStatus::Active->value)
            ->orderBy('id')
            ->limit(24)
            ->get();
        $cursor = Carbon::parse(self::HISTORY_FROM)->startOfMonth();

        while ($cursor->lte($closed)) {
            $periodStart = $cursor->copy()->startOfMonth()->toDateString();
            $periodEnd = $cursor->copy()->endOfMonth()->toDateString();
            $issueDate = $cursor->copy()->addMonth()->startOfMonth()->toDateString();
            $isLatest = $cursor->isSameMonth($closed);
            $isPrevious = $cursor->isSameMonth($closed->copy()->subMonth());

            foreach ($sites as $siteIndex => $site) {
                if (DB::table('invoices')->where('site_id', $site->id)->whereDate('period_start', $periodStart)->exists()) {
                    continue;
                }
                $lines = $invoices->suggestLines((int) $site->client_id, (int) $site->id, $periodStart, $periodEnd);
                if ($lines === []) {
                    continue;
                }
                $dueDate = $isLatest && $siteIndex < 8
                    ? now()->subDays(12)->toDateString()
                    : $cursor->copy()->addMonth()->day(15)->toDateString();
                $invoice = $invoices->createDraft([
                    'client_id' => $site->client_id,
                    'site_id' => $site->id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'due_date' => $dueDate,
                    'notes' => $cursor->format('F Y').' invoice from the scale site billing profile.',
                    'lines' => $lines,
                ]);
                $invoice = $invoices->issue($invoice, $issueDate);
                $payInFull = ! $isLatest && ! ($isPrevious && $siteIndex < 6);
                $payHalf = $isPrevious && $siteIndex < 6;
                if ($payInFull || $payHalf) {
                    $payments->record([
                        'invoice_id' => $invoice->id,
                        'amount' => $payHalf ? round(((float) $invoice->total) / 2, 2) : (float) $invoice->total,
                        'payment_date' => $cursor->copy()->addMonth()->day(min(10, $cursor->copy()->addMonth()->daysInMonth))->toDateString(),
                        'notes' => $payHalf ? 'Part payment. Balance remains on this invoice.' : 'Paid in full from the scale billing profile.',
                    ]);
                }
            }
            $cursor->addMonth();
        }

        $invoices->markOverdueInvoices();
        $this->mark('invoices');
    }

    private function releaseDuplicateCompletedDuties(): void
    {
        $blocking = [
            ShiftStatus::Scheduled->value,
            ShiftStatus::Confirmed->value,
            ShiftStatus::InProgress->value,
            ShiftStatus::Recorded->value,
            ShiftStatus::Completed->value,
        ];
        $groups = DB::table('shifts')
            ->select('guard_id', 'shift_date', 'period')
            ->whereIn('status', $blocking)
            ->groupBy('guard_id', 'shift_date', 'period')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $recorded = DB::table('shifts')
                ->where('guard_id', $group->guard_id)
                ->where('shift_date', $group->shift_date)
                ->where('period', $group->period)
                ->where('status', ShiftStatus::Recorded->value)
                ->exists();
            if (! $recorded) {
                continue;
            }

            DB::table('shifts')
                ->where('guard_id', $group->guard_id)
                ->where('shift_date', $group->shift_date)
                ->where('period', $group->period)
                ->where('status', ShiftStatus::Completed->value)
                ->update([
                    'status' => ShiftStatus::Cancelled->value,
                    'same_shift_slot' => null,
                    'notes' => DB::raw("CONCAT(COALESCE(notes, ''), ' Duplicate completed duty closed because the recorded duty is the payable one.')"),
                ]);
        }
    }

    private function probe(): void
    {
        $this->command?->newLine();
        $this->command?->line('Response samples:');
        $samples = [
            'Guard page' => fn () => Guard::query()->with(['region:id,name', 'currentSite:id,name'])->orderBy('employment_id')->paginate(25),
            'Site page' => fn () => Site::query()->with(['client:id,name', 'region:id,name'])->orderBy('name')->paginate(25),
            'August shifts' => fn () => Shift::query()->whereBetween('shift_date', ['2026-08-01', '2026-08-31'])->count(),
            'Open deployments' => fn () => Deployment::query()->where('is_current', true)->count(),
        ];
        foreach ($samples as $label => $sample) {
            $start = microtime(true);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $sample();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();
            $this->command?->line(sprintf('  %s: %d ms, %d queries', $label, (int) ((microtime(true) - $start) * 1000), $queries));
        }
    }

    private function done(string $step): bool
    {
        return (bool) ($this->progress()[$step] ?? false);
    }

    private function mark(string $step): void
    {
        $progress = $this->progress();
        $progress[$step] = true;
        Storage::disk('local')->put('scale-seed.json', json_encode($progress));
    }

    /** @return array<string, bool> */
    private function progress(): array
    {
        if (! Storage::disk('local')->exists('scale-seed.json')) {
            return [];
        }

        $decoded = json_decode((string) Storage::disk('local')->get('scale-seed.json'), true);

        return is_array($decoded) ? $decoded : [];
    }
}
