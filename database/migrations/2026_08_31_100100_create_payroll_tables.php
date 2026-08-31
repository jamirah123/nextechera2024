<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->default('draft')->index();
            $table->string('currency', 8)->default('UGX');
            $table->unsignedInteger('guard_count')->default(0);
            $table->decimal('gross_total', 14, 2)->default(0);
            $table->decimal('deductions_total', 14, 2)->default(0);
            $table->decimal('net_total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['period_year', 'period_month']);
        });

        Schema::create('payroll_payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guard_id')->constrained()->cascadeOnDelete();
            $table->string('employment_id', 32);
            $table->string('full_name');
            $table->unsignedSmallInteger('normal_shifts')->default(0);
            $table->unsignedSmallInteger('overtime_shifts')->default(0);
            $table->unsignedSmallInteger('relief_shifts')->default(0);
            $table->unsignedSmallInteger('replacement_shifts')->default(0);
            $table->unsignedSmallInteger('special_duty_shifts')->default(0);
            $table->unsignedSmallInteger('total_shifts')->default(0);
            $table->decimal('base_shift_rate', 12, 2)->default(0);
            $table->decimal('overtime_shift_rate', 12, 2)->default(0);
            $table->decimal('gross_pay', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('net_pay', 14, 2)->default(0);
            $table->string('bank_name', 120)->nullable();
            $table->string('bank_account', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'guard_id']);
        });

        Schema::create('payroll_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_payslip_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('label');
            $table->decimal('amount', 12, 2);
            $table->boolean('is_statutory')->default(false);
            $table->timestamps();
        });

        Schema::create('payroll_payslip_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_payslip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['payroll_payslip_id', 'shift_id']);
            $table->unique('shift_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_payslip_shifts');
        Schema::dropIfExists('payroll_deductions');
        Schema::dropIfExists('payroll_payslips');
        Schema::dropIfExists('payroll_runs');
    }
};
