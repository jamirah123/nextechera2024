<?php

namespace App\Console\Commands;

use App\Models\DatabaseBackup;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class VerifyBackupCommand extends Command
{
    protected $signature = 'psg:verify-backup {reference? : Backup reference (e.g. BKP-20260909-001)}';

    protected $description = 'Verify SHA-256 integrity of a database backup';

    public function handle(DatabaseBackupService $backups): int
    {
        $reference = $this->argument('reference');

        $backup = $reference
            ? DatabaseBackup::query()->where('reference', $reference)->first()
            : DatabaseBackup::query()->whereIn('status', ['completed', 'verified'])->latest('id')->first();

        if (! $backup) {
            $this->error('Backup not found.');

            return self::FAILURE;
        }

        try {
            $verified = $backups->verify($backup);
            $this->info('Verified '.$verified->reference.' ('.$verified->checksum_sha256.')');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
