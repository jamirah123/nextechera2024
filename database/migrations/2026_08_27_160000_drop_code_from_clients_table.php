<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('clients', 'code')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('clients', 'code')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('name');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->unique('code');
        });
    }
};
