<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guards', function (Blueprint $table) {
            $table->decimal('base_shift_rate', 12, 2)->default(0)->after('notes');
            $table->decimal('overtime_shift_rate', 12, 2)->nullable()->after('base_shift_rate');
            $table->string('bank_name', 120)->nullable()->after('overtime_shift_rate');
            $table->string('bank_account', 64)->nullable()->after('bank_name');
            $table->string('nssf_number', 40)->nullable()->after('bank_account');
        });
    }

    public function down(): void
    {
        Schema::table('guards', function (Blueprint $table) {
            $table->dropColumn([
                'base_shift_rate',
                'overtime_shift_rate',
                'bank_name',
                'bank_account',
                'nssf_number',
            ]);
        });
    }
};
