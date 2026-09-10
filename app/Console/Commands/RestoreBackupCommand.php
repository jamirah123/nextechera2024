<?php

namespace App\Console\Commands;

use App\Models\DatabaseBackup;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class RestoreBackupCommand extends Command
{
    protected $signature = 'psg:restore-backup
                            {reference : Backup reference to restore}
                            {--force : Skip interactive confirmation}';

    protected $description = 'Restore the live database from a catalogued backup (creates a safety backup first)';

    public function handle(DatabaseBackupService $backups): int
    {
        $backup = DatabaseBackup::query()->where('reference', $this->argument('reference'))->first();

        if (! $backup) {
            $this->error('Backup not found.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(
            'Restore '.$backup->reference.' into the LIVE database? A safety backup will be created first.'
        )) {
            $this->warn('Restore cancelled.');

            return self::SUCCESS;
        }

        try {
            $result = $backups->restore($backup, null, 'Restored via artisan psg:restore-backup');
            $this->info('Restored from '.$result['restored']->reference);
            $this->info('Safety backup: '.$result['safety']->reference);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
