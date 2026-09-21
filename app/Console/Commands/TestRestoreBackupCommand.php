<?php

namespace App\Console\Commands;

use App\Models\DatabaseBackup;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class TestRestoreBackupCommand extends Command
{
    protected $signature = 'psg:test-restore-backup {reference? : Backup reference (defaults to latest successful)}';

    protected $description = 'Run a non-destructive restore drill (checksum + isolated readability check)';

    public function handle(DatabaseBackupService $backups): int
    {
        $reference = $this->argument('reference');

        $backup = $reference
            ? DatabaseBackup::query()->where('reference', $reference)->first()
            : $backups->latestSuccessful();

        if (! $backup) {
            $this->error('Backup not found.');

            return self::FAILURE;
        }

        try {
            $result = $backups->testRestore($backup);
            $this->info('Restore drill passed for '.$backup->reference);
            $this->line($result['notes']);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
