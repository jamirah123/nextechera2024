<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guard_attachments', function (Blueprint $table) {
            $table->string('document_type', 40)->nullable()->after('label');
            $table->date('expires_at')->nullable()->after('document_type');
        });
    }

    public function down(): void
    {
        Schema::table('guard_attachments', function (Blueprint $table) {
            $table->dropColumn(['document_type', 'expires_at']);
        });
    }
};
