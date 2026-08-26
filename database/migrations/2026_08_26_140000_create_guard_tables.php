<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guards', function (Blueprint $table) {
            $table->id();
            $table->string('employment_id', 50)->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('full_name', 191)->index();
            $table->string('gender', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('alternative_phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('national_id', 50)->nullable()->index();
            $table->date('date_employed')->nullable();
            $table->string('employment_status', 30)->default('active')->index();
            $table->string('rank_designation', 100)->nullable();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->foreignId('current_site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('current_supervisor_id')->nullable()->constrained('supervisors')->nullOnDelete();
            $table->string('operational_status', 40)->default('awaiting_deployment')->index();
            $table->string('emergency_contact_name', 191)->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['region_id', 'employment_status']);
            $table->index(['employment_status', 'operational_status']);
        });

        Schema::create('guard_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guard_id')->constrained('guards')->cascadeOnDelete();
            $table->string('status_type', 30);
            $table->string('previous_status', 40)->nullable();
            $table->string('new_status', 40);
            $table->string('reason', 191)->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('effective_at');
            $table->timestamps();

            $table->index(['guard_id', 'effective_at']);
            $table->index(['status_type', 'new_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guard_status_histories');
        Schema::dropIfExists('guards');
    }
};
