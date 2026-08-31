<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guards', function (Blueprint $table) {
            $table->date('employment_end_date')->nullable()->after('date_employed');
            $table->string('email', 120)->nullable()->after('phone');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->string('email', 120)->nullable()->after('phone');
        });

        Schema::table('payroll_payslips', function (Blueprint $table) {
            $table->string('nssf_number', 40)->nullable()->after('bank_account');
            $table->string('tin_number', 40)->nullable()->after('nssf_number');
            $table->string('payroll_email', 120)->nullable()->after('tin_number');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_payslips', function (Blueprint $table) {
            $table->dropColumn(['nssf_number', 'tin_number', 'payroll_email']);
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('email');
        });

        Schema::table('guards', function (Blueprint $table) {
            $table->dropColumn(['employment_end_date', 'email']);
        });
    }
};
