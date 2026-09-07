<?php

namespace Tests\Feature\Finance;

use App\Enums\GlJournalSource;
use App\Enums\GlJournalStatus;
use App\Enums\PurchaseInvoiceStatus;
use App\Enums\UserRole;
use App\Models\BankAccount;
use App\Models\GlAccount;
use App\Models\GlJournal;
use App\Models\User;
use App\Services\Finance\Ledger\ChartOfAccountsService;
use App\Services\Finance\Ledger\GlPeriodService;
use App\Services\Finance\Ledger\LedgerPostingService;
use App\Services\Finance\Ledger\LedgerReportService;
use App\Services\Finance\Ledger\PurchaseInvoiceService;
use App\Services\Finance\Ledger\VatPackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerFollowUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_manager_can_post_a_manual_journal(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $coa = app(ChartOfAccountsService::class);

        $this->actingAs($finance)
            ->get(route('ledger.journals.create'))
            ->assertOk()
            ->assertSee('Manual journal');

        $this->actingAs($finance)
            ->post(route('ledger.journals.store'), [
                'journal_date' => now()->toDateString(),
                'description' => 'Bank charges September',
                'lines' => [
                    ['account_id' => GlAccount::query()->where('code', '5200')->value('id'), 'debit' => 5000, 'credit' => 0, 'memo' => 'Charges'],
                    ['account_id' => $coa->cashBank()->id, 'debit' => 0, 'credit' => 5000, 'memo' => 'Bank'],
                ],
            ])
            ->assertRedirect();

        $journal = GlJournal::query()->where('source', GlJournalSource::Manual->value)->first();
        $this->assertNotNull($journal);
        $this->assertTrue($journal->isBalanced());
        $this->assertSame(GlJournalStatus::Posted, $journal->status);
        $this->assertEquals(5000.0, $journal->debitTotal());
    }

    public function test_additional_bank_account_can_be_created(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();

        $this->actingAs($finance)
            ->post(route('ledger.bank.store'), [
                'name' => 'Stanbic collections',
                'bank_name' => 'Stanbic',
                'account_number' => '9030011',
                'opening_balance' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bank_accounts', [
            'name' => 'Stanbic collections',
            'account_number' => '9030011',
        ]);

        $this->assertGreaterThan(1, BankAccount::query()->count());
        $this->assertDatabaseHas('gl_accounts', ['name' => 'Bank · Stanbic collections']);
    }

    public function test_posted_purchase_creates_input_vat_and_updates_vat_pack(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $this->actingAs($finance);

        $coa = app(ChartOfAccountsService::class);
        $service = app(PurchaseInvoiceService::class);

        $bill = $service->createDraft([
            'supplier_name' => 'Kampala Uniforms Ltd',
            'bill_date' => now()->toDateString(),
            'subtotal' => 100000,
            'tax_amount' => 18000,
            'expense_account_id' => $coa->operatingExpense()->id,
            'description' => 'Guard uniforms',
        ], $finance);

        $posted = $service->post($bill, $finance);

        $this->assertSame(PurchaseInvoiceStatus::Posted, $posted->status);

        $journal = GlJournal::query()
            ->where('source', GlJournalSource::Purchase->value)
            ->where('source_document_id', $posted->id)
            ->first();

        $this->assertNotNull($journal);
        $this->assertTrue($journal->isBalanced());
        $this->assertEquals(118000.0, $journal->debitTotal());

        $period = app(GlPeriodService::class)->ensureForDate(now());
        $pack = app(VatPackService::class)->returnForPeriod($period);

        $this->assertEquals(18000.0, $pack['input_vat']);
        $this->assertEquals(100000.0, $pack['taxable_purchases']);

        $this->actingAs($finance)
            ->get(route('ledger.vat.index', ['period_id' => $period->id]))
            ->assertOk()
            ->assertSee('Input VAT')
            ->assertSee('Kampala Uniforms Ltd');
    }

    public function test_trial_balance_and_profit_and_loss_include_posted_activity(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $this->actingAs($finance);
        $coa = app(ChartOfAccountsService::class);

        app(LedgerPostingService::class)->post(
            source: GlJournalSource::Manual,
            document: null,
            journalDate: now()->toDateString(),
            description: 'Recognise training revenue',
            lines: [
                ['account' => $coa->accountsReceivable(), 'debit' => 200000, 'credit' => 0],
                ['account' => $coa->revenueServices(), 'debit' => 0, 'credit' => 200000],
            ],
            actor: $finance,
        );

        $period = app(GlPeriodService::class)->ensureForDate(now());
        $tb = app(LedgerReportService::class)->trialBalance($period);
        $pnl = app(LedgerReportService::class)->profitAndLoss($period);

        $this->assertEqualsWithDelta($tb['debit_total'], $tb['credit_total'], 0.009);
        $this->assertGreaterThan(0, $tb['debit_total']);
        $this->assertEquals(200000.0, $pnl['revenue_total']);

        $this->actingAs($finance)
            ->get(route('ledger.reports.trial-balance', ['period_id' => $period->id]))
            ->assertOk()
            ->assertSee('Trial balance')
            ->assertSee('Accounts receivable');

        $this->actingAs($finance)
            ->get(route('ledger.reports.profit-loss', ['period_id' => $period->id]))
            ->assertOk()
            ->assertSee('Profit and loss')
            ->assertSee('Security services revenue');
    }
}
