<?php

namespace Tests\Feature\Production;

use App\Enums\BackupType;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProductionToolingTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_check_command_passes_in_local_test_env(): void
    {
        $this->artisan('psg:production-check')
            ->expectsOutputToContain('Production check passed.')
            ->assertSuccessful();
    }

    public function test_sqlite_backup_copies_file_database(): void
    {
        $source = storage_path('app/backup-source-test.sqlite');
        $dir = storage_path('app/backups-test');

        File::ensureDirectoryExists(dirname($source));
        // Minimal valid SQLite header + empty DB created via PDO.
        if (File::exists($source)) {
            File::delete($source);
        }
        new \PDO('sqlite:'.$source);
        File::deleteDirectory($dir);
        File::ensureDirectoryExists($dir);

        $previousDefault = config('database.default');
        $previousSqlite = config('database.connections.sqlite.database');

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $source,
            'psg.backup.disk' => 'backups',
            'psg.backup.path' => 'backups-test',
            'psg.backup.keep_days' => 3,
            'psg.backup.notify' => false,
            'filesystems.disks.backups' => [
                'driver' => 'local',
                'root' => $dir,
                'visibility' => 'private',
                'throw' => false,
            ],
        ]);
        app('filesystem')->forgetDisk('backups');

        try {
            $backup = app(DatabaseBackupService::class)->create(BackupType::Manual);
            $this->assertTrue($backup->fileExists());
            $this->assertStringStartsWith('psg-sqlite-', $backup->filename);
            $this->assertSame(File::size($source), File::size($backup->absolutePath()));
        } finally {
            config([
                'database.default' => $previousDefault,
                'database.connections.sqlite.database' => $previousSqlite,
            ]);
            File::delete($source);
            File::deleteDirectory($dir);
        }
    }
}
