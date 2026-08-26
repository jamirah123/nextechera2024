<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_replacements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('original_shift_id')->constrained('shifts')->restrictOnDelete();
            $table->foreignId('original_guard_id')->constrained('guards')->restrictOnDelete();
            $table->foreignId('replacement_guard_id')->constrained('guards')->restrictOnDelete();
            $table->foreignId('replacement_shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->string('reason', 40);
            $table->text('notes')->nullable();
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replaced_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('original_shift_id');
            $table->index(['replacement_guard_id', 'replaced_at']);
            $table->index(['site_id', 'replaced_at']);
            $table->index('reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_replacements');
    }
};
