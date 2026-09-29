<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_states', function (Blueprint $table) {
            $table->string('priority', 20)->nullable()->after('audit_log_id');
            $table->string('delivery_status', 20)->default('delivered')->after('priority');
            $table->dateTime('delivered_at')->nullable()->after('delivery_status');
        });
    }

    public function down(): void
    {
        Schema::table('notification_states', function (Blueprint $table) {
            $table->dropColumn(['priority', 'delivery_status', 'delivered_at']);
        });
    }
};
