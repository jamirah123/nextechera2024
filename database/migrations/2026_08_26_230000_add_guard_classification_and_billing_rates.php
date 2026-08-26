<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->string('guard_classification', 20)->default('unarmed')->after('shift_type')->index();
        });

        Schema::table('billing_profiles', function (Blueprint $table) {
            $table->decimal('rate_per_armed_shift', 14, 2)->default(0)->after('monthly_site_fee');
            $table->decimal('rate_per_unarmed_shift', 14, 2)->default(0)->after('rate_per_armed_shift');
            $table->decimal('cost_per_armed_shift', 14, 2)->default(0)->after('rate_per_guard_shift');
            $table->decimal('cost_per_unarmed_shift', 14, 2)->default(0)->after('cost_per_armed_shift');
        });

        DB::table('billing_profiles')->update([
            'rate_per_armed_shift' => DB::raw('rate_per_guard_shift'),
            'rate_per_unarmed_shift' => DB::raw('rate_per_guard_shift'),
            'cost_per_armed_shift' => DB::raw('cost_per_guard_shift'),
            'cost_per_unarmed_shift' => DB::raw('cost_per_guard_shift'),
        ]);
    }

    public function down(): void
    {
        Schema::table('billing_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'rate_per_armed_shift',
                'rate_per_unarmed_shift',
                'cost_per_armed_shift',
                'cost_per_unarmed_shift',
            ]);
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn('guard_classification');
        });
    }
};
