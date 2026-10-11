<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('billing_profiles', 'vat_treatment')) {
            Schema::table('billing_profiles', function (Blueprint $table): void {
                $table->string('vat_treatment', 20)->default('standard')->after('cash_no_tax');
                $table->timestamp('approved_at')->nullable()->after('is_active');
                $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
                $table->index('approved_by');
            });

            DB::table('billing_profiles')
                ->where('is_active', true)
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('billing_profiles')->where('id', $row->id)->update([
                            'vat_treatment' => $row->vat_treatment ?: 'standard',
                            'approved_at' => $row->updated_at ?? $row->created_at,
                            'approved_by' => $row->updated_by ?? $row->created_by,
                        ]);
                    }
                });
        }

        if (! Schema::hasColumn('invoices', 'billing_profile_id')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->unsignedBigInteger('billing_profile_id')->nullable()->after('site_id');
                $table->index('billing_profile_id');
                $table->string('vat_treatment', 20)->nullable()->after('currency');
                $table->decimal('vat_rate', 8, 2)->nullable()->after('vat_treatment');
                $table->json('calculation_snapshot')->nullable()->after('notes');
                $table->json('issued_snapshot')->nullable()->after('calculation_snapshot');
            });
        }

        if (! Schema::hasTable('invoice_billed_shifts')) {
            Schema::create('invoice_billed_shifts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('invoice_id');
                $table->unsignedBigInteger('shift_id');
                $table->unique(['invoice_id', 'shift_id']);
                $table->index('shift_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_billed_shifts');

        if (Schema::hasColumn('invoices', 'billing_profile_id')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropIndex(['billing_profile_id']);
                $table->dropColumn([
                    'billing_profile_id',
                    'vat_treatment',
                    'vat_rate',
                    'calculation_snapshot',
                    'issued_snapshot',
                ]);
            });
        }

        if (Schema::hasColumn('billing_profiles', 'vat_treatment')) {
            Schema::table('billing_profiles', function (Blueprint $table): void {
                $table->dropIndex(['approved_by']);
                $table->dropColumn(['vat_treatment', 'approved_at', 'approved_by']);
            });
        }
    }
};
