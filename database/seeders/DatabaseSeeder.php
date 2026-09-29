<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $mode = (string) env('PSG_SEED_MODE', 'story');

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
