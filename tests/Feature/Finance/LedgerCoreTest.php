<?php

namespace Tests\Feature\Finance;

use App\Enums\BankStatementLineStatus;
use App\Enums\GlJournalSource;
use App\Enums\GlJournalStatus;
use App\Enums\GlPeriodStatus;
use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\Client;
use App\Models\GlAccount;
use App\Models\GlJournal;
use App\Models\GlPeriod;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\Ledger\BankReconciliationService;
use App\Services\Finance\Ledger\GlPeriodService;
use App\Services\Finance\Ledger\VatPackService;
use App\Services\Finance\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_chart_of_accounts_and_ledger_hub_are_available(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();

        $this->assertDatabaseHas('gl_accounts', ['system_role' => 'accounts_receivable']);
        $this->assertDatabaseHas('bank_accounts', ['name' => 'Operating account']);

        $this->actingAs($finance)
            ->get(route('ledger.index'))
            ->assertOk()
            ->assertSee('General ledger')
            ->assertSee('Chart of accounts');

        $this->actingAs($finance)
            ->get(route('ledger.accounts.index'))
            ->assertOk()
            ->assertSee('1100')
            ->assertSee('Accounts receivable');
    }

    public function test_issuing_invoice_and_recording_payment_posts_balanced_journals(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $this->actingAs($finance);
        $client = Client::factory()->create();

        $invoice = Invoice::query()->create([
            'reference' => 'INV-LEDGER-001',
            'client_id' => $client->id,
            'status' => InvoiceStatus::Draft,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'due_date' => now()->addDays(14),
            'currency' => 'UGX',
            'subtotal' => 0,
            'tax_amount' => 18000,
            'total' => 0,
            'amount_paid' => 0,
            'balance' => 0,
        ]);

        InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Armed posts',
            'quantity' => 1,
            'unit_price' => 100000,
            'line_total' => 100000,
            'sort_order' => 0,
        ]);

        $issued = app(InvoiceService::class)->issue($invoice->fresh('lines'));

        $journal = GlJournal::query()
            ->where('source', GlJournalSource::Invoice->value)
            ->where('source_document_id', $issued->id)
            ->first();

        $this->assertNotNull($journal);
        $this->assertTrue($journal->isBalanced());
        $this->assertSame(GlJournalStatus::Posted, $journal->status);
        $this->assertEquals(118000.0, $journal->debitTotal());

        $payment = app(PaymentService::class)->record([
            'invoice_id' => $issued->id,
            'amount' => 118000,
            'payment_date' => now()->toDateString(),
        ]);

        $paymentJournal = GlJournal::query()
            ->where('source', GlJournalSource::Payment->value)
            ->where('source_document_id', $payment->id)
            ->first();

        $this->assertNotNull($paymentJournal);
        $this->assertTrue($paymentJournal->isBalanced());
        $this->assertEquals(118000.0, $paymentJournal->debitTotal());
    }

    public function test_vat_pack_and_period_close_block_posting(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $this->actingAs($finance);
        $client = Client::factory()->create();
        $periodService = app(GlPeriodService::class);
        $period = $periodService->ensureForDate(now());

        $invoice = Invoice::query()->create([
            'reference' => 'INV-VAT-001',
            'client_id' => $client->id,
            'status' => InvoiceStatus::Issued,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'issue_date' => now(),
            'due_date' => now()->addDays(14),
            'currency' => 'UGX',
            'subtotal' => 100000,
            'tax_amount' => 18000,
            'total' => 118000,
            'amount_paid' => 0,
            'balance' => 118000,
        ]);

        $pack = app(VatPackService::class)->returnForPeriod($period);
        $this->assertEquals(18000.0, $pack['output_vat']);
        $this->assertEquals(100000.0, $pack['taxable_sales']);

        $this->actingAs($finance)
            ->get(route('ledger.vat.index', ['period_id' => $period->id]))
            ->assertOk()
            ->assertSee('INV-VAT-001')
            ->assertSee('Output VAT');

        $this->actingAs($finance)
            ->post(route('ledger.periods.close', $period))
            ->assertRedirect();

        $this->assertSame(GlPeriodStatus::Closed, $period->fresh()->status);

        $draft = Invoice::query()->create([
            'reference' => 'INV-VAT-002',
            'client_id' => $client->id,
            'status' => InvoiceStatus::Draft,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'due_date' => now()->addDays(14),
            'currency' => 'UGX',
            'subtotal' => 0,
            'tax_amount' => 0,
            'total' => 0,
            'amount_paid' => 0,
            'balance' => 0,
        ]);

        InvoiceLine::query()->create([
            'invoice_id' => $draft->id,
            'description' => 'Blocked by close',
            'quantity' => 1,
            'unit_price' => 50000,
            'line_total' => 50000,
            'sort_order' => 0,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is closed');
        app(InvoiceService::class)->issue($draft->fresh('lines'));
    }

    public function test_bank_statement_line_can_be_matched_to_payment(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $client = Client::factory()->create();
        $account = BankAccount::query()->firstOrFail();

        $invoice = Invoice::query()->create([
            'reference' => 'INV-BANK-001',
            'client_id' => $client->id,
            'status' => InvoiceStatus::Issued,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'issue_date' => now(),
            'due_date' => now()->addDays(14),
            'currency' => 'UGX',
            'subtotal' => 50000,
            'tax_amount' => 0,
            'total' => 50000,
            'amount_paid' => 0,
            'balance' => 50000,
        ]);

        $payment = Payment::query()->create([
            'reference' => 'PAY-BANK-001',
            'invoice_id' => $invoice->id,
            'client_id' => $client->id,
            'amount' => 50000,
            'payment_date' => now()->toDateString(),
            'method' => 'bank_transfer',
            'recorded_by' => $finance->id,
        ]);

        $this->actingAs($finance)
            ->post(route('ledger.bank.lines.store', $account), [
                'transaction_date' => now()->toDateString(),
                'description' => 'Client receipt',
                'amount' => 50000,
                'external_reference' => 'PAY-BANK-001',
            ])
            ->assertRedirect();

        $line = BankStatementLine::query()->firstOrFail();

        app(BankReconciliationService::class)->matchToPayment($line, $payment, $finance);

        $this->assertSame(BankStatementLineStatus::Matched, $line->fresh()->status);
        $this->assertSame($payment->id, $line->fresh()->matched_payment_id);

        $this->actingAs($finance)
            ->get(route('ledger.bank.show', $account))
            ->assertOk()
            ->assertSee('Client receipt')
            ->assertSee('PAY-BANK-001');
    }
}
