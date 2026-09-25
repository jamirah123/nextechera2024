<?php

namespace Database\Seeders;

use App\Enums\CompensationType;
use App\Enums\ContractStatus;
use App\Enums\EmploymentStatus;
use App\Enums\GuardClassification;
use App\Enums\GuardGender;
use App\Enums\OperationalStatus;
use App\Enums\SiteStatus;
use App\Models\BillingProfile;
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
use Database\Seeders\Concerns\SeedsBillingAndInvoices;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Expands the small-company footprint with more clients, sites, guards, and office staff.
 * Safe to re-run (skips existing codes / names / employment IDs).
 */
class ExpandOrgSeeder extends Seeder
{
    use SeedsBillingAndInvoices;

    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first();
        if ($admin) {
            Auth::login($admin);
        }

        $organization = app(OrganizationService::class);

        $kampala = Region::query()->where('code', 'KLA')->firstOrFail();
        $western = Region::query()->where('code', 'WES')->firstOrFail();
        $klaSupervisor = Supervisor::query()->where('supervisor_code', 'SUP0001')->firstOrFail();
        $wesSupervisor = Supervisor::query()->where('supervisor_code', 'SUP0002')->firstOrFail();

        $before = $this->counts();

        $klaClients = $this->seedClients([
            [
                'name' => 'Entebbe Logistics Hub',
                'contact_person' => 'Grace Namuli',
                'phone' => '+256750000021',
                'email' => 'security@entebbelogistics.local',
                'address' => 'Airport Road, Entebbe',
                'notes' => 'Cargo yard and gate security.',
            ],
            [
                'name' => 'Kololo Residences Ltd',
                'contact_person' => 'Mark Ssewanyana',
                'phone' => '+256750000022',
                'email' => 'manager@kololoresidences.local',
                'address' => 'Acacia Avenue, Kololo',
                'notes' => 'Residential compound security.',
            ],
            [
                'name' => 'Nakawa Industrial Park',
                'contact_person' => 'Joyce Nabirye',
                'phone' => '+256750000023',
                'email' => 'ops@nakawapark.local',
                'address' => 'Nakawa Industrial Area, Kampala',
                'notes' => 'Multi-unit industrial park gates.',
            ],
        ]);

        $wesClients = $this->seedClients([
            [
                'name' => 'Kasese Mining Support',
                'contact_person' => 'Tom Bwambale',
                'phone' => '+256750000031',
                'email' => 'security@kasesemining.local',
                'address' => 'Rwenzori Road, Kasese',
                'notes' => 'Depot and access-road security.',
            ],
            [
                'name' => 'Bushenyi Tea Estates',
                'contact_person' => 'Agnes Tumusiime',
                'phone' => '+256750000032',
                'email' => 'admin@bushenyitea.local',
                'address' => 'Ishaka, Bushenyi',
                'notes' => 'Estate perimeter and factory gate.',
            ],
            [
                'name' => 'Hoima Oilfield Services',
                'contact_person' => 'Isaac Ocaya',
                'phone' => '+256750000033',
                'email' => 'site@hoimaoil.local',
                'address' => 'Kiziranfumbi Road, Hoima',
                'notes' => 'Camp and yard security.',
            ],
        ]);

        $this->seedSites($kampala, $klaSupervisor, $organization, [
            [
                'code' => 'KLA-EH1',
                'name' => 'Entebbe Logistics Main Gate',
                'client' => $klaClients[0],
                'location' => 'Airport Road, Entebbe',
                'contact' => 'Grace Namuli',
                'phone' => '+256750000021',
                'notes' => 'Primary cargo gate.',
                'day' => 3,
                'night' => 3,
            ],
            [
                'code' => 'KLA-KR1',
                'name' => 'Kololo Residences Gate',
                'client' => $klaClients[1],
                'location' => 'Acacia Avenue, Kololo',
                'contact' => 'Mark Ssewanyana',
                'phone' => '+256750000022',
                'notes' => 'Compound entrance.',
                'day' => 2,
                'night' => 2,
            ],
            [
                'code' => 'KLA-NP1',
                'name' => 'Nakawa Park East Gate',
                'client' => $klaClients[2],
                'location' => 'Nakawa Industrial Area, Kampala',
                'contact' => 'Joyce Nabirye',
                'phone' => '+256750000023',
                'notes' => 'East industrial gate.',
                'day' => 2,
                'night' => 3,
            ],
            [
                'code' => 'KLA-NP2',
                'name' => 'Nakawa Park West Gate',
                'client' => $klaClients[2],
                'location' => 'Nakawa Industrial Area, Kampala',
                'contact' => 'Joyce Nabirye',
                'phone' => '+256750000023',
                'notes' => 'West industrial gate.',
                'day' => 2,
                'night' => 2,
            ],
        ]);

        $this->seedSites($western, $wesSupervisor, $organization, [
            [
                'code' => 'WES-KM1',
                'name' => 'Kasese Mining Depot Gate',
                'client' => $wesClients[0],
                'location' => 'Rwenzori Road, Kasese',
                'contact' => 'Tom Bwambale',
                'phone' => '+256750000031',
                'notes' => 'Depot main gate.',
                'day' => 2,
                'night' => 3,
            ],
            [
                'code' => 'WES-BT1',
                'name' => 'Bushenyi Tea Factory Gate',
                'client' => $wesClients[1],
                'location' => 'Ishaka, Bushenyi',
                'contact' => 'Agnes Tumusiime',
                'phone' => '+256750000032',
                'notes' => 'Factory entrance.',
                'day' => 2,
                'night' => 2,
            ],
            [
                'code' => 'WES-HO1',
                'name' => 'Hoima Camp Gate',
                'client' => $wesClients[2],
                'location' => 'Kiziranfumbi Road, Hoima',
                'contact' => 'Isaac Ocaya',
                'phone' => '+256750000033',
                'notes' => 'Camp perimeter gate.',
                'day' => 3,
                'night' => 3,
            ],
            [
                'code' => 'WES-HO2',
                'name' => 'Hoima Yard Post',
                'client' => $wesClients[2],
                'location' => 'Equipment Yard, Hoima',
                'contact' => 'Isaac Ocaya',
                'phone' => '+256750000033',
                'notes' => 'Equipment yard post.',
                'day' => 2,
                'night' => 2,
            ],
        ]);

        $this->seedGuards($kampala, 'Kampala, Uganda', 100, [
            ['first_name' => 'Joseph', 'last_name' => 'Wasswa', 'gender' => GuardGender::Male],
            ['first_name' => 'Flavia', 'last_name' => 'Nankya', 'gender' => GuardGender::Female],
            ['first_name' => 'Henry', 'last_name' => 'Mukasa', 'gender' => GuardGender::Male],
            ['first_name' => 'Patricia', 'last_name' => 'Namuganza', 'gender' => GuardGender::Female],
            ['first_name' => 'Charles', 'last_name' => 'Ouma', 'gender' => GuardGender::Male],
            ['first_name' => 'Betty', 'last_name' => 'Akello', 'gender' => GuardGender::Female],
            ['first_name' => 'Godfrey', 'last_name' => 'Ssebunya', 'gender' => GuardGender::Male],
            ['first_name' => 'Susan', 'last_name' => 'Nambi', 'gender' => GuardGender::Female],
            ['first_name' => 'Alex', 'last_name' => 'Kiggundu', 'gender' => GuardGender::Male],
            ['first_name' => 'Harriet', 'last_name' => 'Nakazzi', 'gender' => GuardGender::Female],
            ['first_name' => 'Moses', 'last_name' => 'Lwanga', 'gender' => GuardGender::Male],
            ['first_name' => 'Christine', 'last_name' => 'Auma', 'gender' => GuardGender::Female],
        ]);

        $this->seedGuards($western, 'Mbarara, Uganda', 200, [
            ['first_name' => 'Emmanuel', 'last_name' => 'Tumwesigye', 'gender' => GuardGender::Male],
            ['first_name' => 'Annet', 'last_name' => 'Kembabazi', 'gender' => GuardGender::Female],
            ['first_name' => 'Stephen', 'last_name' => 'Mugisha', 'gender' => GuardGender::Male],
            ['first_name' => 'Gloria', 'last_name' => 'Natukunda', 'gender' => GuardGender::Female],
            ['first_name' => 'Patrick', 'last_name' => 'Ahimbisibwe', 'gender' => GuardGender::Male],
            ['first_name' => 'Jackie', 'last_name' => 'Tusiime', 'gender' => GuardGender::Female],
            ['first_name' => 'Ivan', 'last_name' => 'Ndyomugyenyi', 'gender' => GuardGender::Male],
            ['first_name' => 'Mercy', 'last_name' => 'Kobusingye', 'gender' => GuardGender::Female],
            ['first_name' => 'Ronald', 'last_name' => 'Mwesigwa', 'gender' => GuardGender::Male],
            ['first_name' => 'Sheila', 'last_name' => 'Atukunda', 'gender' => GuardGender::Female],
            ['first_name' => 'Bruno', 'last_name' => 'Kato', 'gender' => GuardGender::Male],
            ['first_name' => 'Diana', 'last_name' => 'Nuwamanya', 'gender' => GuardGender::Female],
        ]);

        $this->seedStaff([
            ['first_name' => 'Nakato', 'last_name' => 'Sarah', 'job_title' => 'Payroll Officer', 'department' => 'Finance', 'monthly_salary' => 850000],
            ['first_name' => 'Okello', 'last_name' => 'Daniel', 'job_title' => 'HR Officer', 'department' => 'Human Resources', 'monthly_salary' => 880000],
            ['first_name' => 'Namara', 'last_name' => 'Patience', 'job_title' => 'Accounts Clerk', 'department' => 'Finance', 'monthly_salary' => 720000],
            ['first_name' => 'Kayanja', 'last_name' => 'Fred', 'job_title' => 'Fleet Coordinator', 'department' => 'Operations', 'monthly_salary' => 780000],
            ['first_name' => 'Aciro', 'last_name' => 'Lydia', 'job_title' => 'Receptionist', 'department' => 'Administration', 'monthly_salary' => 650000],
            ['first_name' => 'Wamala', 'last_name' => 'Isaac', 'job_title' => 'IT Support', 'department' => 'Administration', 'monthly_salary' => 800000],
            ['first_name' => 'Birungi', 'last_name' => 'Claire', 'job_title' => 'Compliance Officer', 'department' => 'Operations', 'monthly_salary' => 920000],
        ]);

        $this->seedRealisticBillingProfiles(now()->subMonths(4)->startOfMonth());

        Auth::logout();

        $after = $this->counts();

        $this->command?->newLine();
        $this->command?->info('=== Expand org seed ===');
        foreach ($after as $label => $count) {
            $added = $count - ($before[$label] ?? 0);
            $suffix = $added > 0 ? " (+{$added})" : '';
            $this->command?->info("{$label}: {$count}{$suffix}");
        }
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        return [
            'Clients' => Client::query()->count(),
            'Sites' => Site::query()->count(),
            'Guards' => Guard::query()->count(),
            'Staff' => Staff::query()->count(),
            'Billing profiles' => BillingProfile::query()->count(),
        ];
    }

    /**
     * @param  list<array{name: string, contact_person: string, phone: string, email: string, address: string, notes: string}>  $defs
     * @return list<Client>
     */
    private function seedClients(array $defs): array
    {
        $clients = [];

        foreach ($defs as $def) {
            $clients[] = Client::query()->updateOrCreate(
                ['name' => $def['name']],
                [
                    'contact_person' => $def['contact_person'],
                    'phone' => $def['phone'],
                    'email' => $def['email'],
                    'address' => $def['address'],
                    'contract_start_date' => now()->subMonths(4)->toDateString(),
                    'contract_end_date' => now()->addMonths(8)->toDateString(),
                    'contract_status' => ContractStatus::Active,
                    'notes' => $def['notes'],
                ],
            );
        }

        return $clients;
    }

    /**
     * @param  list<array{code: string, name: string, client: Client, location: string, contact: string, phone: string, notes: string, day: int, night: int}>  $defs
     */
    private function seedSites(Region $region, Supervisor $supervisor, OrganizationService $organization, array $defs): void
    {
        foreach ($defs as $def) {
            $day = $def['day'];
            $night = $def['night'];

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
                    'contract_start_date' => now()->subMonths(3)->toDateString(),
                    'contract_end_date' => now()->addMonths(9)->toDateString(),
                    'required_guards' => $day + $night,
                    'required_day_guards' => $day,
                    'required_day_armed_guards' => 0,
                    'required_day_unarmed_guards' => $day,
                    'required_night_guards' => $night,
                    'required_night_armed_guards' => 0,
                    'required_night_unarmed_guards' => $night,
                    'number_of_posts' => max(2, (int) ceil(($day + $night) / 2)),
                    'status' => SiteStatus::Active,
                    'notes' => $def['notes'],
                ],
            );

            if ($site->manpowerRequirements()->where('is_current', true)->doesntExist()) {
                $organization->syncSiteManpower($site, 'Expanded org seed manpower');
            }
        }
    }

    /**
     * @param  list<array{first_name: string, last_name: string, gender: GuardGender}>  $defs
     */
    private function seedGuards(Region $region, string $address, int $phoneStartIndex, array $defs): void
    {
        $service = app(GuardService::class);
        $monthlyGross = (float) config('psg.payroll.default_monthly_gross', 170000);
        if ($monthlyGross <= 0) {
            $monthlyGross = 170000;
        }

        foreach ($defs as $offset => $def) {
            $existing = Guard::query()
                ->where('first_name', $def['first_name'])
                ->where('last_name', $def['last_name'])
                ->first();

            if ($existing) {
                if ((float) $existing->base_shift_rate <= 0) {
                    $service->updateGuard($existing, [
                        'compensation_type' => CompensationType::Shift->value,
                        'base_shift_rate' => $monthlyGross,
                        'bank_name' => $existing->bank_name ?: 'Stanbic Bank',
                        'bank_account' => $existing->bank_account ?: '42'.str_pad((string) $existing->id, 8, '0', STR_PAD_LEFT),
                        'nssf_number' => $existing->nssf_number ?: 'NSSF'.str_pad((string) $existing->id, 6, '0', STR_PAD_LEFT),
                    ], 'expand_org_seed_salary');
                }

                continue;
            }

            $phoneIndex = $phoneStartIndex + $offset;

            $service->createGuard([
                'employment_id' => $service->nextEmploymentId(),
                'first_name' => $def['first_name'],
                'last_name' => $def['last_name'],
                'region_id' => $region->id,
                'gender' => $def['gender']->value,
                'phone' => '+25670020'.str_pad((string) $phoneIndex, 4, '0', STR_PAD_LEFT),
                'date_employed' => now()->subMonths(1 + ($offset % 5))->toDateString(),
                'employment_status' => EmploymentStatus::Active->value,
                'operational_status' => OperationalStatus::AwaitingDeployment->value,
                'rank_designation' => 'Security Guard',
                'guard_classification' => GuardClassification::Unarmed->value,
                'address' => $address,
                'compensation_type' => CompensationType::Shift->value,
                'base_shift_rate' => $monthlyGross,
                'bank_name' => 'Stanbic Bank',
                'bank_account' => '42'.str_pad((string) $phoneIndex, 8, '0', STR_PAD_LEFT),
                'nssf_number' => 'NSSF'.str_pad((string) $phoneIndex, 6, '0', STR_PAD_LEFT),
            ]);
        }
    }

    /**
     * @param  list<array{first_name: string, last_name: string, job_title: string, department: string, monthly_salary: int}>  $members
     */
    private function seedStaff(array $members): void
    {
        $service = app(StaffService::class);

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
                'phone' => '07'.str_pad((string) (52000000 + $index), 8, '0', STR_PAD_LEFT),
                'email' => strtolower($member['first_name']).'.'.strtolower($member['last_name']).'@platinumsecurity.local',
                'bank_name' => 'Stanbic Bank',
                'bank_account' => '41'.str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT),
                'date_employed' => now()->subMonths($index + 1)->startOfMonth()->toDateString(),
                'employment_status' => EmploymentStatus::Active->value,
            ]);
        }
    }

}
