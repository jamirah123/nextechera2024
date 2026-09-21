<?php

namespace App\Console\Commands;

use App\Enums\BackupType;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'psg:backup-database
                            {--type=scheduled_daily : Backup type: manual, scheduled_daily, scheduled_weekly, scheduled_monthly, safety_pre_restore}
                            {--keep= : Number of daily-bucket backups to retain (overrides settings)}
                            {--notes= : Optional notes stored with the backup catalog row}';

    protected $description = 'Create a catalogued, checksummed application backup (database + optional private files)';

    public function handle(DatabaseBackupService $backups): int
    {
        $type = BackupType::tryFrom((string) $this->option('type')) ?? BackupType::ScheduledDaily;

        if ($this->option('keep') !== null) {
            $keep = max(1, (int) $this->option('keep'));
            config([
                'psg.backup.keep_days' => $keep,
                'psg.backup.keep_daily' => $keep,
            ]);
        }

        try {
            $backup = $backups->create($type, null, [
                'notes' => $this->option('notes') ?: $type->label().' backup via scheduler/console.',
            ]);

            $this->info('Backup created: '.$backup->reference.' → '.$backup->absolutePath());
            if ($backup->includes_files) {
                $this->info('Files archive: '.$backup->files_filename.' ('.$backup->formattedFilesSize().')');
            }
            if ($backup->offsite_disk) {
                $this->info('Off-site copy: '.$backup->offsite_disk.':'.$backup->offsite_path);
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
