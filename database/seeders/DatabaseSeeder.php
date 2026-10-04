<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $mode = strtolower(trim((string) config('psg.seed.mode', 'off')));
        if ($mode === '') {
            $mode = 'off';
        }

        if ($mode === 'off') {
            $this->command?->warn('PSG_SEED_MODE=off. No guards, payroll, invoices, or deployments were written.');

            return;
        }

        if (app()->environment('production') && ! filter_var(config('psg.seed.allow_production', false), FILTER_VALIDATE_BOOL)) {
            $this->command?->warn('Production refused the seeder. Keep PSG_SEED_MODE=off on the live server.');

            return;
        }

        if ($mode === 'legacy') {
            $this->call([
                SmallCompanySeeder::class,
                ExpandOrgSeeder::class,
                ExpandHrModulesSeeder::class,
                RealisticOpsSeeder::class,
            ]);

            return;
        }

        $this->call(WorkflowOperationsSeeder::class);
    }
}
