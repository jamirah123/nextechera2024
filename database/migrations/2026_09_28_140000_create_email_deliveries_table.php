<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_log_id')->nullable()->constrained('audit_logs')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_email');
            $table->string('subject');
            $table->string('action');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('failure_reason')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['audit_log_id', 'recipient_email']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('system_settings', function (Blueprint $table) {
            $table->json('email_event_rules')->nullable()->after('notify_proactive_alerts');
            $table->boolean('email_include_sensitive_amounts')->default(false)->after('email_event_rules');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn(['email_event_rules', 'email_include_sensitive_amounts']);
        });

        Schema::dropIfExists('email_deliveries');
    }
};
