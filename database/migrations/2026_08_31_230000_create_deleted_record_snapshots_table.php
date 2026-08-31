<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deleted_record_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('record_type', 64);
            $table->string('model_class', 191);
            $table->unsignedBigInteger('record_id');
            $table->string('label');
            $table->json('attributes');
            $table->json('relations')->nullable();
            $table->boolean('was_soft_deleted')->default(true);
            $table->string('source_action', 120)->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('restored_at')->nullable();
            $table->foreignId('restored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['model_class', 'record_id']);
            $table->index('restored_at');
            $table->index('record_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deleted_record_snapshots');
    }
};
