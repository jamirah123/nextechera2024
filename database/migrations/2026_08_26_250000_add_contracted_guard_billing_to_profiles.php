<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_profiles', function (Blueprint $table) {
            $table->unsignedSmallInteger('contracted_armed_guards')->default(0)->after('monthly_site_fee');
            $table->unsignedSmallInteger('contracted_unarmed_guards')->default(0)->after('contracted_armed_guards');
            $table->decimal('monthly_rate_per_armed_guard', 14, 2)->default(0)->after('contracted_unarmed_guards');
            $table->decimal('monthly_rate_per_unarmed_guard', 14, 2)->default(0)->after('monthly_rate_per_armed_guard');
            $table->decimal('monthly_cost_per_armed_guard', 14, 2)->default(0)->after('monthly_rate_per_unarmed_guard');
            $table->decimal('monthly_cost_per_unarmed_guard', 14, 2)->default(0)->after('monthly_cost_per_armed_guard');
        });
    }

    public function down(): void
    {
        Schema::table('billing_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'contracted_armed_guards',
                'contracted_unarmed_guards',
                'monthly_rate_per_armed_guard',
                'monthly_rate_per_unarmed_guard',
                'monthly_cost_per_armed_guard',
                'monthly_cost_per_unarmed_guard',
            ]);
        });
    }
};
