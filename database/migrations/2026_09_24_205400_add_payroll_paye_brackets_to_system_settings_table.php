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
            $table->json('payroll_paye_brackets')
                ->nullable()
                ->after('payroll_use_progressive_paye');
        });

        $defaults = [
            'threshold_tax_free' => 335_000,
            'band_20_max' => 410_000,
            'band_25_max' => 485_000,
            'surtax_threshold' => 10_000_000,
            'band_25_base' => 15_000,
            'band_30_base' => 33_750,
            'rate_20' => 20,
            'rate_25' => 25,
            'rate_30' => 30,
            'rate_surtax' => 10,
            'label' => 'PAYE (URA resident monthly)',
        ];

        DB::table('system_settings')->update([
            'payroll_paye_brackets' => json_encode($defaults),
        ]);
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('payroll_paye_brackets');
        });
    }
};
