<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guards', function (Blueprint $table) {
            $table->string('compensation_type', 16)
                ->default('shift')
                ->after('notes')
                ->index();
        });

        Schema::table('payroll_payslips', function (Blueprint $table) {
            $table->string('compensation_type', 16)
                ->default('shift')
                ->after('full_name');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_payslips', function (Blueprint $table) {
            $table->dropColumn('compensation_type');
        });

        Schema::table('guards', function (Blueprint $table) {
            $table->dropColumn('compensation_type');
        });
    }
};
