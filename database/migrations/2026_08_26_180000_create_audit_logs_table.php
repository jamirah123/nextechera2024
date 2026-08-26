<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 64);
            $table->string('category', 32)->default('system');
            $table->string('severity', 16)->default('info');
            $table->string('summary', 255);
            $table->nullableMorphs('subject');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 191)->nullable();
            $table->string('actor_role', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('context')->nullable();
            $table->boolean('is_override')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
            $table->index(['category', 'created_at']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['is_override', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
