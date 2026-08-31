<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->boolean('payroll_use_progressive_paye')
                ->default(true)
                ->after('payroll_paye_rate');
            $table->string('payroll_bank_export_format', 32)
                ->default('generic')
                ->after('payroll_uniform_charge');
            $table->boolean('payroll_send_payslip_email_on_approve')
                ->default(true)
                ->after('payroll_bank_export_format');
        });

        DB::table('system_settings')->update([
            'payroll_use_progressive_paye' => filter_var(config('psg.payroll.use_progressive_paye', true), FILTER_VALIDATE_BOOL),
            'payroll_bank_export_format' => (string) config('psg.payroll.bank_export_format', 'generic'),
            'payroll_send_payslip_email_on_approve' => filter_var(config('psg.payroll.send_payslip_email_on_approve', true), FILTER_VALIDATE_BOOL),
        ]);
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'payroll_use_progressive_paye',
                'payroll_bank_export_format',
                'payroll_send_payslip_email_on_approve',
            ]);
        });
    }
};
