<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->string('theme_primary', 7)->nullable()->after('logo_path');
            $table->string('theme_sidebar', 7)->nullable()->after('theme_primary');
            $table->string('favicon_path', 255)->nullable()->after('theme_sidebar');
            $table->text('email_footer_text')->nullable()->after('favicon_path');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'theme_primary',
                'theme_sidebar',
                'favicon_path',
                'email_footer_text',
            ]);
        });
    }
};
