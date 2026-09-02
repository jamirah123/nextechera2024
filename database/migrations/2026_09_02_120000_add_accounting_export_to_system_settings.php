<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->boolean('accounting_export_enabled')->default(false)->after('backup_path');
            $table->string('accounting_export_path', 120)->default('exports/accounting')->after('accounting_export_enabled');
            $table->timestamp('accounting_export_last_run_at')->nullable()->after('accounting_export_path');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'accounting_export_enabled',
                'accounting_export_path',
                'accounting_export_last_run_at',
            ]);
        });
    }
};
