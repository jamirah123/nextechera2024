<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->string('tagline', 191)->nullable()->after('company_name');
            $table->string('system_subtitle', 120)->default('Operations System')->after('tagline');
            $table->string('company_short_name', 12)->nullable()->after('system_subtitle');
            $table->string('logo_path', 255)->nullable()->after('company_short_name');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'tagline',
                'system_subtitle',
                'company_short_name',
                'logo_path',
            ]);
        });
    }
};
