<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->unsignedInteger('required_day_armed_guards')->default(0)->after('required_day_guards');
            $table->unsignedInteger('required_day_unarmed_guards')->default(0)->after('required_day_armed_guards');
            $table->unsignedInteger('required_night_armed_guards')->default(0)->after('required_night_guards');
            $table->unsignedInteger('required_night_unarmed_guards')->default(0)->after('required_night_armed_guards');
        });

        Schema::table('site_manpower_requirements', function (Blueprint $table) {
            $table->unsignedInteger('required_day_armed')->default(0)->after('required_day');
            $table->unsignedInteger('required_day_unarmed')->default(0)->after('required_day_armed');
            $table->unsignedInteger('required_night_armed')->default(0)->after('required_night');
            $table->unsignedInteger('required_night_unarmed')->default(0)->after('required_night_armed');
        });

        Schema::table('billing_profiles', function (Blueprint $table) {
            $table->unsignedInteger('contracted_day_armed_guards')->default(0)->after('contracted_unarmed_guards');
            $table->unsignedInteger('contracted_day_unarmed_guards')->default(0)->after('contracted_day_armed_guards');
            $table->unsignedInteger('contracted_night_armed_guards')->default(0)->after('contracted_day_unarmed_guards');
            $table->unsignedInteger('contracted_night_unarmed_guards')->default(0)->after('contracted_night_armed_guards');
            $table->decimal('monthly_rate_per_armed_day_guard', 14, 2)->default(0)->after('monthly_rate_per_unarmed_guard');
            $table->decimal('monthly_rate_per_unarmed_day_guard', 14, 2)->default(0)->after('monthly_rate_per_armed_day_guard');
            $table->decimal('monthly_rate_per_armed_night_guard', 14, 2)->default(0)->after('monthly_rate_per_unarmed_day_guard');
            $table->decimal('monthly_rate_per_unarmed_night_guard', 14, 2)->default(0)->after('monthly_rate_per_armed_night_guard');
        });

        // Preserve existing day/night totals as unarmed until sites are reclassified.
        DB::table('sites')->orderBy('id')->chunkById(200, function ($sites): void {
            foreach ($sites as $site) {
                DB::table('sites')->where('id', $site->id)->update([
                    'required_day_armed_guards' => 0,
                    'required_day_unarmed_guards' => (int) $site->required_day_guards,
                    'required_night_armed_guards' => 0,
                    'required_night_unarmed_guards' => (int) $site->required_night_guards,
                ]);
            }
        });

        DB::table('site_manpower_requirements')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('site_manpower_requirements')->where('id', $row->id)->update([
                    'required_day_armed' => 0,
                    'required_day_unarmed' => (int) $row->required_day,
                    'required_night_armed' => 0,
                    'required_night_unarmed' => (int) $row->required_night,
                ]);
            }
        });

        // Billing previously mapped day→armed column and night→unarmed column.
        DB::table('billing_profiles')->orderBy('id')->chunkById(200, function ($profiles): void {
            foreach ($profiles as $profile) {
                DB::table('billing_profiles')->where('id', $profile->id)->update([
                    'contracted_day_armed_guards' => 0,
                    'contracted_day_unarmed_guards' => (int) $profile->contracted_armed_guards,
                    'contracted_night_armed_guards' => 0,
                    'contracted_night_unarmed_guards' => (int) $profile->contracted_unarmed_guards,
                    'monthly_rate_per_armed_day_guard' => 0,
                    'monthly_rate_per_unarmed_day_guard' => (float) $profile->monthly_rate_per_armed_guard,
                    'monthly_rate_per_armed_night_guard' => 0,
                    'monthly_rate_per_unarmed_night_guard' => (float) $profile->monthly_rate_per_unarmed_guard,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('billing_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'contracted_day_armed_guards',
                'contracted_day_unarmed_guards',
                'contracted_night_armed_guards',
                'contracted_night_unarmed_guards',
                'monthly_rate_per_armed_day_guard',
                'monthly_rate_per_unarmed_day_guard',
                'monthly_rate_per_armed_night_guard',
                'monthly_rate_per_unarmed_night_guard',
            ]);
        });

        Schema::table('site_manpower_requirements', function (Blueprint $table) {
            $table->dropColumn([
                'required_day_armed',
                'required_day_unarmed',
                'required_night_armed',
                'required_night_unarmed',
            ]);
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'required_day_armed_guards',
                'required_day_unarmed_guards',
                'required_night_armed_guards',
                'required_night_unarmed_guards',
            ]);
        });
    }
};
