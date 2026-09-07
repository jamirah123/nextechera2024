<?php

use App\Enums\GuardClassification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guards', function (Blueprint $table): void {
            $table->string('guard_classification', 20)
                ->default(GuardClassification::Unarmed->value)
                ->after('rank_designation');
        });
    }

    public function down(): void
    {
        Schema::table('guards', function (Blueprint $table): void {
            $table->dropColumn('guard_classification');
        });
    }
};
