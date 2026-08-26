<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guard_id')->constrained('guards')->restrictOnDelete();
            $table->foreignId('site_id')->constrained('sites')->restrictOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('supervisors')->nullOnDelete();
            $table->string('shift_type', 30)->default('day')->index();
            $table->string('status', 30)->default('active')->index();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['site_id', 'is_current', 'status']);
            $table->index(['guard_id', 'is_current']);
            $table->index(['region_id', 'status']);
        });

        Schema::create('deployment_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guard_id')->constrained('guards')->cascadeOnDelete();
            $table->foreignId('from_deployment_id')->nullable()->constrained('deployments')->nullOnDelete();
            $table->foreignId('to_deployment_id')->nullable()->constrained('deployments')->nullOnDelete();
            $table->foreignId('from_site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('to_site_id')->constrained('sites')->restrictOnDelete();
            $table->string('reason', 191)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('transferred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('effective_at');
            $table->timestamps();

            $table->index(['guard_id', 'effective_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_transfers');
        Schema::dropIfExists('deployments');
    }
};
