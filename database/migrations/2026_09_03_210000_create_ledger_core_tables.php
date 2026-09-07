<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->string('type', 20)->index();
            $table->string('system_role', 40)->nullable()->unique();
            $table->boolean('is_postable')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('gl_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('open')->index();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
        });

        Schema::create('gl_journals', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->date('journal_date')->index();
            $table->foreignId('period_id')->constrained('gl_periods')->restrictOnDelete();
            $table->string('source', 40)->index();
            $table->nullableMorphs('source_document');
            $table->string('description', 255);
            $table->string('status', 20)->default('posted')->index();
            $table->string('currency', 8)->default('UGX');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('gl_journals')->nullOnDelete();
            $table->timestamps();

            $table->index(['source', 'status']);
        });

        Schema::create('gl_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained('gl_journals')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('memo', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['account_id', 'journal_id']);
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gl_account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('bank_name', 120)->nullable();
            $table->string('account_number', 80)->nullable();
            $table->string('currency', 8)->default('UGX');
            $table->boolean('is_active')->default(true)->index();
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->date('transaction_date')->index();
            $table->string('description', 255);
            $table->string('external_reference', 120)->nullable()->index();
            $table->decimal('amount', 14, 2);
            $table->string('status', 20)->default('unmatched')->index();
            $table->foreignId('matched_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('matched_journal_id')->nullable()->constrained('gl_journals')->nullOnDelete();
            $table->timestamp('matched_at')->nullable();
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['bank_account_id', 'status']);
        });

        $now = now();
        $accounts = [
            ['code' => '1100', 'name' => 'Cash at bank', 'type' => 'asset', 'system_role' => 'cash_bank', 'description' => 'Primary operating bank account'],
            ['code' => '1200', 'name' => 'Accounts receivable', 'type' => 'asset', 'system_role' => 'accounts_receivable', 'description' => 'Client invoice balances'],
            ['code' => '2200', 'name' => 'VAT output', 'type' => 'liability', 'system_role' => 'vat_output', 'description' => 'VAT charged on invoices'],
            ['code' => '2300', 'name' => 'Payroll payable', 'type' => 'liability', 'system_role' => 'payroll_payable', 'description' => 'Net pay owed to guards and staff'],
            ['code' => '2400', 'name' => 'Payroll deductions clearing', 'type' => 'liability', 'system_role' => 'payroll_deductions', 'description' => 'PAYE, NSSF, advances and other deductions'],
            ['code' => '3100', 'name' => 'Retained earnings', 'type' => 'equity', 'system_role' => 'retained_earnings', 'description' => 'Accumulated earnings'],
            ['code' => '4100', 'name' => 'Security services revenue', 'type' => 'revenue', 'system_role' => 'revenue_services', 'description' => 'Client billing revenue'],
            ['code' => '5100', 'name' => 'Payroll expense', 'type' => 'expense', 'system_role' => 'payroll_expense', 'description' => 'Gross payroll cost'],
            ['code' => '5200', 'name' => 'Bank charges', 'type' => 'expense', 'system_role' => null, 'description' => 'Bank fees and charges'],
        ];

        foreach ($accounts as $account) {
            DB::table('gl_accounts')->insert([
                ...$account,
                'is_postable' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $cashAccountId = (int) DB::table('gl_accounts')->where('system_role', 'cash_bank')->value('id');
        DB::table('bank_accounts')->insert([
            'gl_account_id' => $cashAccountId,
            'name' => 'Operating account',
            'bank_name' => config('psg.company_bank_name') ?: 'Company bank',
            'account_number' => config('psg.company_bank_account'),
            'currency' => config('psg.currency', 'UGX'),
            'is_active' => true,
            'opening_balance' => 0,
            'notes' => 'Default bank account for collections and payroll disbursements.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $cursor = now()->startOfMonth()->subMonths(2);
        for ($i = 0; $i < 6; $i++) {
            $start = $cursor->copy()->addMonths($i)->startOfMonth();
            DB::table('gl_periods')->insert([
                'year' => (int) $start->year,
                'month' => (int) $start->month,
                'starts_on' => $start->toDateString(),
                'ends_on' => $start->copy()->endOfMonth()->toDateString(),
                'status' => 'open',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('gl_journal_lines');
        Schema::dropIfExists('gl_journals');
        Schema::dropIfExists('gl_periods');
        Schema::dropIfExists('gl_accounts');
    }
};
