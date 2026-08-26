<?php

namespace Database\Seeders;

use App\Enums\ContractStatus;
use App\Enums\RegionStatus;
use App\Enums\SiteStatus;
use App\Enums\SupervisorStatus;
use App\Models\Client;
use App\Models\Region;
use App\Models\Site;
use App\Models\Supervisor;
use App\Models\User;
use App\Services\OrganizationService;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->where('email', 'admin@platinumsecurity.local')->first();
        if ($actor) {
            auth()->login($actor);
        }

        $organization = app(OrganizationService::class);

        $regions = collect([
            ['name' => 'Central', 'code' => 'CEN', 'manager_name' => 'Amina Juma'],
            ['name' => 'Eastern', 'code' => 'EAS', 'manager_name' => 'Joseph Mwangi'],
            ['name' => 'Northern', 'code' => 'NOR', 'manager_name' => 'Grace Okello'],
            ['name' => 'Western', 'code' => 'WES', 'manager_name' => 'Daniel Kiprotich'],
        ])->map(function (array $data) {
            return Region::query()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'manager_name' => $data['manager_name'],
                    'manager_phone' => '+255700'.fake()->numerify('######'),
                    'description' => $data['name'].' operational region for Platinum Security Group.',
                    'status' => RegionStatus::Active,
                ],
            );
        });

        $supervisors = collect();
        foreach ($regions as $index => $region) {
            $supervisor = Supervisor::query()->updateOrCreate(
                ['supervisor_code' => 'SUP'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'name' => fake()->name(),
                    'phone' => '+25571'.fake()->numerify('#######'),
                    'email' => 'supervisor'.($index + 1).'@platinumsecurity.local',
                    'region_id' => $region->id,
                    'status' => SupervisorStatus::Active,
                    'assignment_date' => now()->subMonths(6)->toDateString(),
                    'notes' => 'Primary supervisor for '.$region->name.' region.',
                ],
            );

            if ($supervisor->assignmentHistories()->doesntExist()) {
                $organization->recordSupervisorAssignment(
                    $supervisor,
                    null,
                    (int) $region->id,
                    'initial_assignment',
                    'Seeded assignment',
                    'Initial regional assignment from seeder.',
                );
            }

            $supervisors->push($supervisor);
        }

        $clients = collect([
            ['name' => 'ABC Logistics Ltd', 'code' => 'ABC'],
            ['name' => 'Summit Industries', 'code' => 'SUM'],
            ['name' => 'Harbor Retail Group', 'code' => 'HRG'],
        ])->map(function (array $data) {
            return Client::query()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'contact_person' => fake()->name(),
                    'phone' => '+25575'.fake()->numerify('#######'),
                    'email' => strtolower($data['code']).'@client.local',
                    'address' => fake()->address(),
                    'contract_start_date' => now()->subYear()->toDateString(),
                    'contract_end_date' => now()->addYear()->toDateString(),
                    'contract_status' => ContractStatus::Active,
                    'notes' => 'Active security services contract.',
                ],
            );
        });

        $siteDefs = [
            ['name' => 'ABC Warehouse', 'code' => 'ABC-WH', 'client' => 0, 'region' => 0, 'day' => 6, 'night' => 6, 'posts' => 4],
            ['name' => 'ABC Distribution Yard', 'code' => 'ABC-DY', 'client' => 0, 'region' => 0, 'day' => 4, 'night' => 4, 'posts' => 3],
            ['name' => 'Summit Factory Gate', 'code' => 'SUM-FG', 'client' => 1, 'region' => 1, 'day' => 5, 'night' => 5, 'posts' => 2],
            ['name' => 'Harbor Mall East', 'code' => 'HRG-ME', 'client' => 2, 'region' => 2, 'day' => 8, 'night' => 6, 'posts' => 5],
            ['name' => 'Harbor Depot West', 'code' => 'HRG-DW', 'client' => 2, 'region' => 3, 'day' => 3, 'night' => 3, 'posts' => 2],
        ];

        foreach ($siteDefs as $def) {
            $region = $regions[$def['region']];
            $supervisor = $supervisors[$def['region']];
            $client = $clients[$def['client']];

            $site = Site::query()->updateOrCreate(
                ['code' => $def['code']],
                [
                    'name' => $def['name'],
                    'client_id' => $client->id,
                    'region_id' => $region->id,
                    'supervisor_id' => $supervisor->id,
                    'physical_location' => fake()->streetAddress().', '.$region->name,
                    'site_contact_person' => fake()->name(),
                    'site_contact_phone' => '+25576'.fake()->numerify('#######'),
                    'contract_start_date' => now()->subMonths(8)->toDateString(),
                    'contract_end_date' => now()->addMonths(16)->toDateString(),
                    'required_guards' => $def['day'] + $def['night'],
                    'required_day_guards' => $def['day'],
                    'required_night_guards' => $def['night'],
                    'number_of_posts' => $def['posts'],
                    'status' => SiteStatus::Active,
                    'notes' => 'Seeded operational site.',
                ],
            );

            if ($site->manpowerRequirements()->where('is_current', true)->doesntExist()) {
                $organization->syncSiteManpower($site, 'Seeded manpower requirement');
            }
        }

        if ($actor) {
            auth()->logout();
        }
    }
}
