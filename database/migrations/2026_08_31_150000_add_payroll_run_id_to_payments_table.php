<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('payroll_run_id')
                ->nullable()
                ->after('reference')
                ->constrained('payroll_runs')
                ->nullOnDelete();

            $table->unique('payroll_run_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->change();
            $table->foreignId('client_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payroll_run_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable(false)->change();
            $table->foreignId('client_id')->nullable(false)->change();
        });
    }
};
