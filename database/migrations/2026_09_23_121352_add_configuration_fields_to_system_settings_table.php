<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            $table->string('employment_id_prefix', 12)->default('PSG')->after('company_short_name');
            $table->string('invoice_prefix', 12)->default('INV')->after('employment_id_prefix');
            $table->string('payroll_run_prefix', 12)->default('PAY')->after('invoice_prefix');
            $table->string('shift_prefix', 12)->default('SHF')->after('payroll_run_prefix');
            $table->string('timezone', 64)->default('Africa/Dar_es_Salaam')->after('shift_prefix');
            $table->decimal('vat_rate', 5, 2)->default(18)->after('currency_decimals');
            $table->string('supervisor_normal_start', 5)->default('06:00')->after('default_night_shift_end');
            $table->string('supervisor_normal_end', 5)->default('19:00')->after('supervisor_normal_start');
            $table->string('login_headline', 191)->nullable()->after('system_subtitle');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'employment_id_prefix',
                'invoice_prefix',
                'payroll_run_prefix',
                'shift_prefix',
                'timezone',
                'vat_rate',
                'supervisor_normal_start',
                'supervisor_normal_end',
                'login_headline',
            ]);
        });
    }
};
