<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('database_backups', function (Blueprint $table): void {
            if (! Schema::hasColumn('database_backups', 'includes_files')) {
                $table->boolean('includes_files')->default(false)->after('driver');
            }
            if (! Schema::hasColumn('database_backups', 'files_relative_path')) {
                $table->string('files_relative_path')->nullable()->after('relative_path');
            }
            if (! Schema::hasColumn('database_backups', 'files_filename')) {
                $table->string('files_filename')->nullable()->after('filename');
            }
            if (! Schema::hasColumn('database_backups', 'files_size_bytes')) {
                $table->unsignedBigInteger('files_size_bytes')->default(0)->after('size_bytes');
            }
            if (! Schema::hasColumn('database_backups', 'files_checksum_sha256')) {
                $table->string('files_checksum_sha256', 64)->nullable()->after('checksum_sha256');
            }
            if (! Schema::hasColumn('database_backups', 'restore_tested_at')) {
                $table->timestamp('restore_tested_at')->nullable()->after('restored_at');
            }
            if (! Schema::hasColumn('database_backups', 'restore_test_notes')) {
                $table->text('restore_test_notes')->nullable()->after('notes');
            }
        });

        Schema::table('system_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('system_settings', 'backup_keep_daily')) {
                $table->unsignedSmallInteger('backup_keep_daily')->default(14)->after('backup_keep_days');
            }
            if (! Schema::hasColumn('system_settings', 'backup_keep_weekly')) {
                $table->unsignedSmallInteger('backup_keep_weekly')->default(8)->after('backup_keep_daily');
            }
            if (! Schema::hasColumn('system_settings', 'backup_keep_monthly')) {
                $table->unsignedSmallInteger('backup_keep_monthly')->default(12)->after('backup_keep_weekly');
            }
            if (! Schema::hasColumn('system_settings', 'backup_include_files')) {
                $table->boolean('backup_include_files')->default(true)->after('backup_keep_monthly');
            }
            if (! Schema::hasColumn('system_settings', 'backup_stale_hours')) {
                $table->unsignedSmallInteger('backup_stale_hours')->default(36)->after('backup_include_files');
            }
        });
    }

    public function down(): void
    {
        Schema::table('database_backups', function (Blueprint $table): void {
            foreach ([
                'includes_files',
                'files_relative_path',
                'files_filename',
                'files_size_bytes',
                'files_checksum_sha256',
                'restore_tested_at',
                'restore_test_notes',
            ] as $column) {
                if (Schema::hasColumn('database_backups', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('system_settings', function (Blueprint $table): void {
            foreach ([
                'backup_keep_daily',
                'backup_keep_weekly',
                'backup_keep_monthly',
                'backup_include_files',
                'backup_stale_hours',
            ] as $column) {
                if (Schema::hasColumn('system_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
