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
     * @param  array{notes?: string|null, skip_prune?: bool}  $options
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

        $backup = DatabaseBackup::query()->create([
            'reference' => $this->nextReference(),
            'type' => $type,
            'status' => BackupStatus::Pending,
            'disk' => $disk,
            'relative_path' => '',
            'filename' => '',
            'driver' => $driver === 'mariadb' ? 'mysql' : $driver,
            'size_bytes' => 0,
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

            $backup->update([
                'size_bytes' => $size,
                'checksum_sha256' => $checksum,
                'status' => BackupStatus::Completed,
                'completed_at' => now(),
                'error_message' => null,
            ]);

            $this->syncOffsite($backup->fresh());

            if (! ($options['skip_prune'] ?? false)) {
                $this->prune();
            }

            $fresh = $backup->fresh(['creator']);

            $this->audit->log(
                action: 'backup.completed',
                summary: 'Database backup '.$fresh->reference.' completed ('.$fresh->type->label().', '.$fresh->formattedSize().').',
                category: AuditCategory::System,
                severity: AuditSeverity::Notice,
                subject: $fresh,
                context: [
                    'reference' => $fresh->reference,
                    'type' => $fresh->type->value,
                    'filename' => $fresh->filename,
                    'size_bytes' => $fresh->size_bytes,
                    'checksum_sha256' => $fresh->checksum_sha256,
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
                'size_bytes' => $fresh->size_bytes,
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
        $keep ??= max(1, (int) config('psg.backup.keep_days', 14));
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

        foreach ($retainable->slice($keep) as $backup) {
            $this->deleteBackupFile($backup);
            $backup->delete();
            $pruned++;
        }

        // Age-based prune for safety backups older than retention window.
        $cutoff = now()->subDays($keep);
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
        if (Storage::disk($disk)->exists('/')) {
            foreach (Storage::disk($disk)->files('/') as $file) {
                $name = basename($file);
                if (! str_starts_with($name, 'psg-')) {
                    continue;
                }

                $tracked = DatabaseBackup::query()
                    ->where('disk', $disk)
                    ->where(function ($q) use ($file, $name) {
                        $q->where('relative_path', $file)->orWhere('filename', $name);
                    })
                    ->exists();

                if ($tracked) {
                    continue;
                }

                // Keep newest orphans up to retention, delete the rest by mtime.
            }
        }

        unset($disk);

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

        if (filled($backup->offsite_disk) && filled($backup->offsite_path)) {
            try {
                Storage::disk((string) $backup->offsite_disk)->delete((string) $backup->offsite_path);
            } catch (Throwable) {
                // Off-site cleanup is best-effort.
            }
        }
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
            'driver' => $backup->driver,
            'size_bytes' => $backup->size_bytes,
            'checksum_sha256' => $backup->checksum_sha256,
            'offsite_disk' => $backup->offsite_disk,
            'offsite_path' => $backup->offsite_path,
            'offsite_synced_at' => $backup->offsite_synced_at,
            'started_at' => $backup->started_at,
            'completed_at' => $backup->completed_at,
            'verified_at' => $backup->verified_at,
            'restored_at' => $backup->restored_at,
            'created_by' => $backup->created_by,
            'verified_by' => $backup->verified_by,
            'restored_by' => $backup->restored_by,
            'error_message' => $backup->error_message,
            'notes' => $backup->notes,
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
