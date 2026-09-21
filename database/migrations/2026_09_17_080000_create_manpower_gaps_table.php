<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manpower_gaps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->date('gap_date')->index();
            $table->string('period', 20)->index(); // day | night
            $table->unsignedInteger('required')->default(0);
            $table->unsignedInteger('permanent_deployed')->default(0);
            $table->unsignedInteger('original_shortage')->default(0);
            $table->unsignedInteger('overtime_covered')->default(0);
            $table->unsignedInteger('remaining_shortage')->default(0);
            $table->string('status', 30)->default('open')->index(); // open | partially_resolved | resolved | none
            $table->timestamp('resolved_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['site_id', 'gap_date', 'period']);
            $table->index(['gap_date', 'status']);
            $table->index(['site_id', 'status']);
        });

        Schema::table('deployments', function (Blueprint $table): void {
            $table->boolean('is_temporary')->default(false)->after('is_current')->index();
            $table->string('duty_type', 30)->nullable()->after('is_temporary')->index();
            $table->foreignId('manpower_gap_id')->nullable()->after('duty_type')
                ->constrained('manpower_gaps')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deployments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('manpower_gap_id');
            $table->dropColumn(['is_temporary', 'duty_type']);
        });

        Schema::dropIfExists('manpower_gaps');
    }
};
