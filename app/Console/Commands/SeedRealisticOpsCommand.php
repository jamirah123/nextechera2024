<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Database\Seeders\RealisticOpsSeeder;
use Illuminate\Console\Command;

class SeedRealisticOpsCommand extends Command
{
    protected $signature = 'psg:seed-realistic
                            {--from=2026-01-01 : First operational date to seed}
                            {--to= : Last operational date (defaults to today)}
                            {--fresh : migrate:fresh before seeding (destroys all data)}';

    protected $description = 'Seed realistic ops history through domain services (deployments, duties, HR, invoices, payroll)';

    public function handle(): int
    {
        $from = (string) $this->option('from');
        $to = $this->option('to') ? (string) $this->option('to') : now()->toDateString();

        try {
            Carbon::parse($from);
            Carbon::parse($to);
        } catch (\Throwable) {
            $this->error('Invalid --from / --to date.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            if ($this->input->isInteractive()
                && ! $this->confirm('This will wipe the database (migrate:fresh) then seed. Continue?', false)
            ) {
                $this->warn('Cancelled.');

                return self::SUCCESS;
            }

            $this->call('migrate:fresh', ['--force' => true]);
        }

        $seeder = new RealisticOpsSeeder;
        $seeder->setCommand($this);
        $seeder->run($from, $to);

        return self::SUCCESS;
    }
}
