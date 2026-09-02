<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->string('company_bank_name', 120)->nullable()->after('invoice_due_days');
            $table->string('company_bank_account', 80)->nullable()->after('company_bank_name');
            $table->string('company_bank_branch', 120)->nullable()->after('company_bank_account');
            $table->text('invoice_payment_terms')->nullable()->after('company_bank_branch');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'company_bank_name',
                'company_bank_account',
                'company_bank_branch',
                'invoice_payment_terms',
            ]);
        });
    }
};
