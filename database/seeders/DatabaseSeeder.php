<?php

namespace Database\Seeders;

use App\Models\Absence;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\Deployment;
use App\Models\Desertion;
use App\Models\Guard;
use App\Models\Leave;
use App\Models\Region;
use App\Models\Shift;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            OrganizationSeeder::class,
            GuardSeeder::class,
        ]);

        if (config('psg.seed.deployments')) {
            $this->call(DeploymentSeeder::class);
        } else {
            $this->command?->info('Skipping deployment seed (PSG_SEED_DEPLOYMENTS=false).');
        }

        if (config('psg.seed.shifts')) {
            $this->call(ShiftSeeder::class);
        } else {
            $this->command?->info('Skipping shift seed (PSG_SEED_SHIFTS=false).');
        }

        $this->call([
            HrSeeder::class,
            FinanceSeeder::class,
        ]);

        $this->command?->newLine();
        $this->command?->info('=== Volume seed summary ===');
        foreach ([
            'Users' => User::query()->count(),
            'Regions' => Region::query()->count(),
            'Supervisors' => Supervisor::query()->count(),
            'Clients' => Client::query()->count(),
            'Sites' => Site::query()->count(),
            'Guards' => Guard::query()->count(),
            'Deployments' => Deployment::query()->count(),
            'Shifts' => Shift::query()->count(),
            'Leaves' => Leave::query()->count(),
            'Absences' => Absence::query()->count(),
            'Desertions' => Desertion::query()->count(),
            'Staff' => Staff::query()->count(),
            'Billing profiles' => BillingProfile::query()->count(),
        ] as $label => $count) {
            $this->command?->info(sprintf('%s: %s', $label, number_format($count)));
        }

        $total = User::query()->count()
            + Region::query()->count()
            + Supervisor::query()->count()
            + Client::query()->count()
            + Site::query()->count()
            + Guard::query()->count()
            + Deployment::query()->count()
            + Shift::query()->count()
            + Leave::query()->count()
            + Absence::query()->count()
            + Desertion::query()->count()
            + BillingProfile::query()->count();

        $this->command?->info('Approximate operational total: '.number_format($total));
    }
}
