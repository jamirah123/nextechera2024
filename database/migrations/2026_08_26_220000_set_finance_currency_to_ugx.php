<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('billing_profiles')->where('currency', 'TZS')->update(['currency' => 'UGX']);
        DB::table('invoices')->where('currency', 'TZS')->update(['currency' => 'UGX']);
    }

    public function down(): void
    {
        DB::table('billing_profiles')->where('currency', 'UGX')->update(['currency' => 'TZS']);
        DB::table('invoices')->where('currency', 'UGX')->update(['currency' => 'TZS']);
    }
};
