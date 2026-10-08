<?php

namespace App\Console\Commands;

use App\Enums\BackupType;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Throwable;

class ReplaceSeededDatabaseCommand extends Command
{
    protected $signature = 'psg:replace-seeded-database
                            {--allow-missing-backup : Continue after a backup failure. Production still requires the exact confirmation phrase.}';

    protected $description = 'Back up, then replace the application tables with the current LargeCompanySeeder dataset';

    public function handle(DatabaseBackupService $backups): int
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");
        $branch = $this->currentBranch();
        $requiredBranch = (string) config('psg.seed.replace_branch', 'seed/current-300-guards');

        $this->newLine();
        $this->warn('WARNING: This will permanently replace the current database.');
        $this->line('Application: Platinum Security Group');
        $this->line('Environment: '.app()->environment());
        $this->line('Database: '.$database);
        $this->newLine();
        $this->line('APP_ENV='.config('app.env'));
        $this->line('APP_URL='.(string) config('app.url'));
        $this->line('DB_CONNECTION='.$connection);
        $this->line('DB_HOST='.$this->connectionValue($connection, 'host'));
        $this->line('DB_DATABASE='.$database);
        $this->line('DB_USERNAME='.$this->connectionValue($connection, 'username'));
        $this->line('Git branch: '.($branch ?? '(unknown)'));
        $this->newLine();

        if ($database === '') {
            $this->error('The database name is empty. Replacement was not started.');

            return self::FAILURE;
        }

        if (app()->environment('production') && ! filter_var(config('psg.seed.allow_production', false), FILTER_VALIDATE_BOOL)) {
            $this->error('Production replacement is refused. Set PSG_SEED_ALLOW_PRODUCTION=true only for this intentional reload, then set it back to false.');

            return self::FAILURE;
        }

        if ($branch !== $requiredBranch) {
            $this->error('Replacement runs only from the '.$requiredBranch.' branch. The checked-out branch is '.($branch ?? 'not a Git branch').'.');

            return self::FAILURE;
        }

        $typedDatabase = trim((string) $this->ask('Type the database name shown above to confirm it is the intended Platinum Security database'));
        if ($typedDatabase !== $database) {
            $this->error('The database name did not match. Replacement was not started.');

            return self::FAILURE;
        }

        $phrase = app()->environment('production') ? 'REPLACE-PRODUCTION-DATABASE' : 'REPLACE-DATABASE';
        $typedPhrase = trim((string) $this->ask('Type '.$phrase.' to continue'));
        if ($typedPhrase !== $phrase) {
            $this->error('Confirmation was not the exact required phrase. Replacement was not started.');

            return self::FAILURE;
        }

        if (! $this->backup($backups)) {
            return self::FAILURE;
        }

        $this->warn('Resetting application tables, then running the current seed from '.$branch.'.');
        config([
            'psg.seed.mode' => 'load',
            'psg.seed.resume' => false,
        ]);
        set_time_limit(0);

        $exit = Artisan::call('migrate:fresh', [
            '--force' => true,
            '--seed' => true,
        ]);
        $this->output->write(Artisan::output());
        if ($exit !== self::SUCCESS) {
            $this->error('Migrations or the current seeder failed. The database may be incomplete.');

            return self::FAILURE;
        }

        return $this->validateDataset() ? self::SUCCESS : self::FAILURE;
    }

    private function backup(DatabaseBackupService $backups): bool
    {
        try {
            $backup = $backups->create(BackupType::SafetyPreRestore, null, [
                'notes' => 'Safety backup before psg:replace-seeded-database.',
            ]);
            $verified = $backups->verify($backup->fresh());
        } catch (Throwable $exception) {
            $this->error('Backup was not created or could not be verified: '.$exception->getMessage());
            if (! $this->option('allow-missing-backup')) {
                $this->error('Replacement stopped because there is no verified backup.');

                return false;
            }

            $this->warn('Continuing without a verified backup because --allow-missing-backup was passed.');

            return true;
        }

        $this->info('Verified backup: '.$verified->reference.' → '.$verified->absolutePath());

        return true;
    }

    private function validateDataset(): bool
    {
        $checks = [
            'Guards' => [(int) DB::table('guards')->count(), 250, 350],
            'Regions' => [(int) DB::table('regions')->count(), 6, 6],
            'Supervisors' => [(int) DB::table('supervisors')->count(), 10, 10],
            'Staff' => [(int) DB::table('staff')->count(), 20, null],
            'Clients' => [(int) DB::table('clients')->count(), 1, null],
            'Sites' => [(int) DB::table('sites')->count(), 1, null],
            'Manpower requirements' => [(int) DB::table('site_manpower_requirements')->count(), 1, null],
            'Shifts' => [(int) DB::table('shifts')->count(), 1, null],
            'Deployments' => [(int) DB::table('deployments')->count(), 1, null],
            'Attendance' => [(int) DB::table('attendances')->count(), 1, null],
            'Leave' => [(int) DB::table('leaves')->count(), 1, null],
            'Overtime shifts' => [(int) DB::table('shifts')->where('shift_type', 'overtime')->count(), 1, null],
            'Salary revisions' => [(int) DB::table('guard_salary_revisions')->count(), 1, null],
            'Payroll runs' => [(int) DB::table('payroll_runs')->count(), 1, null],
            'Payslips' => [(int) DB::table('payroll_payslips')->count(), 1, null],
            'Billing profiles' => [(int) DB::table('billing_profiles')->count(), 1, null],
            'Invoices' => [(int) DB::table('invoices')->count(), 1, null],
            'Payments' => [(int) DB::table('payments')->count(), 1, null],
            'Incidents' => [(int) DB::table('incidents')->count(), 1, null],
        ];

        $failed = false;
        $this->newLine();
        $this->info('Current dataset');
        foreach ($checks as $label => [$count, $minimum, $maximum]) {
            $ok = $count >= $minimum && ($maximum === null || $count <= $maximum);
            $failed = $failed || ! $ok;
            $this->line(sprintf('  %-24s %s %s', $label, number_format($count), $ok ? 'ok' : 'FAILED'));
        }

        $duplicateIds = (int) DB::table('guards')
            ->select('employment_id')
            ->whereNotNull('employment_id')
            ->groupBy('employment_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
        $beforeHire = (int) DB::table('deployments')
            ->join('guards', 'guards.id', '=', 'deployments.guard_id')
            ->whereColumn('deployments.start_date', '<', 'guards.date_employed')
            ->count();
        $openPosts = (int) DB::table('deployments')->where('is_current', true)->count();
        $orphanSites = (int) DB::table('sites')->whereNull('client_id')->count();

        foreach ([
            'Duplicate employment IDs' => $duplicateIds,
            'Deployments before hire' => $beforeHire,
            'Open current postings' => $openPosts,
            'Sites without a client' => $orphanSites,
        ] as $label => $count) {
            $ok = $count === 0;
            $failed = $failed || ! $ok;
            $this->line(sprintf('  %-24s %s %s', $label, number_format($count), $ok ? 'ok' : 'FAILED'));
        }

        if ($failed) {
            $this->error('The current dataset failed validation.');

            return false;
        }

        $this->info('The database contains only the current seeded dataset.');
        $this->line('Set PSG_SEED_ALLOW_PRODUCTION=false again if this server is production.');

        return true;
    }

    private function currentBranch(): ?string
    {
        $process = new Process(['git', 'branch', '--show-current'], base_path());
        $process->setTimeout(15);
        $process->run();
        if (! $process->isSuccessful()) {
            return null;
        }

        $branch = trim($process->getOutput());

        return $branch !== '' ? $branch : null;
    }

    private function connectionValue(string $connection, string $key): string
    {
        $value = config("database.connections.{$connection}.{$key}");

        return filled($value) ? (string) $value : '(not set)';
    }
}
