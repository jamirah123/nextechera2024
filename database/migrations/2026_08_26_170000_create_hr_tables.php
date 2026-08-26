<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guard_id')->constrained('guards')->restrictOnDelete();
            $table->string('leave_type', 40)->index();
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->date('expected_return_date')->nullable();
            $table->string('reason', 255)->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->text('notes')->nullable();
            $table->unsignedInteger('conflicting_shifts_count')->default(0);
            $table->json('conflict_snapshot')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['guard_id', 'status', 'start_date']);
        });

        Schema::create('absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guard_id')->constrained('guards')->restrictOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->date('absence_date')->index();
            $table->string('reason', 40)->index();
            $table->string('action_taken', 255)->nullable();
            $table->boolean('replacement_required')->default(false);
            $table->foreignId('replacement_guard_id')->nullable()->constrained('guards')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reported_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['guard_id', 'absence_date']);
            $table->index(['site_id', 'absence_date']);
        });

        Schema::create('desertions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guard_id')->constrained('guards')->restrictOnDelete();
            $table->date('last_known_duty_date')->nullable();
            $table->foreignId('last_known_site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->date('date_reported')->index();
            $table->text('circumstances')->nullable();
            $table->string('action_taken', 255)->nullable();
            $table->string('hr_status', 40)->default('reported')->index();
            $table->text('notes')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['guard_id', 'hr_status']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guard_id')->constrained('guards')->restrictOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->string('event_type', 40)->index();
            $table->string('source', 40)->default('manual')->index();
            $table->timestamp('occurred_at')->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('device_ref', 100)->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['guard_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('desertions');
        Schema::dropIfExists('absences');
        Schema::dropIfExists('leaves');
    }
};
