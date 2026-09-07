<?php

use App\Enums\GlAccountType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $this->ensureAccount('2100', 'Accounts payable', GlAccountType::Liability->value, 'accounts_payable', 'Supplier bills awaiting payment');
        $this->ensureAccount('2210', 'VAT input', GlAccountType::Liability->value, 'vat_input', 'Recoverable VAT on purchases');
        $this->ensureAccount('5300', 'Operating expenses', GlAccountType::Expense->value, 'operating_expense', 'Default expense account for supplier bills');

        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('supplier_name', 160);
            $table->string('supplier_tin', 40)->nullable();
            $table->string('supplier_invoice_no', 80)->nullable();
            $table->date('bill_date')->index();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->string('currency', 8)->default('UGX');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->foreignId('expense_account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices');
    }

    private function ensureAccount(string $code, string $name, string $type, string $role, string $description): void
    {
        if (DB::table('gl_accounts')->where('system_role', $role)->exists()) {
            return;
        }

        if (DB::table('gl_accounts')->where('code', $code)->exists()) {
            DB::table('gl_accounts')->where('code', $code)->update([
                'system_role' => $role,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('gl_accounts')->insert([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'system_role' => $role,
            'is_postable' => true,
            'is_active' => true,
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
