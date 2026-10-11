<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_misconduct_charges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('guard_id')->nullable()->index();
            $table->unsignedBigInteger('staff_id')->nullable()->index();
            $table->string('reason', 160);
            $table->date('incident_on')->nullable();
            $table->decimal('original_amount', 12, 2);
            $table->decimal('balance_remaining', 12, 2);
            $table->decimal('monthly_installment', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('payroll_deductions', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_charge_id')->nullable()->after('guard_asset_recovery_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_deductions', function (Blueprint $table) {
            $table->dropIndex(['employee_charge_id']);
            $table->dropColumn('employee_charge_id');
        });

        Schema::dropIfExists('employee_misconduct_charges');
    }
};
