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
            $table->decimal('payroll_default_base_shift_rate', 12, 2)
                ->default(25000)
                ->after('invoice_due_days');
            $table->decimal('payroll_overtime_multiplier', 5, 2)
                ->default(1.5)
                ->after('payroll_default_base_shift_rate');
            $table->decimal('payroll_paye_rate', 5, 2)
                ->default(0)
                ->after('payroll_overtime_multiplier');
            $table->decimal('payroll_nssf_employee_rate', 5, 2)
                ->default(5)
                ->after('payroll_paye_rate');
            $table->decimal('payroll_uniform_charge', 12, 2)
                ->default(0)
                ->after('payroll_nssf_employee_rate');
        });

        DB::table('system_settings')->update([
            'payroll_default_base_shift_rate' => (float) config('psg.payroll.default_base_shift_rate', 25000),
            'payroll_overtime_multiplier' => (float) config('psg.payroll.overtime_multiplier', 1.5),
            'payroll_paye_rate' => (float) config('psg.payroll.paye_rate', 0),
            'payroll_nssf_employee_rate' => (float) config('psg.payroll.nssf_employee_rate', 5),
            'payroll_uniform_charge' => (float) config('psg.payroll.uniform_charge', 0),
        ]);
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'payroll_default_base_shift_rate',
                'payroll_overtime_multiplier',
                'payroll_paye_rate',
                'payroll_nssf_employee_rate',
                'payroll_uniform_charge',
            ]);
        });
    }
};
