<?php

namespace App\Console\Commands;

use App\Enums\BackupType;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'psg:backup-database
                            {--type=scheduled_daily : Backup type: manual, scheduled_daily, scheduled_weekly, safety_pre_restore}
                            {--keep= : Number of backups to retain (overrides settings)}
                            {--notes= : Optional notes stored with the backup catalog row}';

    protected $description = 'Create a catalogued, checksummed database backup for Platinum Security';

    public function handle(DatabaseBackupService $backups): int
    {
        $type = BackupType::tryFrom((string) $this->option('type')) ?? BackupType::ScheduledDaily;

        if ($this->option('keep') !== null) {
            config(['psg.backup.keep_days' => max(1, (int) $this->option('keep'))]);
        }

        try {
            $backup = $backups->create($type, null, [
                'notes' => $this->option('notes') ?: $type->label().' backup via scheduler/console.',
            ]);

            $this->info('Backup created: '.$backup->reference.' → '.$backup->absolutePath());
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
