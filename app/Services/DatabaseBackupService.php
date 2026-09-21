<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\DatabaseBackup;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;
use Throwable;

class DatabaseBackupService
{
    public function __construct(private AuditService $audit) {}

    /**
     * @param  array{notes?: string|null, skip_prune?: bool, include_files?: bool}  $options
     */
    public function create(BackupType $type, ?User $actor = null, array $options = []): DatabaseBackup
    {
        $disk = (string) config('psg.backup.disk', 'backups');
        $this->ensureBackupDisk($disk);

        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");

        if (! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
            throw new RuntimeException("Unsupported database driver [{$driver}] for automated backup.");
        }

        $includeFiles = array_key_exists('include_files', $options)
            ? (bool) $options['include_files']
            : (bool) config('psg.backup.include_files', true);

        $backup = DatabaseBackup::query()->create([
            'reference' => $this->nextReference(),
            'type' => $type,
            'status' => BackupStatus::Pending,
            'disk' => $disk,
            'relative_path' => '',
            'filename' => '',
            'driver' => $driver === 'mariadb' ? 'mysql' : $driver,
            'includes_files' => false,
            'size_bytes' => 0,
            'files_size_bytes' => 0,
            'started_at' => now(),
            'created_by' => $actor?->id ?? auth()->id(),
            'notes' => $options['notes'] ?? null,
        ]);

        try {
            $stamp = now()->format('Ymd-His').'-'.$backup->id;
            $filename = $driver === 'sqlite'
                ? "psg-sqlite-{$stamp}.sqlite"
                : "psg-mysql-{$stamp}.sql";
            $relativePath = $filename;

            // Persist path before the dump so SQLite file copies include catalog metadata.
            $backup->update([
                'relative_path' => $relativePath,
                'filename' => $filename,
            ]);

            $absolute = Storage::disk($disk)->path($relativePath);

            if ($driver === 'sqlite') {
                $this->backupSqlite($absolute);
            } else {
                $this->backupMysql($absolute, $connection);
            }

            $size = File::size($absolute);
            $checksum = hash_file('sha256', $absolute);

            $filesMeta = [
                'includes_files' => false,
                'files_relative_path' => null,
                'files_filename' => null,
                'files_size_bytes' => 0,
                'files_checksum_sha256' => null,
            ];

            if ($includeFiles && $type !== BackupType::SafetyPreRestore) {
                $filesMeta = array_merge($filesMeta, $this->backupPrivateFiles($disk, $stamp));
            }

            $backup->update([
                'size_bytes' => $size,
                'checksum_sha256' => $checksum,
                'status' => BackupStatus::Completed,
                'completed_at' => now(),
                'error_message' => null,
                ...$filesMeta,
            ]);

            $this->syncOffsite($backup->fresh());

            if (! ($options['skip_prune'] ?? false)) {
                $this->prune();
            }

            $fresh = $backup->fresh(['creator']);

            $this->audit->log(
                action: 'backup.completed',
                summary: 'Application backup '.$fresh->reference.' completed ('.$fresh->type->label().', '.$fresh->formattedSize()
                    .($fresh->includes_files ? ', includes files' : '').').',
                category: AuditCategory::System,
                severity: AuditSeverity::Notice,
                subject: $fresh,
                context: [
                    'reference' => $fresh->reference,
                    'type' => $fresh->type->value,
                    'filename' => $fresh->filename,
                    'size_bytes' => $fresh->size_bytes,
                    'checksum_sha256' => $fresh->checksum_sha256,
                    'includes_files' => $fresh->includes_files,
                    'files_filename' => $fresh->files_filename,
                    'files_size_bytes' => $fresh->files_size_bytes,
                    'driver' => $fresh->driver,
                    'offsite_disk' => $fresh->offsite_disk,
                ],
            );

            return $fresh;
        } catch (Throwable $e) {
            $backup->update([
                'status' => BackupStatus::Failed,
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            $this->audit->log(
                action: 'backup.failed',
                summary: 'Database backup '.$backup->reference.' failed: '.$e->getMessage(),
                category: AuditCategory::System,
                severity: AuditSeverity::Critical,
                subject: $backup->fresh(),
                context: [
                    'reference' => $backup->reference,
                    'type' => $type->value,
                    'error' => $e->getMessage(),
                ],
            );

            throw $e;
        }
    }

    public function verify(DatabaseBackup $backup, ?User $actor = null): DatabaseBackup
    {
        if (! $backup->fileExists()) {
            throw new InvalidArgumentException('Backup file is missing from storage.');
        }

        if (! filled($backup->checksum_sha256)) {
            throw new InvalidArgumentException('This backup has no stored checksum to verify against.');
        }

        $current = hash_file('sha256', $backup->absolutePath());

        if (! hash_equals((string) $backup->checksum_sha256, (string) $current)) {
            $backup->update([
                'status' => BackupStatus::Failed,
                'error_message' => 'Integrity check failed: checksum mismatch. File may be corrupted or altered.',
            ]);

            $this->audit->log(
                action: 'backup.verify_failed',
                summary: 'Backup '.$backup->reference.' failed integrity verification.',
                category: AuditCategory::System,
                severity: AuditSeverity::Critical,
                subject: $backup->fresh(),
                context: [
                    'expected' => $backup->checksum_sha256,
                    'actual' => $current,
                ],
            );

            throw new InvalidArgumentException('Integrity check failed. The backup file does not match its stored checksum.');
        }

        if ($backup->includes_files) {
            if (! $backup->filesArchiveExists()) {
                throw new InvalidArgumentException('Files archive is missing from storage for this backup.');
            }

            if (filled($backup->files_checksum_sha256)) {
                $filesChecksum = hash_file('sha256', Storage::disk($backup->disk)->path($backup->files_relative_path));
                if (! hash_equals((string) $backup->files_checksum_sha256, (string) $filesChecksum)) {
                    $backup->update([
                        'status' => BackupStatus::Failed,
                        'error_message' => 'Files archive integrity check failed: checksum mismatch.',
                    ]);

                    throw new InvalidArgumentException('Files archive integrity check failed.');
                }
            }
        }

        $backup->update([
            'status' => BackupStatus::Verified,
            'verified_at' => now(),
            'verified_by' => $actor?->id ?? auth()->id(),
            'size_bytes' => File::size($backup->absolutePath()),
            'error_message' => null,
        ]);

        $fresh = $backup->fresh(['verifier']);

        $this->audit->log(
            action: 'backup.verified',
            summary: 'Backup '.$fresh->reference.' passed integrity verification.',
            category: AuditCategory::System,
            severity: AuditSeverity::Notice,
            subject: $fresh,
            context: [
                'checksum_sha256' => $fresh->checksum_sha256,
                'files_checksum_sha256' => $fresh->files_checksum_sha256,
                'size_bytes' => $fresh->size_bytes,
                'files_size_bytes' => $fresh->files_size_bytes,
            ],
        );

        return $fresh;
    }

    public function download(DatabaseBackup $backup): StreamedResponse
    {
        if (! $backup->status->isDownloadable() || ! $backup->fileExists()) {
            throw new InvalidArgumentException('This backup is not available for download.');
        }

        $this->audit->log(
            action: 'backup.downloaded',
            summary: 'Backup '.$backup->reference.' downloaded by '.(auth()->user()?->name ?? 'system').'.',
            category: AuditCategory::System,
            severity: AuditSeverity::Warning,
            subject: $backup,
            context: [
                'filename' => $backup->filename,
                'size_bytes' => $backup->size_bytes,
            ],
        );

        return Storage::disk($backup->disk)->download($backup->relative_path, $backup->filename);
    }

    public function downloadFiles(DatabaseBackup $backup): StreamedResponse
    {
        if (! $backup->status->isDownloadable() || ! $backup->filesArchiveExists()) {
            throw new InvalidArgumentException('This backup does not have a downloadable files archive.');
        }

        $this->audit->log(
            action: 'backup.files_downloaded',
            summary: 'Files archive for '.$backup->reference.' downloaded by '.(auth()->user()?->name ?? 'system').'.',
            category: AuditCategory::System,
            severity: AuditSeverity::Warning,
            subject: $backup,
            context: [
                'filename' => $backup->files_filename,
                'files_size_bytes' => $backup->files_size_bytes,
            ],
        );

        return Storage::disk($backup->disk)->download($backup->files_relative_path, $backup->files_filename);
    }

    /**
     * Restore the live database from a selected backup.
     * Always creates a safety backup of the current database first.
     *
     * @return array{safety: DatabaseBackup, restored: DatabaseBackup}
     */
    public function restore(DatabaseBackup $backup, ?User $actor = null, ?string $notes = null): array
    {
        if (! $backup->status->isRestorable()) {
            throw new InvalidArgumentException('Only completed or verified backups can be restored.');
        }

        if (! $backup->fileExists()) {
            throw new InvalidArgumentException('Backup file is missing from storage.');
        }

        // Re-verify before restore to avoid loading a corrupted dump.
        $verified = $this->verify($backup->fresh(), $actor);
        $sourcePath = $verified->absolutePath();

        if (! File::exists($sourcePath)) {
            throw new InvalidArgumentException('Backup file is missing from storage.');
        }

        $safety = $this->create(
            BackupType::SafetyPreRestore,
            $actor,
            [
                'notes' => 'Automatic safety backup before restoring '.$verified->reference,
                'skip_prune' => true,
            ],
        );

        try {
            if ($verified->driver === 'sqlite') {
                $this->restoreSqlite($verified);
            } else {
                $this->restoreMysql($verified);
            }
        } catch (Throwable $e) {
            $this->audit->log(
                action: 'backup.restore_failed',
                summary: 'Restore of '.$verified->reference.' failed after safety backup '.$safety->reference.': '.$e->getMessage(),
                category: AuditCategory::System,
                severity: AuditSeverity::Critical,
                subject: $verified,
                context: [
                    'safety_reference' => $safety->reference,
                    'error' => $e->getMessage(),
                ],
            );

            throw new RuntimeException(
                'Restore failed. A safety backup was saved as '.$safety->reference.'. Error: '.$e->getMessage(),
                0,
                $e,
            );
        }

        // SQLite/MySQL restores replace data with the selected dump, which predates the
        // safety backup catalog row. Re-insert that row so the safety file remains tracked.
        $this->repersistBackupCatalog($safety);

        // SQLite restores replace the DB file. Catalog fields held in memory must be
        // force-written because Eloquent dirty-checking would skip unchanged attributes
        // while the replaced file still has the pre-checksum snapshot values.
        DatabaseBackup::query()->whereKey($verified->id)->update([
            'relative_path' => $verified->relative_path,
            'filename' => $verified->filename,
            'size_bytes' => $verified->size_bytes,
            'checksum_sha256' => $verified->checksum_sha256,
            'disk' => $verified->disk,
            'driver' => $verified->driver instanceof \BackedEnum ? $verified->driver->value : $verified->driver,
            'type' => $verified->type instanceof \BackedEnum ? $verified->type->value : $verified->type,
            'completed_at' => $verified->completed_at,
            'verified_at' => $verified->verified_at,
            'verified_by' => $verified->verified_by,
            'status' => BackupStatus::Restored->value,
            'restored_at' => now(),
            'restored_by' => $actor?->id ?? auth()->id(),
            'notes' => trim(($verified->notes ? $verified->notes."\n" : '').($notes ?: 'Restored to live database.')),
            'error_message' => null,
        ]);

        $verified->refresh();
        $safety = DatabaseBackup::query()->where('reference', $safety->reference)->first() ?? $safety;

        $this->prune();

        $fresh = $verified->fresh(['restorer']);

        $this->audit->log(
            action: 'backup.restored',
            summary: 'Database restored from '.$fresh->reference.'. Safety backup: '.$safety->reference.'.',
            category: AuditCategory::System,
            severity: AuditSeverity::Critical,
            subject: $fresh,
            context: [
                'restored_reference' => $fresh->reference,
                'safety_reference' => $safety->reference,
                'notes' => $notes,
            ],
        );

        return [
            'safety' => $safety,
            'restored' => $fresh,
        ];
    }

    public function dismissFailed(DatabaseBackup $backup, ?User $actor = null): void
    {
        if ($backup->status !== BackupStatus::Failed) {
            throw new InvalidArgumentException('Only failed backup records can be dismissed.');
        }

        $reference = $backup->reference;
        $backup->delete();

        $this->audit->log(
            action: 'backup.failed_dismissed',
            summary: 'Failed backup record '.$reference.' dismissed.',
            category: AuditCategory::System,
            severity: AuditSeverity::Notice,
            context: [
                'reference' => $reference,
                'by' => $actor?->id ?? auth()->id(),
            ],
        );
    }

    /** @return LengthAwarePaginator<int, DatabaseBackup> */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $perPage ??= table_per_page();

        return DatabaseBackup::query()
            ->with(['creator:id,name', 'verifier:id,name', 'restorer:id,name'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->search($term))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function prune(?int $keep = null): int
    {
        $keepDaily = max(1, (int) ($keep ?? config('psg.backup.keep_daily', config('psg.backup.keep_days', 14))));
        $keepWeekly = max(1, (int) config('psg.backup.keep_weekly', 8));
        $keepMonthly = max(1, (int) config('psg.backup.keep_monthly', 12));
        $disk = (string) config('psg.backup.disk', 'backups');
        $pruned = 0;

        $retainable = DatabaseBackup::query()
            ->whereIn('status', [
                BackupStatus::Completed->value,
                BackupStatus::Verified->value,
                BackupStatus::Restored->value,
            ])
            ->where('type', '!=', BackupType::SafetyPreRestore->value)
            ->orderByDesc('id')
            ->get();

        $keepers = [];
        $dailyKept = 0;
        $weeklyKept = 0;
        $monthlyKept = 0;

        foreach ($retainable as $backup) {
            $bucket = $backup->type->retentionBucket();
            $keep = match ($bucket) {
                'weekly' => $keepWeekly,
                'monthly' => $keepMonthly,
                default => $keepDaily,
            };
            $count = match ($bucket) {
                'weekly' => $weeklyKept,
                'monthly' => $monthlyKept,
                default => $dailyKept,
            };

            if ($count < $keep) {
                $keepers[$backup->id] = true;
                match ($bucket) {
                    'weekly' => $weeklyKept++,
                    'monthly' => $monthlyKept++,
                    default => $dailyKept++,
                };
            }
        }

        foreach ($retainable as $backup) {
            if (isset($keepers[$backup->id])) {
                continue;
            }
            $this->deleteBackupFile($backup);
            $backup->delete();
            $pruned++;
        }

        // Age-based prune for safety backups older than daily retention window.
        $cutoff = now()->subDays($keepDaily);
        $oldSafety = DatabaseBackup::query()
            ->where('type', BackupType::SafetyPreRestore->value)
            ->where('created_at', '<', $cutoff)
            ->get();

        foreach ($oldSafety as $backup) {
            $this->deleteBackupFile($backup);
            $backup->delete();
            $pruned++;
        }

        // Clean orphaned psg-* files on the backup disk that have no catalog row.
        try {
            foreach (Storage::disk($disk)->files('/') as $file) {
                $name = basename($file);
                if (! str_starts_with($name, 'psg-')) {
                    continue;
                }

                $tracked = DatabaseBackup::query()
                    ->where('disk', $disk)
                    ->where(function ($q) use ($file, $name): void {
                        $q->where('relative_path', $file)
                            ->orWhere('filename', $name)
                            ->orWhere('files_relative_path', $file)
                            ->orWhere('files_filename', $name);
                    })
                    ->exists();

                if ($tracked) {
                    continue;
                }

                Storage::disk($disk)->delete($file);
                $pruned++;
            }
        } catch (Throwable) {
            // Disk listing failures should not break backup completion.
        }

        return $pruned;
    }

    public function syncOffsite(DatabaseBackup $backup): DatabaseBackup
    {
        $offsiteDisk = config('psg.backup.offsite_disk');

        if (! filled($offsiteDisk) || $offsiteDisk === 'none' || $offsiteDisk === $backup->disk) {
            return $backup;
        }

        if (! $backup->fileExists()) {
            return $backup;
        }

        try {
            $contents = Storage::disk($backup->disk)->get($backup->relative_path);
            $offsitePath = trim((string) config('psg.backup.offsite_path', 'psg-backups'), '/').'/'.$backup->filename;
            Storage::disk((string) $offsiteDisk)->put($offsitePath, $contents);

            if ($backup->filesArchiveExists()) {
                $filesContents = Storage::disk($backup->disk)->get($backup->files_relative_path);
                $filesOffsite = trim((string) config('psg.backup.offsite_path', 'psg-backups'), '/').'/'.$backup->files_filename;
                Storage::disk((string) $offsiteDisk)->put($filesOffsite, $filesContents);
            }

            $backup->update([
                'offsite_disk' => $offsiteDisk,
                'offsite_path' => $offsitePath,
                'offsite_synced_at' => now(),
            ]);
        } catch (Throwable $e) {
            $this->audit->log(
                action: 'backup.offsite_failed',
                summary: 'Off-site copy failed for '.$backup->reference.': '.$e->getMessage(),
                category: AuditCategory::System,
                severity: AuditSeverity::Warning,
                subject: $backup,
                context: ['error' => $e->getMessage(), 'offsite_disk' => $offsiteDisk],
            );
        }

        return $backup->fresh();
    }

    private function ensureBackupDisk(string $disk): void
    {
        $root = Storage::disk($disk)->path('');
        File::ensureDirectoryExists($root);

        $htaccess = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.htaccess';
        if (! File::exists($htaccess)) {
            File::put($htaccess, "Deny from all\n");
        }

        $gitignore = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.gitignore';
        if (! File::exists($gitignore)) {
            File::put($gitignore, "*\n!.gitignore\n!.htaccess\n");
        }
    }

    private function backupSqlite(string $target): void
    {
        $database = config('database.connections.sqlite.database');

        if (! is_string($database) || ! File::exists($database)) {
            throw new RuntimeException('SQLite database file was not found.');
        }

        // Flush WAL into the main DB file so the copy is a consistent snapshot.
        try {
            DB::connection()->getPdo()->exec('PRAGMA wal_checkpoint(TRUNCATE);');
        } catch (Throwable) {
            // Checkpoint is best-effort when the driver/connection cannot checkpoint.
        }

        File::ensureDirectoryExists(dirname($target));
        clearstatcache(true, $database);
        File::copy($database, $target);
    }

    private function backupMysql(string $target, string $connection): void
    {
        $config = config("database.connections.{$connection}");
        $mysqldump = $this->resolveBinary('mysqldump');

        if ($mysqldump === null) {
            throw new RuntimeException(
                'mysqldump was not found on PATH. On WAMP, add MySQL bin to PATH or install mysqldump.'
            );
        }

        File::ensureDirectoryExists(dirname($target));

        $command = [
            $mysqldump,
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--user='.$config['username'],
            '--single-transaction',
            '--routines',
            '--triggers',
            '--result-file='.$target,
            $config['database'],
        ];

        $process = new Process($command);
        $process->setTimeout(600);
        if (! empty($config['password'])) {
            $process->setEnv(array_merge($_ENV, ['MYSQL_PWD' => $config['password']]));
        }
        $process->run();

        if (! $process->isSuccessful() || ! File::exists($target) || File::size($target) === 0) {
            throw new RuntimeException('mysqldump failed: '.$process->getErrorOutput());
        }
    }

    private function restoreSqlite(DatabaseBackup $backup): void
    {
        $database = config('database.connections.sqlite.database');

        if (! is_string($database) || $database === ':memory:' || str_contains($database, 'mode=memory')) {
            throw new RuntimeException('Cannot restore over an in-memory SQLite database.');
        }

        $source = $backup->absolutePath();
        if (! File::exists($source)) {
            throw new RuntimeException('Backup file was not found at '.$source);
        }

        // Disconnect writers before replacing database contents.
        DB::purge(config('database.default'));
        gc_collect_cycles();
        clearstatcache(true, $database);

        foreach ([$database.'-wal', $database.'-shm'] as $sidecar) {
            if (is_file($sidecar)) {
                @unlink($sidecar);
            }
        }

        // Prefer page-level SQLite backup so Windows file locks don't block a rename/replace.
        if (class_exists(\SQLite3::class)) {
            $from = new \SQLite3($source, SQLITE3_OPEN_READONLY);
            $to = new \SQLite3($database, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);

            try {
                if (! $from->backup($to)) {
                    throw new RuntimeException('SQLite backup API failed during restore.');
                }
            } finally {
                $from->close();
                $to->close();
            }
        } else {
            File::copy($source, $database);
        }

        clearstatcache(true, $database);
        DB::reconnect(config('database.default'));
    }

    private function restoreMysql(DatabaseBackup $backup): void
    {
        $connection = (string) config('database.default');
        $config = config("database.connections.{$connection}");
        $mysql = $this->resolveBinary('mysql');

        if ($mysql === null) {
            throw new RuntimeException(
                'mysql client was not found on PATH. Restore requires the MySQL client binary.'
            );
        }

        $command = [
            $mysql,
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--user='.$config['username'],
            $config['database'],
            '-e',
            'source '.str_replace('\\', '/', $backup->absolutePath()),
        ];

        // Prefer stdin piping for Windows compatibility.
        $process = Process::fromShellCommandline(
            $this->quote($mysql)
            .' --host='.escapeshellarg($config['host'])
            .' --port='.escapeshellarg((string) $config['port'])
            .' --user='.escapeshellarg($config['username'])
            .' '.escapeshellarg($config['database'])
            .' < '.escapeshellarg($backup->absolutePath())
        );
        $process->setTimeout(900);
        if (! empty($config['password'])) {
            $process->setEnv(array_merge($_ENV, ['MYSQL_PWD' => $config['password']]));
        }
        $process->run();

        if (! $process->isSuccessful()) {
            // Fallback without shell redirection.
            $fallback = new Process([
                $mysql,
                '--host='.$config['host'],
                '--port='.$config['port'],
                '--user='.$config['username'],
                $config['database'],
            ]);
            $fallback->setTimeout(900);
            if (! empty($config['password'])) {
                $fallback->setEnv(array_merge($_ENV, ['MYSQL_PWD' => $config['password']]));
            }
            $fallback->setInput(File::get($backup->absolutePath()));
            $fallback->run();

            if (! $fallback->isSuccessful()) {
                throw new RuntimeException('mysql restore failed: '.$fallback->getErrorOutput() ?: $process->getErrorOutput());
            }
        }

        unset($command);
        DB::reconnect($connection);
    }

    private function resolveBinary(string $name): ?string
    {
        $candidates = [$name];

        if (PHP_OS_FAMILY === 'Windows') {
            $candidates = array_merge($candidates, [
                'C:\\wamp64\\bin\\mysql\\mysql8.3.0\\bin\\'.$name.'.exe',
                'C:\\wamp64\\bin\\mysql\\mysql8.2.0\\bin\\'.$name.'.exe',
                'C:\\wamp64\\bin\\mysql\\mysql8.1.0\\bin\\'.$name.'.exe',
                'C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\'.$name.'.exe',
                'C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\'.$name.'.exe',
            ]);

            foreach (glob('C:\\wamp64\\bin\\mysql\\*\\bin\\'.$name.'.exe') ?: [] as $path) {
                $candidates[] = $path;
            }
        }

        foreach ($candidates as $binary) {
            if ($binary === $name) {
                $process = Process::fromShellCommandline(
                    PHP_OS_FAMILY === 'Windows' ? 'where '.$name : 'command -v '.$name
                );
                $process->run();
                if ($process->isSuccessful() && filled(trim($process->getOutput()))) {
                    return $name;
                }

                continue;
            }

            if (is_file($binary)) {
                return $binary;
            }
        }

        return null;
    }

    private function quote(string $path): string
    {
        if (str_contains($path, ' ')) {
            return '"'.$path.'"';
        }

        return $path;
    }

    private function deleteBackupFile(DatabaseBackup $backup): void
    {
        if ($backup->fileExists()) {
            Storage::disk($backup->disk)->delete($backup->relative_path);
        }

        if ($backup->filesArchiveExists()) {
            Storage::disk($backup->disk)->delete($backup->files_relative_path);
        }

        if (filled($backup->offsite_disk) && filled($backup->offsite_path)) {
            try {
                Storage::disk((string) $backup->offsite_disk)->delete((string) $backup->offsite_path);
                if (filled($backup->files_filename)) {
                    $filesOffsite = trim((string) config('psg.backup.offsite_path', 'psg-backups'), '/').'/'.$backup->files_filename;
                    Storage::disk((string) $backup->offsite_disk)->delete($filesOffsite);
                }
            } catch (Throwable) {
                // Off-site cleanup is best-effort.
            }
        }
    }

    /**
     * Zip private uploads (guard documents, attachments) into the backup disk.
     *
     * @return array{
     *     includes_files: bool,
     *     files_relative_path: ?string,
     *     files_filename: ?string,
     *     files_size_bytes: int,
     *     files_checksum_sha256: ?string
     * }
     */
    private function backupPrivateFiles(string $disk, string $stamp): array
    {
        $empty = [
            'includes_files' => false,
            'files_relative_path' => null,
            'files_filename' => null,
            'files_size_bytes' => 0,
            'files_checksum_sha256' => null,
        ];

        if (! class_exists(\ZipArchive::class)) {
            return $empty;
        }

        $privateRoot = storage_path('app/private');
        if (! is_dir($privateRoot)) {
            return $empty;
        }

        $filename = "psg-files-{$stamp}.zip";
        $absolute = Storage::disk($disk)->path($filename);
        File::ensureDirectoryExists(dirname($absolute));

        $zip = new \ZipArchive;
        if ($zip->open($absolute, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create files archive zip.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($privateRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        $added = 0;
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $full = $file->getRealPath();
            $relative = 'private/'.ltrim(str_replace('\\', '/', substr($full, strlen($privateRoot))), '/');
            $zip->addFile($full, $relative);
            $added++;
        }

        $zip->close();

        if ($added === 0) {
            @unlink($absolute);

            return $empty;
        }

        return [
            'includes_files' => true,
            'files_relative_path' => $filename,
            'files_filename' => $filename,
            'files_size_bytes' => File::size($absolute),
            'files_checksum_sha256' => hash_file('sha256', $absolute),
        ];
    }

    /**
     * Non-destructive restore drill: verify checksums and open the dump in isolation.
     *
     * @return array{ok: bool, notes: string, checks: array<string, mixed>}
     */
    public function testRestore(DatabaseBackup $backup, ?User $actor = null): array
    {
        $verified = $this->verify($backup->fresh(), $actor);
        $checks = [
            'database_checksum' => true,
            'files_checksum' => $verified->includes_files ? true : null,
            'readable' => false,
            'row_smoke' => null,
        ];

        $notes = [];

        if ($verified->driver === 'sqlite') {
            if (! class_exists(\SQLite3::class)) {
                throw new RuntimeException('SQLite3 extension is required for restore drills.');
            }

            $temp = storage_path('app/tmp/restore-test-'.$verified->id.'-'.uniqid('', true).'.sqlite');
            File::ensureDirectoryExists(dirname($temp));
            File::copy($verified->absolutePath(), $temp);

            try {
                $db = new \SQLite3($temp, SQLITE3_OPEN_READONLY);
                $checks['readable'] = true;
                $result = $db->querySingle('SELECT COUNT(*) FROM users');
                $checks['row_smoke'] = (int) $result;
                $notes[] = 'SQLite dump opened read-only; users table count='.$checks['row_smoke'].'.';
                $db->close();
            } finally {
                @unlink($temp);
            }
        } else {
            // MySQL dumps are validated by checksum + non-empty file; full import drills need a staging host.
            $checks['readable'] = File::size($verified->absolutePath()) > 0;
            $notes[] = 'MySQL dump checksum verified and file is non-empty. Full import drills require a staging database.';
        }

        if ($verified->includes_files && $verified->filesArchiveExists()) {
            $zip = new \ZipArchive;
            $path = Storage::disk($verified->disk)->path($verified->files_relative_path);
            if ($zip->open($path) === true) {
                $notes[] = 'Files archive contains '.$zip->numFiles.' entries.';
                $zip->close();
            } else {
                throw new RuntimeException('Files archive could not be opened during restore drill.');
            }
        }

        $summary = implode(' ', $notes);

        $verified->update([
            'restore_tested_at' => now(),
            'restore_test_notes' => $summary,
        ]);

        $this->audit->log(
            action: 'backup.restore_tested',
            summary: 'Restore drill passed for '.$verified->reference.'.',
            category: AuditCategory::System,
            severity: AuditSeverity::Notice,
            subject: $verified->fresh(),
            context: [
                'checks' => $checks,
                'notes' => $summary,
                'by' => $actor?->id ?? auth()->id(),
            ],
        );

        return [
            'ok' => true,
            'notes' => $summary,
            'checks' => $checks,
        ];
    }

    /**
     * Restore uploaded documents/files from a backup zip into storage/app/private.
     */
    public function restoreFiles(DatabaseBackup $backup, ?User $actor = null, bool $overwrite = false): int
    {
        if (! $backup->includes_files || ! $backup->filesArchiveExists()) {
            throw new InvalidArgumentException('This backup does not include a files archive.');
        }

        if (! class_exists(\ZipArchive::class)) {
            throw new RuntimeException('ZipArchive extension is required to restore files.');
        }

        $this->verify($backup->fresh(), $actor);

        $targetRoot = storage_path('app');
        $zipPath = Storage::disk($backup->disk)->path($backup->files_relative_path);
        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Unable to open files archive.');
        }

        $extracted = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || str_ends_with($name, '/')) {
                continue;
            }
            if (! str_starts_with($name, 'private/')) {
                continue;
            }

            $dest = $targetRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name);
            if (File::exists($dest) && ! $overwrite) {
                continue;
            }

            File::ensureDirectoryExists(dirname($dest));
            $stream = $zip->getStream($name);
            if ($stream === false) {
                continue;
            }
            file_put_contents($dest, stream_get_contents($stream));
            fclose($stream);
            $extracted++;
        }
        $zip->close();

        $this->audit->log(
            action: 'backup.files_restored',
            summary: 'Restored '.$extracted.' file(s) from '.$backup->reference.'.',
            category: AuditCategory::System,
            severity: AuditSeverity::Critical,
            subject: $backup,
            context: [
                'extracted' => $extracted,
                'overwrite' => $overwrite,
                'by' => $actor?->id ?? auth()->id(),
            ],
        );

        return $extracted;
    }

    public function latestSuccessful(): ?DatabaseBackup
    {
        return DatabaseBackup::query()
            ->whereIn('status', [
                BackupStatus::Completed->value,
                BackupStatus::Verified->value,
                BackupStatus::Restored->value,
            ])
            ->where('type', '!=', BackupType::SafetyPreRestore->value)
            ->latest('completed_at')
            ->latest('id')
            ->first();
    }

    public function hoursSinceLastSuccessfulBackup(): ?float
    {
        $latest = $this->latestSuccessful();
        if (! $latest?->completed_at) {
            return null;
        }

        return $latest->completed_at->diffInMinutes(now()) / 60;
    }

    private function repersistBackupCatalog(DatabaseBackup $backup): void
    {
        $payload = [
            'reference' => $backup->reference,
            'type' => $backup->type instanceof \BackedEnum ? $backup->type->value : $backup->type,
            'status' => $backup->status instanceof \BackedEnum ? $backup->status->value : $backup->status,
            'disk' => $backup->disk,
            'relative_path' => $backup->relative_path,
            'filename' => $backup->filename,
            'files_relative_path' => $backup->files_relative_path,
            'files_filename' => $backup->files_filename,
            'driver' => $backup->driver,
            'includes_files' => (bool) $backup->includes_files,
            'size_bytes' => $backup->size_bytes,
            'files_size_bytes' => $backup->files_size_bytes,
            'checksum_sha256' => $backup->checksum_sha256,
            'files_checksum_sha256' => $backup->files_checksum_sha256,
            'offsite_disk' => $backup->offsite_disk,
            'offsite_path' => $backup->offsite_path,
            'offsite_synced_at' => $backup->offsite_synced_at,
            'started_at' => $backup->started_at,
            'completed_at' => $backup->completed_at,
            'verified_at' => $backup->verified_at,
            'restored_at' => $backup->restored_at,
            'restore_tested_at' => $backup->restore_tested_at,
            'created_by' => $backup->created_by,
            'verified_by' => $backup->verified_by,
            'restored_by' => $backup->restored_by,
            'error_message' => $backup->error_message,
            'notes' => $backup->notes,
            'restore_test_notes' => $backup->restore_test_notes,
        ];

        DatabaseBackup::query()->updateOrCreate(
            ['reference' => $backup->reference],
            $payload,
        );
    }

    private function nextReference(): string
    {
        $prefix = 'BKP-'.now()->format('Ymd');
        $count = DatabaseBackup::query()->where('reference', 'like', $prefix.'%')->count() + 1;

        return $prefix.'-'.str_pad((string) $count, 3, '0', STR_PAD_LEFT);
    }
}
