<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guard_asset_issuances', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('guard_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->string('issuance_type', 32);
            $table->string('status', 32)->default('active');
            $table->date('issued_at');
            $table->text('notes')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['guard_id', 'issued_at']);
            $table->index(['status', 'issued_at']);
        });

        Schema::create('guard_asset_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guard_asset_issuance_id')->constrained()->cascadeOnDelete();
            $table->string('asset_category', 32);
            $table->string('description')->nullable();
            $table->string('size', 32)->nullable();
            $table->string('serial_number', 64)->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedSmallInteger('quantity_returned')->default(0);
            $table->decimal('unit_value', 12, 2)->default(0);
            $table->decimal('recovery_amount', 12, 2)->default(0);
            $table->decimal('recovered_amount', 12, 2)->default(0);
            $table->decimal('monthly_recovery', 12, 2)->nullable();
            $table->string('status', 32)->default('issued');
            $table->timestamp('returned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['asset_category', 'status']);
        });

        Schema::create('guard_asset_recoveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guard_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guard_asset_line_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('original_amount', 12, 2);
            $table->decimal('balance_remaining', 12, 2);
            $table->decimal('monthly_installment', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['guard_id', 'is_active']);
        });

        Schema::table('payroll_deductions', function (Blueprint $table) {
            $table->foreignId('guard_asset_recovery_id')
                ->nullable()
                ->after('guard_advance_id')
                ->constrained('guard_asset_recoveries')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_deductions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guard_asset_recovery_id');
        });

        Schema::dropIfExists('guard_asset_recoveries');
        Schema::dropIfExists('guard_asset_lines');
        Schema::dropIfExists('guard_asset_issuances');
    }
};
