<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_stock_items', function (Blueprint $table) {
            $table->string('tracking_type', 24)->default('quantity')->after('category');
            $table->string('asset_tag', 64)->nullable()->unique()->after('reference');
            $table->string('serial_number', 64)->nullable()->unique()->after('asset_tag');
            $table->string('brand', 80)->nullable()->after('name');
            $table->string('model', 80)->nullable()->after('brand');
            $table->text('specifications')->nullable()->after('model');
            $table->date('acquisition_date')->nullable()->after('unit_cost');
            $table->string('acquisition_source', 32)->default('opening_balance')->after('acquisition_date');
            $table->string('purchase_reference', 64)->nullable()->after('acquisition_source');
            $table->date('warranty_starts_on')->nullable()->after('purchase_reference');
            $table->date('warranty_ends_on')->nullable()->after('warranty_starts_on');
            $table->string('condition', 24)->default('good')->after('warranty_ends_on');
            $table->string('operational_status', 24)->default('available')->after('condition');
            $table->unsignedBigInteger('region_id')->nullable()->index()->after('location');
            $table->unsignedBigInteger('site_id')->nullable()->index()->after('region_id');
            $table->string('attachment_path')->nullable()->after('notes');
            $table->unsignedInteger('quantity_issued')->default(0)->after('quantity_on_hand');
            $table->unsignedInteger('quantity_lost')->default(0)->after('quantity_issued');
            $table->unsignedInteger('quantity_damaged')->default(0)->after('quantity_lost');
            $table->index(['operational_status', 'tracking_type']);
        });

        Schema::create('procurement_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_item_id')->index();
            $table->string('movement_type', 32);
            $table->unsignedInteger('quantity')->default(0);
            $table->string('direction', 8);
            $table->unsignedInteger('balance_after')->default(0);
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('guard_asset_line_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('guard_asset_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('transferred_from_line_id')->nullable()->index()->after('stock_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('guard_asset_lines', function (Blueprint $table) {
            $table->dropColumn('transferred_from_line_id');
        });

        Schema::dropIfExists('procurement_stock_movements');

        Schema::table('procurement_stock_items', function (Blueprint $table) {
            $table->dropUnique(['asset_tag']);
            $table->dropUnique(['serial_number']);
            $table->dropColumn([
                'tracking_type', 'asset_tag', 'serial_number', 'brand', 'model', 'specifications',
                'acquisition_date', 'acquisition_source', 'purchase_reference', 'warranty_starts_on',
                'warranty_ends_on', 'condition', 'operational_status', 'region_id', 'site_id',
                'attachment_path', 'quantity_issued', 'quantity_lost', 'quantity_damaged',
            ]);
        });
    }
};
