<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Runs only the current company dataset.
 *
 * Older company seeders are not on this path. A database replacement must
 * migrate:fresh first so this seed is the only company data left behind.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(LargeCompanySeeder::class);
    }
}
