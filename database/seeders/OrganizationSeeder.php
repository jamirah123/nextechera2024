<?php

namespace Database\Seeders;

use App\Enums\ContractStatus;
use App\Enums\RegionStatus;
use App\Enums\SiteStatus;
use App\Enums\SupervisorStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Region;
use App\Models\Site;
use App\Models\Supervisor;
use App\Models\User;
use App\Services\OrganizationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->where('email', 'admin@platinumsecurity.local')->first();
        if ($actor) {
            auth()->login($actor);
        }

        $organization = app(OrganizationService::class);
        $supervisorsPerRegion = max(1, (int) config('psg.seed.supervisors_per_region', 4));
        $sitesPerRegion = max(1, (int) config('psg.seed.sites_per_region', 16));
        $clientTarget = max(6, (int) config('psg.seed.clients', 40));

        $regionDefs = [
            ['name' => 'Central', 'code' => 'CEN', 'manager_name' => 'Amina Juma'],
            ['name' => 'Eastern', 'code' => 'EAS', 'manager_name' => 'Joseph Mwangi'],
            ['name' => 'Northern', 'code' => 'NOR', 'manager_name' => 'Grace Okello'],
            ['name' => 'Western', 'code' => 'WES', 'manager_name' => 'Daniel Kiprotich'],
            ['name' => 'Southern', 'code' => 'SOU', 'manager_name' => 'Sarah Nalwoga'],
            ['name' => 'Kampala Metro', 'code' => 'KLA', 'manager_name' => 'Peter Ssemakula'],
        ];

        $regions = collect($regionDefs)->map(function (array $data) {
            return Region::query()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'manager_name' => $data['manager_name'],
                    'manager_phone' => '+256700'.fake()->numerify('######'),
                    'description' => $data['name'].' operational region for Platinum Security Group.',
                    'status' => RegionStatus::Active,
                ],
            );
        })->values();

        $supervisors = collect();
        $supervisorIndex = 0;
        foreach ($regions as $region) {
            for ($slot = 1; $slot <= $supervisorsPerRegion; $slot++) {
                $supervisorIndex++;
                $code = 'SUP'.str_pad((string) $supervisorIndex, 4, '0', STR_PAD_LEFT);

                $supervisor = Supervisor::query()->updateOrCreate(
                    ['supervisor_code' => $code],
                    [
                        'name' => fake()->name(),
                        'phone' => '+25671'.fake()->numerify('#######'),
                        'email' => 'field.supervisor'.$supervisorIndex.'@platinumsecurity.local',
                        'region_id' => $region->id,
                        'status' => SupervisorStatus::Active,
                        'assignment_date' => now()->subMonths(fake()->numberBetween(2, 18))->toDateString(),
                        'notes' => 'Field supervisor '.$slot.' for '.$region->name.'.',
                    ],
                );

                if ($supervisor->assignmentHistories()->doesntExist()) {
                    $organization->recordSupervisorAssignment(
                        $supervisor,
                        null,
                        (int) $region->id,
                        'initial_assignment',
                        'Seeded assignment',
                        'Initial regional assignment from volume seeder.',
                    );
                }

                $supervisors->push($supervisor);
            }
        }

        $clients = collect();
        for ($i = 1; $i <= $clientTarget; $i++) {
            $name = match ($i) {
                1 => 'ABC Logistics Ltd',
                2 => 'Summit Industries',
                3 => 'Harbor Retail Group',
                4 => 'Nile Agro Processors',
                5 => 'Pearl Bank HQ',
                6 => 'Victoria Malls',
                default => fake()->unique()->company().' '.$i,
            };
            $slug = 'cli'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);

            $clients->push(Client::query()->updateOrCreate(
                ['name' => $name],
                [
                    'contact_person' => fake()->name(),
                    'phone' => '+25675'.fake()->numerify('#######'),
                    'email' => $slug.'@client.local',
                    'address' => fake()->streetAddress().', Uganda',
                    'contract_start_date' => now()->subMonths(fake()->numberBetween(6, 24))->toDateString(),
                    'contract_end_date' => now()->addMonths(fake()->numberBetween(6, 24))->toDateString(),
                    'contract_status' => ContractStatus::Active,
                    'notes' => 'Active security services contract.',
                ],
            ));
        }

        $siteTypes = ['Gate', 'Warehouse', 'Depot', 'Mall', 'HQ', 'Yard', 'Plant', 'Campus', 'Tower', 'Compound', 'Terminal', 'Clinic', 'Estate', 'Factory', 'Park', 'Hub'];
        $siteCounter = 0;

        foreach ($regions as $region) {
            $regionSupervisors = $supervisors->where('region_id', $region->id)->values();

            for ($s = 0; $s < $sitesPerRegion; $s++) {
                $siteCounter++;
                $client = $clients[($siteCounter - 1) % $clients->count()];
                $supervisor = $regionSupervisors[$s % $regionSupervisors->count()];
                $day = fake()->numberBetween(8, 18);
                $night = fake()->numberBetween(6, 14);
                $code = $region->code.'-S'.str_pad((string) ($s + 1), 2, '0', STR_PAD_LEFT);

                $site = Site::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $client->name.' '.$siteTypes[$s % count($siteTypes)].' '.$region->code,
                        'client_id' => $client->id,
                        'region_id' => $region->id,
                        'supervisor_id' => $supervisor->id,
                        'physical_location' => fake()->streetAddress().', '.$region->name,
                        'site_contact_person' => fake()->name(),
                        'site_contact_phone' => '+25676'.fake()->numerify('#######'),
                        'contract_start_date' => now()->subMonths(fake()->numberBetween(3, 18))->toDateString(),
                        'contract_end_date' => now()->addMonths(fake()->numberBetween(6, 24))->toDateString(),
                        'required_guards' => $day + $night,
                        'required_day_guards' => $day,
                        'required_night_guards' => $night,
                        'number_of_posts' => fake()->numberBetween(3, 10),
                        'status' => SiteStatus::Active,
                        'notes' => 'Seeded operational site for volume testing.',
                    ],
                );

                if ($site->manpowerRequirements()->where('is_current', true)->doesntExist()) {
                    $organization->syncSiteManpower($site, 'Seeded manpower requirement');
                }
            }
        }

        foreach ($supervisors->groupBy('region_id') as $regionId => $regionSupervisors) {
            $primary = $regionSupervisors->first();
            $region = $regions->firstWhere('id', $regionId);
            if (! $primary || ! $region) {
                continue;
            }

            $slug = strtolower($region->code);
            User::query()->updateOrCreate(
                ['email' => "supervisor.{$slug}@platinumsecurity.local"],
                [
                    'name' => 'Region Supervisor ('.$region->name.')',
                    'role' => UserRole::RegionSupervisor,
                    'supervisor_id' => $primary->id,
                    'phone' => '+256700'.str_pad((string) $regionId, 6, '0', STR_PAD_LEFT),
                    'password' => Hash::make('Password@123'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }

        $central = $supervisors->first();
        if ($central) {
            User::query()->updateOrCreate(
                ['email' => 'supervisor@platinumsecurity.local'],
                [
                    'name' => 'Region Supervisor (Central)',
                    'role' => UserRole::RegionSupervisor,
                    'supervisor_id' => $central->id,
                    'phone' => '+256700000006',
                    'password' => Hash::make('Password@123'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }

        $this->command?->info(sprintf(
            'Organization: %d regions, %d supervisors, %d clients, %d sites',
            $regions->count(),
            $supervisors->count(),
            $clients->count(),
            $siteCounter,
        ));

        if ($actor) {
            auth()->logout();
        }
    }
}
