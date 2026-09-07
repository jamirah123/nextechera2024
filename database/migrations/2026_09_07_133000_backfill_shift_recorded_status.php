<?php

use App\Enums\ShiftStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Posted / rostered duties that were still "scheduled" become Shift recorded (payable).
        DB::table('shifts')
            ->where('status', ShiftStatus::Scheduled->value)
            ->where(function ($query): void {
                $query->whereNotNull('deployment_id')
                    ->orWhere('notes', 'like', '%site posting%')
                    ->orWhere('notes', 'like', '%Shift recorded%')
                    ->orWhere('notes', 'like', '%Allocated from%')
                    ->orWhere('notes', 'like', '%duty roster%');
            })
            ->update([
                'status' => ShiftStatus::Recorded->value,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('shifts')
            ->where('status', ShiftStatus::Recorded->value)
            ->where(function ($query): void {
                $query->whereNotNull('deployment_id')
                    ->orWhere('notes', 'like', '%site posting%')
                    ->orWhere('notes', 'like', '%Shift recorded%')
                    ->orWhere('notes', 'like', '%Allocated from%')
                    ->orWhere('notes', 'like', '%duty roster%');
            })
            ->update([
                'status' => ShiftStatus::Scheduled->value,
                'updated_at' => now(),
            ]);
    }
};
