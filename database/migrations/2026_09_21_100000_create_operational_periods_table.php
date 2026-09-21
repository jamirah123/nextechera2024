<?php

use App\Support\Access\RolePermissionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_periods', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('open')->index();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
            $table->index(['starts_on', 'ends_on']);
        });

        // Seed a rolling window of open periods (3 behind, current, 3 ahead).
        $cursor = now()->copy()->startOfMonth()->subMonths(3);
        for ($i = 0; $i < 7; $i++) {
            DB::table('operational_periods')->insert([
                'year' => (int) $cursor->year,
                'month' => (int) $cursor->month,
                'starts_on' => $cursor->copy()->startOfMonth()->toDateString(),
                'ends_on' => $cursor->copy()->endOfMonth()->toDateString(),
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $cursor->addMonth();
        }

        if (Schema::hasTable('role_permissions') && DB::table('role_permissions')->count() > 0) {
            app(RolePermissionService::class)->mergeMissingPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_periods');
    }
};
