<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'psg:backup-database
                            {--keep=14 : Number of backups to retain}
                            {--path=backups : Directory under storage/app}';

    protected $description = 'Create a timestamped database backup for Platinum Shifts (MySQL dump or SQLite copy)';

    public function handle(): int
    {
        try {
            $connection = config('database.default');
            $driver = config("database.connections.{$connection}.driver");
            $dir = storage_path('app/'.trim((string) $this->option('path'), '/\\'));
            File::ensureDirectoryExists($dir);

            $stamp = now()->format('Ymd-His');
            $path = match ($driver) {
                'sqlite' => $this->backupSqlite($dir, $stamp),
                'mysql', 'mariadb' => $this->backupMysql($dir, $stamp, $connection),
                default => null,
            };

            if ($path === null) {
                $this->error("Unsupported database driver [{$driver}] for automated backup.");

                return self::FAILURE;
            }

            $this->info('Backup created: '.$path);
            $this->prune($dir, (int) $this->option('keep'));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function backupSqlite(string $dir, string $stamp): string
    {
        $database = config('database.connections.sqlite.database');
        $target = $dir.DIRECTORY_SEPARATOR."psg-sqlite-{$stamp}.sqlite";

        if (! is_string($database) || ! File::exists($database)) {
            throw new \RuntimeException('SQLite database file was not found.');
        }

        File::copy($database, $target);

        return $target;
    }

    private function backupMysql(string $dir, string $stamp, string $connection): string
    {
        $config = config("database.connections.{$connection}");
        $target = $dir.DIRECTORY_SEPARATOR."psg-mysql-{$stamp}.sql";
        $mysqldump = $this->resolveMysqlDumpBinary();

        if ($mysqldump === null) {
            throw new \RuntimeException(
                'mysqldump was not found on PATH. On WAMP, add MySQL bin to PATH or run backups from a host with mysqldump installed.'
            );
        }

        $command = [
            $mysqldump,
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--user='.$config['username'],
            '--single-transaction',
            '--routines',
            '--triggers',
            $config['database'],
        ];

        $process = new Process($command);
        $process->setTimeout(300);
        if (! empty($config['password'])) {
            $process->setEnv(array_merge($_ENV, ['MYSQL_PWD' => $config['password']]));
        }
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('mysqldump failed: '.$process->getErrorOutput());
        }

        File::put($target, $process->getOutput());

        return $target;
    }

    private function resolveMysqlDumpBinary(): ?string
    {
        $candidates = [
            'mysqldump',
            'C:\\wamp64\\bin\\mysql\\mysql8.3.0\\bin\\mysqldump.exe',
            'C:\\wamp64\\bin\\mysql\\mysql8.2.0\\bin\\mysqldump.exe',
            'C:\\wamp64\\bin\\mysql\\mysql8.1.0\\bin\\mysqldump.exe',
            'C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\mysqldump.exe',
            'C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysqldump.exe',
        ];

        foreach (glob('C:\\wamp64\\bin\\mysql\\*\\bin\\mysqldump.exe') ?: [] as $path) {
            $candidates[] = $path;
        }

        foreach ($candidates as $binary) {
            if ($binary === 'mysqldump') {
                $process = Process::fromShellCommandline(PHP_OS_FAMILY === 'Windows' ? 'where mysqldump' : 'command -v mysqldump');
                $process->run();
                if ($process->isSuccessful() && filled(trim($process->getOutput()))) {
                    return 'mysqldump';
                }

                continue;
            }

            if (is_file($binary)) {
                return $binary;
            }
        }

        return null;
    }

    private function prune(string $dir, int $keep): void
    {
        $files = collect(File::files($dir))
            ->filter(fn ($file) => str_starts_with($file->getFilename(), 'psg-'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        foreach ($files->slice(max(0, $keep)) as $file) {
            File::delete($file->getPathname());
            $this->line('Pruned old backup: '.$file->getFilename());
        }
    }
}
