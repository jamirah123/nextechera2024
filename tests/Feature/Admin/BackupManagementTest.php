<?php

namespace Tests\Feature\Admin;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Enums\UserRole;
use App\Models\DatabaseBackup;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupManagementTest extends TestCase
{
    use DatabaseMigrations;

    private string $appDb;

    private string $backupRoot;

    protected function setUp(): void
    {
        $base = dirname(__DIR__, 3);
        $this->appDb = $base.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'backup-mgmt-app.sqlite';
        $this->backupRoot = $base.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'backups-mgmt-test';

        if (! is_dir(dirname($this->appDb))) {
            mkdir(dirname($this->appDb), 0777, true);
        }
        if (is_file($this->appDb)) {
            @unlink($this->appDb);
        }
        if (is_dir($this->backupRoot)) {
            $this->deleteTree($this->backupRoot);
        }
        mkdir($this->backupRoot, 0777, true);

        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE='.$this->appDb);
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $this->appDb;
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = $this->appDb;

        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->appDb,
            'psg.backup.disk' => 'backups',
            'psg.backup.path' => 'backups-mgmt-test',
            'psg.backup.keep_days' => 5,
            'psg.backup.keep_daily' => 5,
            'psg.backup.keep_weekly' => 3,
            'psg.backup.keep_monthly' => 2,
            'psg.backup.include_files' => true,
            'psg.backup.stale_hours' => 36,
            'psg.backup.notify' => false,
            'psg.backup.offsite_disk' => null,
            'filesystems.disks.backups' => [
                'driver' => 'local',
                'root' => $this->backupRoot,
                'visibility' => 'private',
                'throw' => false,
            ],
        ]);

        app('filesystem')->forgetDisk('backups');
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } catch (\Throwable) {
            // Restore may replace the SQLite file mid-test.
        }

        if (is_file($this->appDb)) {
            @unlink($this->appDb);
        }
        if (is_dir($this->backupRoot)) {
            $this->deleteTree($this->backupRoot);
        }

        // These tests point SQLite at a file. Restore the suite default
        // so later tests keep using the in-memory database.
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = ':memory:';
    }

    private function deleteTree(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->deleteTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function test_super_admin_can_create_and_verify_backup(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();

        $this->actingAs($admin)
            ->post(route('backups.store'))
            ->assertRedirect();

        $backup = DatabaseBackup::query()->first();
        $this->assertNotNull($backup);
        $this->assertSame(BackupStatus::Completed, $backup->status);
        $this->assertSame(BackupType::Manual, $backup->type);
        $this->assertNotEmpty($backup->checksum_sha256);
        $this->assertTrue($backup->fileExists());

        $this->actingAs($admin)
            ->post(route('backups.verify', $backup))
            ->assertRedirect();

        $this->assertSame(BackupStatus::Verified, $backup->fresh()->status);
    }

    public function test_backup_download_is_authorized(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();
        $backup = app(DatabaseBackupService::class)->create(BackupType::Manual, $admin);

        $this->actingAs($admin)
            ->get(route('backups.download', $backup))
            ->assertOk();

        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $this->actingAs($ops)
            ->get(route('backups.download', $backup))
            ->assertForbidden();
    }

    public function test_restore_creates_safety_backup_and_replaces_database_file(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create(['name' => 'Restore Admin']);
        $service = app(DatabaseBackupService::class);
        $backup = $service->create(BackupType::Manual, $admin);
        $hashBeforeMutation = hash_file('sha256', $this->appDb);

        User::factory()->role(UserRole::HrManager)->create(['name' => 'Post Backup User']);
        $this->assertNotSame($hashBeforeMutation, hash_file('sha256', $this->appDb));

        $result = $service->restore($backup->fresh(), $admin, 'Feature test restore');

        // Restore updates catalog rows after the file swap, so the live DB hash
        // will differ from the pre-mutation snapshot — assert data + safety instead.
        $this->assertSame(BackupStatus::Restored, $result['restored']->status);
        $this->assertSame(BackupType::SafetyPreRestore, $result['safety']->type);
        $this->assertTrue($result['safety']->fileExists());
        $this->assertSame(
            $result['restored']->checksum_sha256,
            hash_file('sha256', $result['restored']->absolutePath()),
        );

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->assertNull(User::query()->where('name', 'Post Backup User')->first());
        $this->assertNotNull(User::query()->where('name', 'Restore Admin')->first());
    }

    public function test_scheduled_backup_type_is_catalogued(): void
    {
        $backup = app(DatabaseBackupService::class)->create(BackupType::ScheduledDaily);

        $this->assertSame(BackupType::ScheduledDaily, $backup->type);
        $this->assertTrue(File::exists($backup->absolutePath()));
        $this->assertDatabaseHas('database_backups', [
            'reference' => $backup->reference,
            'status' => BackupStatus::Completed->value,
        ]);
    }

    public function test_backup_can_include_private_files_archive(): void
    {
        $private = storage_path('app/private/guards');
        File::ensureDirectoryExists($private);
        $doc = $private.DIRECTORY_SEPARATOR.'backup-test-doc.txt';
        File::put($doc, 'confidential guard document');

        $backup = app(DatabaseBackupService::class)->create(BackupType::Manual, null, [
            'include_files' => true,
        ]);

        $this->assertTrue($backup->includes_files);
        $this->assertTrue($backup->filesArchiveExists());
        $this->assertNotEmpty($backup->files_checksum_sha256);

        File::delete($doc);
    }

    public function test_restore_drill_marks_backup_as_tested(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();
        $service = app(DatabaseBackupService::class);
        $backup = $service->create(BackupType::Manual, $admin);

        $result = $service->testRestore($backup->fresh(), $admin);

        $this->assertTrue($result['ok']);
        $fresh = $backup->fresh();
        $this->assertNotNull($fresh->restore_tested_at);
        $this->assertSame(BackupStatus::Verified, $fresh->status);
    }

    public function test_tiered_retention_keeps_weekly_and_monthly_buckets(): void
    {
        $service = app(DatabaseBackupService::class);
        config([
            'psg.backup.keep_daily' => 1,
            'psg.backup.keep_weekly' => 1,
            'psg.backup.keep_monthly' => 1,
        ]);

        $dailyOld = $service->create(BackupType::ScheduledDaily, null, ['skip_prune' => true, 'include_files' => false]);
        $dailyNew = $service->create(BackupType::ScheduledDaily, null, ['skip_prune' => true, 'include_files' => false]);
        $weekly = $service->create(BackupType::ScheduledWeekly, null, ['skip_prune' => true, 'include_files' => false]);
        $monthly = $service->create(BackupType::ScheduledMonthly, null, ['skip_prune' => true, 'include_files' => false]);

        $service->prune();

        $this->assertNull(DatabaseBackup::query()->find($dailyOld->id));
        $this->assertNotNull(DatabaseBackup::query()->find($dailyNew->id));
        $this->assertNotNull(DatabaseBackup::query()->find($weekly->id));
        $this->assertNotNull(DatabaseBackup::query()->find($monthly->id));
    }

    public function test_super_admin_can_run_restore_drill_via_http(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();
        $backup = app(DatabaseBackupService::class)->create(BackupType::Manual, $admin);

        $this->actingAs($admin)
            ->post(route('backups.test-restore', $backup))
            ->assertRedirect();

        $this->assertNotNull($backup->fresh()->restore_tested_at);
    }
}
