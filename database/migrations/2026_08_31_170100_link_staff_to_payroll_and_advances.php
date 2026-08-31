<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_payslips', function (Blueprint $table) {
            $table->dropForeign(['guard_id']);
        });

        Schema::table('payroll_payslips', function (Blueprint $table) {
            $table->unsignedBigInteger('guard_id')->nullable()->change();
            $table->foreignId('staff_id')->nullable()->after('guard_id')->constrained('staff')->cascadeOnDelete();
            $table->foreign('guard_id')->references('id')->on('guards')->cascadeOnDelete();
            $table->unique(['payroll_run_id', 'staff_id']);
        });

        Schema::table('guard_salary_advances', function (Blueprint $table) {
            $table->dropForeign(['guard_id']);
        });

        Schema::table('guard_salary_advances', function (Blueprint $table) {
            $table->unsignedBigInteger('guard_id')->nullable()->change();
            $table->foreignId('staff_id')->nullable()->after('guard_id')->constrained('staff')->cascadeOnDelete();
            $table->foreign('guard_id')->references('id')->on('guards')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guard_salary_advances', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
            $table->dropForeign(['guard_id']);
            $table->dropColumn('staff_id');
        });

        Schema::table('guard_salary_advances', function (Blueprint $table) {
            $table->unsignedBigInteger('guard_id')->nullable(false)->change();
            $table->foreign('guard_id')->references('id')->on('guards')->cascadeOnDelete();
        });

        Schema::table('payroll_payslips', function (Blueprint $table) {
            $table->dropUnique(['payroll_run_id', 'staff_id']);
            $table->dropForeign(['staff_id']);
            $table->dropForeign(['guard_id']);
            $table->dropColumn('staff_id');
        });

        Schema::table('payroll_payslips', function (Blueprint $table) {
            $table->unsignedBigInteger('guard_id')->nullable(false)->change();
            $table->foreign('guard_id')->references('id')->on('guards')->cascadeOnDelete();
        });
    }
};
