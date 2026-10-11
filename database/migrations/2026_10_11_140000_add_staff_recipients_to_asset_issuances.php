<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guard_asset_issuances', function (Blueprint $table) {
            $table->string('recipient_type', 16)->default('guard')->after('reference');
            $table->unsignedBigInteger('staff_id')->nullable()->after('guard_id')->index();
        });

        try {
            Schema::table('guard_asset_issuances', function (Blueprint $table) {
                $table->dropForeign(['guard_id']);
            });
        } catch (Throwable) {
            // Local MyISAM tables do not carry this foreign key.
        }

        Schema::table('guard_asset_issuances', function (Blueprint $table) {
            $table->unsignedBigInteger('guard_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('guard_asset_issuances', function (Blueprint $table) {
            $table->dropIndex(['staff_id']);
            $table->dropColumn(['recipient_type', 'staff_id']);
        });
    }
};
