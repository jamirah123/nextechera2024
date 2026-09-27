<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('job_grade', 40)->nullable();
        });

        Schema::create('staff_salary_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->decimal('previous_salary', 12, 2)->nullable();
            $table->decimal('salary', 12, 2);
            $table->string('previous_job_title', 100)->nullable();
            $table->string('job_title', 100)->nullable();
            $table->string('previous_grade', 40)->nullable();
            $table->string('grade', 40)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('change_type', 40);
            $table->string('reason', 500)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['staff_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_salary_revisions');

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('job_grade');
        });
    }
};
