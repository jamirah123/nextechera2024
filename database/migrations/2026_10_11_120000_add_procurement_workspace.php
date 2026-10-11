<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->string('name', 160);
            $table->string('contact_person', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('delivery_address', 255)->nullable();
            $table->string('tin', 40)->nullable();
            $table->string('category', 40);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();

            $table->index(['is_active', 'category']);
            $table->index('name');
        });

        Schema::create('procurement_stock_items', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->string('name', 160);
            $table->string('category', 32);
            $table->string('unit', 24)->default('pcs');
            $table->unsignedInteger('quantity_on_hand')->default(0);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->string('location', 120)->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();

            $table->index(['is_active', 'category']);
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_id')->nullable()->after('reference')->index();
            $table->string('vat_treatment', 32)->default('standard')->after('currency');
            $table->string('attachment_path')->nullable()->after('notes');
            $table->text('cancellation_reason')->nullable()->after('posted_by');
            $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
            $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at')->index();
            $table->index(['supplier_id', 'supplier_invoice_no']);
        });

        Schema::table('guard_asset_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('stock_item_id')->nullable()->after('guard_asset_issuance_id')->index();
            $table->date('expected_return_on')->nullable()->after('returned_at');
        });
    }

    public function down(): void
    {
        Schema::table('guard_asset_lines', function (Blueprint $table) {
            $table->dropColumn(['stock_item_id', 'expected_return_on']);
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropColumn(['supplier_id', 'vat_treatment', 'attachment_path', 'cancellation_reason', 'cancelled_at', 'cancelled_by']);
        });

        Schema::dropIfExists('procurement_stock_items');
        Schema::dropIfExists('suppliers');
    }
};
