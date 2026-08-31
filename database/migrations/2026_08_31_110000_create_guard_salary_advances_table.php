<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guard_salary_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guard_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('original_amount', 12, 2);
            $table->decimal('balance_remaining', 12, 2);
            $table->decimal('monthly_installment', 12, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('payroll_deductions', function (Blueprint $table) {
            $table->foreignId('guard_advance_id')->nullable()->after('is_statutory')->constrained('guard_salary_advances')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_deductions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guard_advance_id');
        });

        Schema::dropIfExists('guard_salary_advances');
    }
};
