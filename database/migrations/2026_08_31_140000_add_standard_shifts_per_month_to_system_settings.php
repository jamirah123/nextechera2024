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
            $table->unsignedTinyInteger('payroll_standard_shifts_per_month')
                ->default(30)
                ->after('payroll_default_base_shift_rate');
        });

        DB::table('system_settings')->update([
            'payroll_standard_shifts_per_month' => (int) config('psg.payroll.standard_shifts_per_month', 30),
        ]);
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('payroll_standard_shifts_per_month');
        });
    }
};
