<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('report_key', 40);
            $table->string('title');
            $table->string('period_label');
            $table->json('filters')->nullable();
            $table->text('search_text');
            $table->string('filename');
            $table->string('storage_path');
            $table->unsignedInteger('row_count')->default(0);
            $table->timestamps();

            $table->index(['report_key', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_archives');
    }
};
