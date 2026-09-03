<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_profiles', function (Blueprint $table) {
            $table->string('billing_mode', 32)->default('monthly')->after('currency');
            $table->boolean('cash_no_tax')->default(false)->after('billing_mode');
            $table->decimal('rate_per_armed_day_shift', 12, 2)->default(0)->after('rate_per_unarmed_shift');
            $table->decimal('rate_per_unarmed_day_shift', 12, 2)->default(0)->after('rate_per_armed_day_shift');
            $table->decimal('rate_per_armed_night_shift', 12, 2)->default(0)->after('rate_per_unarmed_day_shift');
            $table->decimal('rate_per_unarmed_night_shift', 12, 2)->default(0)->after('rate_per_armed_night_shift');
        });

        if (Schema::hasColumn('billing_profiles', 'rate_per_armed_shift')) {
            DB::table('billing_profiles')->orderBy('id')->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $armed = (float) ($row->rate_per_armed_shift ?? 0);
                    $unarmed = (float) ($row->rate_per_unarmed_shift ?? 0);

                    if ($armed <= 0 && $unarmed <= 0) {
                        continue;
                    }

                    DB::table('billing_profiles')->where('id', $row->id)->update([
                        'rate_per_armed_day_shift' => $armed,
                        'rate_per_unarmed_day_shift' => $unarmed,
                        'rate_per_armed_night_shift' => $armed,
                        'rate_per_unarmed_night_shift' => $unarmed,
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('billing_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'billing_mode',
                'cash_no_tax',
                'rate_per_armed_day_shift',
                'rate_per_unarmed_day_shift',
                'rate_per_armed_night_shift',
                'rate_per_unarmed_night_shift',
            ]);
        });
    }
};
