<?php

namespace Tests\Feature\Production;

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
        File::put($source, 'SQLite format 3 test');
        File::deleteDirectory($dir);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $source,
        ]);

        $this->artisan('psg:backup-database', [
            '--path' => 'backups-test',
            '--keep' => 3,
        ])->assertSuccessful();

        $files = File::files($dir);
        $this->assertNotEmpty($files);
        $this->assertStringStartsWith('psg-sqlite-', $files[0]->getFilename());
        $this->assertSame('SQLite format 3 test', File::get($files[0]->getPathname()));

        File::delete($source);
        File::deleteDirectory($dir);
    }
}
