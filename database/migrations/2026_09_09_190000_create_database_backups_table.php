<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_backups', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('type', 32)->index();
            $table->string('status', 24)->index();
            $table->string('disk', 40)->default('backups');
            $table->string('relative_path');
            $table->string('filename');
            $table->string('driver', 16);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum_sha256', 64)->nullable()->index();
            $table->string('offsite_disk', 40)->nullable();
            $table->string('offsite_path')->nullable();
            $table->timestamp('offsite_synced_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('restored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'status']);
        });

        Schema::table('system_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('system_settings', 'backup_schedule')) {
                $table->string('backup_schedule', 32)->default('daily')->after('backup_path');
            }
            if (! Schema::hasColumn('system_settings', 'backup_notify')) {
                $table->boolean('backup_notify')->default(true)->after('backup_schedule');
            }
            if (! Schema::hasColumn('system_settings', 'backup_offsite_disk')) {
                $table->string('backup_offsite_disk', 40)->nullable()->after('backup_notify');
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            foreach (['backup_schedule', 'backup_notify', 'backup_offsite_disk'] as $column) {
                if (Schema::hasColumn('system_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('database_backups');
    }
};
