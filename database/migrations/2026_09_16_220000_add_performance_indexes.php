<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            $table->index('full_name');
        });

        Schema::table('sites', function (Blueprint $table): void {
            $table->index('name');
        });

        Schema::table('deployments', function (Blueprint $table): void {
            $table->index('start_date');
            $table->index('end_date');
            $table->index(['site_id', 'start_date', 'end_date']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            $table->dropIndex(['full_name']);
        });

        Schema::table('sites', function (Blueprint $table): void {
            $table->dropIndex(['name']);
        });

        Schema::table('deployments', function (Blueprint $table): void {
            $table->dropIndex(['start_date']);
            $table->dropIndex(['end_date']);
            $table->dropIndex(['site_id', 'start_date', 'end_date']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex(['status', 'due_date']);
        });
    }
};
