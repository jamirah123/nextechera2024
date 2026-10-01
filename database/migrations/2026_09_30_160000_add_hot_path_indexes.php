<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->index('payment_date');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->index('issue_date');
        });

        Schema::table('notification_states', function (Blueprint $table): void {
            $table->index(['user_id', 'pinned_unread', 'dismissed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['payment_date']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex(['issue_date']);
        });

        Schema::table('notification_states', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'pinned_unread', 'dismissed_at']);
        });
    }
};
