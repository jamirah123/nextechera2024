<?php

use App\Enums\ShiftStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            // NULL when cancelled/missed/etc. MySQL allows many NULLs in a UNIQUE column.
            $table->string('same_shift_slot', 80)->nullable()->after('status');
            $table->unique('same_shift_slot');
        });

        $blocking = [
            ShiftStatus::Scheduled->value,
            ShiftStatus::Confirmed->value,
            ShiftStatus::InProgress->value,
            ShiftStatus::Recorded->value,
            ShiftStatus::Completed->value,
        ];

        DB::table('shifts')
            ->whereIn('status', $blocking)
            ->orderBy('id')
            ->chunkById(200, function ($shifts): void {
                foreach ($shifts as $shift) {
                    $slot = $shift->guard_id.'|'
                        .Carbon::parse($shift->shift_date)->toDateString()
                        .'|'.$shift->period;

                    $conflict = DB::table('shifts')
                        ->where('same_shift_slot', $slot)
                        ->where('id', '!=', $shift->id)
                        ->exists();

                    if ($conflict) {
                        // Keep the earliest blocking row; leave later duplicates off the unique key.
                        continue;
                    }

                    DB::table('shifts')->where('id', $shift->id)->update(['same_shift_slot' => $slot]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropUnique(['same_shift_slot']);
            $table->dropColumn('same_shift_slot');
        });
    }
};
