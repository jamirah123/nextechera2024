<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_recurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guard_id')->constrained('guards')->restrictOnDelete();
            $table->foreignId('site_id')->constrained('sites')->restrictOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('supervisors')->nullOnDelete();
            $table->string('shift_type', 30)->default('normal');
            $table->string('period', 20)->default('day');
            $table->time('start_time');
            $table->time('end_time');
            $table->json('days_of_week');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['guard_id', 'is_active']);
            $table->index(['site_id', 'is_active']);
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('guard_id')->constrained('guards')->restrictOnDelete();
            $table->foreignId('site_id')->constrained('sites')->restrictOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('supervisors')->nullOnDelete();
            $table->foreignId('deployment_id')->nullable()->constrained('deployments')->nullOnDelete();
            $table->foreignId('recurrence_id')->nullable()->constrained('shift_recurrences')->nullOnDelete();
            $table->foreignId('replaced_shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->date('shift_date')->index();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->index();
            $table->string('period', 20)->default('day')->index();
            $table->string('shift_type', 30)->default('normal')->index();
            $table->string('status', 30)->default('scheduled')->index();
            $table->boolean('is_overnight')->default(false);
            $table->text('notes')->nullable();
            $table->boolean('override_used')->default(false);
            $table->string('override_reason', 255)->nullable();
            $table->foreignId('override_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('override_at')->nullable();
            $table->json('validation_snapshot')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['site_id', 'shift_date', 'status']);
            $table->index(['guard_id', 'shift_date', 'status']);
            $table->index(['region_id', 'shift_date']);
            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('shift_recurrences');
    }
};
