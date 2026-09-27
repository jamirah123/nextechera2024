<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guard_salary_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guard_id')->constrained('guards')->cascadeOnDelete();
            $table->decimal('previous_salary', 12, 2)->nullable();
            $table->decimal('salary', 12, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('reason', 40);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['guard_id', 'effective_from']);
        });

        Schema::table('payroll_payslips', function (Blueprint $table) {
            $table->json('salary_breakdown')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_payslips', function (Blueprint $table) {
            $table->dropColumn('salary_breakdown');
        });

        Schema::dropIfExists('guard_salary_revisions');
    }
};
