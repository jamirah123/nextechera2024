<?php

namespace App\Console\Commands;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\BackupStatus;
use App\Models\AuditLog;
use App\Models\DatabaseBackup;
use App\Services\AuditService;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

class BackupHealthCommand extends Command
{
    protected $signature = 'psg:backup-health {--alert : Emit a proactive audit alert when backups are stale or failing}';

    protected $description = 'Report backup freshness, recent failures, and optionally raise a missed-backup alert';

    public function handle(DatabaseBackupService $backups, AuditService $audit): int
    {
        $staleHours = max(6, (int) config('psg.backup.stale_hours', 36));
        $latest = $backups->latestSuccessful();
        $hoursSince = $backups->hoursSinceLastSuccessfulBackup();
        $recentFailures = DatabaseBackup::query()
            ->where('status', BackupStatus::Failed->value)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if (! $latest) {
            $this->warn('No successful backups found in the catalog.');
        } else {
            $this->info(sprintf(
                'Latest successful backup: %s (%s ago) — %s%s',
                $latest->reference,
                $latest->completed_at?->diffForHumans(syntax: true) ?? 'unknown',
                $latest->formattedSize(),
                $latest->includes_files ? ' + files' : '',
            ));
        }

        if ($recentFailures > 0) {
            $this->warn("Failed backup attempts in the last 24h: {$recentFailures}");
        } else {
            $this->info('No failed backups in the last 24 hours.');
        }

        $isStale = $hoursSince === null || $hoursSince >= $staleHours;

        if ($isStale) {
            $this->error(sprintf(
                'Backup is STALE (threshold %dh%s).',
                $staleHours,
                $hoursSince === null ? ', none found' : ', age '.round($hoursSince, 1).'h'
            ));
        } else {
            $this->info(sprintf('Backup freshness OK (%.1fh old, threshold %dh).', $hoursSince, $staleHours));
        }

        if ($this->option('alert') && $isStale) {
            $dedupKey = 'backup-missed-'.now()->format('Y-m-d-H');

            $already = AuditLog::query()
                ->where('action', 'backup.missed')
                ->where('created_at', '>=', now()->subHours(max(6, (int) ($staleHours / 2))))
                ->where('context->dedup_key', $dedupKey)
                ->exists();

            if (! $already) {
                $audit->log(
                    action: 'backup.missed',
                    summary: $latest
                        ? 'No successful backup within the last '.$staleHours.' hours. Latest: '.$latest->reference.'.'
                        : 'No successful application backup has been recorded yet.',
                    category: AuditCategory::System,
                    severity: AuditSeverity::Critical,
                    subject: $latest,
                    context: [
                        'dedup_key' => $dedupKey,
                        'stale_hours' => $staleHours,
                        'hours_since' => $hoursSince,
                        'latest_reference' => $latest?->reference,
                        'recent_failures' => $recentFailures,
                    ],
                    actor: null,
                );
                $this->warn('Missed-backup alert recorded.');
            }
        }

        return $isStale ? self::FAILURE : self::SUCCESS;
    }
}
