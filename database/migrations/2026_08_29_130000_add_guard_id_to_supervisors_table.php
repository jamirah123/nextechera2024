<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supervisors', function (Blueprint $table) {
            $table->foreignId('guard_id')
                ->nullable()
                ->unique()
                ->after('region_id')
                ->constrained('guards')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supervisors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guard_id');
        });
    }
};
