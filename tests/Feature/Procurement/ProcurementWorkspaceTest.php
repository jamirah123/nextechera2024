<?php

namespace Tests\Feature\Procurement;

use App\Enums\AssetCategory;
use App\Enums\AssetIssuanceType;
use App\Enums\AssetLineStatus;
use App\Enums\GlJournalSource;
use App\Enums\PurchaseInvoiceStatus;
use App\Enums\SupplierCategory;
use App\Enums\UserRole;
use App\Enums\VatTreatment;
use App\Models\GlJournal;
use App\Models\Guard;
use App\Models\NotificationState;
use App\Models\ProcurementStockItem;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Finance\Ledger\ChartOfAccountsService;
use App\Services\Finance\Ledger\GlPeriodService;
use App\Services\Finance\Ledger\PurchaseInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_procurement_officer_sees_the_workspace_and_cannot_open_finance_or_hr_controls(): void
    {
        $officer = User::factory()->role(UserRole::ProcurementOfficer)->create();

        $this->actingAs($officer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Procurement Dashboard')
            ->assertSee('Draft bills')
            ->assertSee('On hand')
            ->assertDontSee('Outstanding')
            ->assertSee('Suppliers');

        $this->actingAs($officer)->get(route('invoices.index'))->assertForbidden();
        $this->actingAs($officer)->get(route('payments.index'))->assertForbidden();
        $this->actingAs($officer)->get(route('payroll.index'))->assertForbidden();
        $this->actingAs($officer)->get(route('ledger.index'))->assertRedirect(route('ledger.purchases.index'));
        $this->actingAs($officer)->get(route('ledger.journals.index'))->assertForbidden();
        $this->actingAs($officer)->get(route('guards.create'))->assertForbidden();
    }

    public function test_supplier_directory_rejects_duplicate_names_and_keeps_bill_snapshots(): void
    {
        $officer = User::factory()->role(UserRole::ProcurementOfficer)->create();

        $this->actingAs($officer)
            ->post(route('procurement.suppliers.store'), [
                'name' => 'Kampala Uniforms Ltd',
                'category' => SupplierCategory::Uniforms->value,
                'tin' => '100200300',
                'is_active' => 1,
            ])
            ->assertRedirect();

        $supplier = Supplier::query()->first();
        $this->assertNotNull($supplier);
        $this->assertMatchesRegularExpression('/^SUP-\d{4}$/', $supplier->reference);

        $this->actingAs($officer)
            ->post(route('procurement.suppliers.store'), [
                'name' => 'kampala uniforms ltd',
                'category' => SupplierCategory::Uniforms->value,
                'is_active' => 1,
            ])
            ->assertSessionHasErrors('name');

        $bill = $this->draft($officer, [
            'supplier_id' => $supplier->id,
            'supplier_name' => 'Ignored',
        ]);

        $supplier->update(['name' => 'Kampala Uniforms Renamed']);

        $this->assertSame('Kampala Uniforms Ltd', $bill->fresh()->supplier_name);
    }

    public function test_draft_bills_post_a_balanced_journal_and_cannot_be_posted_twice(): void
    {
        $officer = User::factory()->role(UserRole::ProcurementOfficer)->create();
        $expense = app(ChartOfAccountsService::class)->operatingExpense();

        $first = $this->draft($officer, ['supplier_invoice_no' => 'SUP-INV-1']);
        $second = $this->draft($officer, ['supplier_name' => 'Second Supplier', 'supplier_invoice_no' => 'SUP-INV-2']);

        $this->assertSame('PINV-'.now()->format('Ym').'-0001', $first->reference);
        $this->assertSame('PINV-'.now()->format('Ym').'-0002', $second->reference);

        $posted = app(PurchaseInvoiceService::class)->post($first, $officer);
        $journal = GlJournal::query()->where('source', GlJournalSource::Purchase->value)->where('source_document_id', $posted->id)->first();

        $this->assertNotNull($journal);
        $this->assertTrue($journal->fresh('lines')->isBalanced());
        $this->assertEquals(118000.0, $journal->debitTotal());

        $this->expectException(\InvalidArgumentException::class);
        app(PurchaseInvoiceService::class)->post($posted->fresh(), $officer);
    }

    public function test_exempt_purchases_cannot_claim_input_vat_and_posted_bills_reverse_once(): void
    {
        $officer = User::factory()->role(UserRole::ProcurementOfficer)->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->draft($officer, [
            'vat_treatment' => VatTreatment::Exempt->value,
            'tax_amount' => 18000,
            'supplier_tin' => '100200300',
        ]);
    }

    public function test_cancelling_a_posted_bill_records_one_reversal(): void
    {
        $officer = User::factory()->role(UserRole::ProcurementOfficer)->create();
        $bill = app(PurchaseInvoiceService::class)->post($this->draft($officer), $officer);

        $cancelled = app(PurchaseInvoiceService::class)->cancel($bill, $officer, 'Supplier invoice was duplicated.');

        $this->assertSame(PurchaseInvoiceStatus::Cancelled, $cancelled->status);
        $this->assertSame('Supplier invoice was duplicated.', $cancelled->cancellation_reason);
        $this->assertSame(2, GlJournal::query()->where('source_document_id', $bill->id)->count());

        $this->expectException(\InvalidArgumentException::class);
        app(PurchaseInvoiceService::class)->cancel($cancelled->fresh(), $officer, 'Again');
    }

    public function test_closed_period_rolls_back_a_post_attempt(): void
    {
        $officer = User::factory()->role(UserRole::ProcurementOfficer)->create();
        $bill = $this->draft($officer);
        $period = app(GlPeriodService::class)->ensureForDate(now());
        app(GlPeriodService::class)->close($period, $officer);

        try {
            app(PurchaseInvoiceService::class)->post($bill, $officer);
            $this->fail('Posting into a closed period should fail.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('closed', $exception->getMessage());
        }

        $this->assertSame(PurchaseInvoiceStatus::Draft, $bill->fresh()->status);
        $this->assertSame(0, GlJournal::query()->where('source_document_id', $bill->id)->count());
    }

    public function test_kit_issue_updates_stock_and_blocks_over_issue_and_duplicate_serials(): void
    {
        $officer = User::factory()->role(UserRole::ProcurementOfficer)->create();
        $guard = Guard::factory()->create();
        $stock = ProcurementStockItem::query()->create([
            'reference' => 'STK-0001',
            'name' => 'Company shirt',
            'category' => AssetCategory::Uniform,
            'unit' => 'pcs',
            'quantity_on_hand' => 2,
            'reorder_level' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($officer)->post(route('assets.store'), [
            'guard_id' => $guard->id,
            'issuance_type' => AssetIssuanceType::InitialKit->value,
            'issued_at' => now()->toDateString(),
            'lines' => [[
                'stock_item_id' => $stock->id,
                'asset_category' => AssetCategory::Uniform->value,
                'quantity' => 1,
                'recover_cost' => 1,
                'recovery_amount' => 50000,
            ]],
        ])->assertRedirect();

        $this->assertSame(1, $stock->fresh()->quantity_on_hand);
        $this->assertDatabaseMissing('guard_asset_recoveries', ['guard_id' => $guard->id]);

        $this->actingAs($officer)->post(route('assets.store'), [
            'guard_id' => $guard->id,
            'issuance_type' => AssetIssuanceType::Replacement->value,
            'issued_at' => now()->toDateString(),
            'lines' => [[
                'stock_item_id' => $stock->id,
                'asset_category' => AssetCategory::Uniform->value,
                'quantity' => 5,
            ]],
        ])->assertSessionHasErrors('lines');

        $this->assertSame(1, $stock->fresh()->quantity_on_hand);

        $this->actingAs($officer)->post(route('assets.store'), [
            'guard_id' => $guard->id,
            'issuance_type' => AssetIssuanceType::Replacement->value,
            'issued_at' => now()->toDateString(),
            'lines' => [[
                'asset_category' => AssetCategory::Radio->value,
                'serial_number' => 'RAD-1',
                'quantity' => 1,
            ]],
        ])->assertRedirect();

        $this->actingAs($officer)->post(route('assets.store'), [
            'guard_id' => $guard->id,
            'issuance_type' => AssetIssuanceType::Replacement->value,
            'issued_at' => now()->toDateString(),
            'lines' => [[
                'asset_category' => AssetCategory::Radio->value,
                'serial_number' => 'RAD-1',
                'quantity' => 1,
            ]],
        ])->assertSessionHasErrors('lines');
    }

    public function test_damaged_returns_do_not_restore_stock_and_good_returns_do(): void
    {
        $officer = User::factory()->role(UserRole::ProcurementOfficer)->create();
        $guard = Guard::factory()->create();
        $stock = ProcurementStockItem::query()->create([
            'reference' => 'STK-0002',
            'name' => 'Boots',
            'category' => AssetCategory::Boots,
            'unit' => 'pair',
            'quantity_on_hand' => 4,
            'reorder_level' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($officer)->post(route('assets.store'), [
            'guard_id' => $guard->id,
            'issuance_type' => AssetIssuanceType::InitialKit->value,
            'issued_at' => now()->toDateString(),
            'lines' => [[
                'stock_item_id' => $stock->id,
                'asset_category' => AssetCategory::Boots->value,
                'quantity' => 2,
            ]],
        ])->assertRedirect();

        $issuance = $guard->assetIssuances()->with('lines')->first();
        $line = $issuance->lines->first();

        $this->actingAs($officer)->post(route('assets.return', $issuance), [
            'lines' => [
                $line->id => ['return_qty' => 1, 'disposition' => 'damaged'],
            ],
        ])->assertRedirect();

        $this->assertSame(2, $stock->fresh()->quantity_on_hand);
        $this->assertSame(AssetLineStatus::WrittenOff, $line->fresh()->status);

        $this->actingAs($officer)->post(route('assets.return', $issuance), [
            'lines' => [
                $line->id => ['return_qty' => 1, 'disposition' => 'returned'],
            ],
        ])->assertRedirect();

        $this->assertSame(3, $stock->fresh()->quantity_on_hand);
    }

    public function test_guard_lookup_hides_salary_and_purchase_notifications_stay_with_procurement(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $officer = User::factory()->role(UserRole::ProcurementOfficer)->create();
        $guard = Guard::factory()->create(['base_shift_rate' => 450000]);

        $this->actingAs($officer)
            ->get(route('guards.show', $guard))
            ->assertOk()
            ->assertDontSee('450,000')
            ->assertDontSee('Documents');

        $bill = app(PurchaseInvoiceService::class)->post($this->draft($finance), $finance);

        $this->assertTrue(
            NotificationState::query()->where('user_id', $officer->id)->whereHas('auditLog', fn ($query) => $query->where('action', 'ledger.purchase_posted'))->exists()
        );

        $this->actingAs($finance)->post(route('payments.store'), [])->assertSessionHasErrors();
        $this->assertFalse(
            NotificationState::query()
                ->where('user_id', $officer->id)
                ->whereHas('auditLog', fn ($query) => $query->where('action', 'finance.payment_recorded'))
                ->exists()
        );

        $this->assertSame($bill->id, PurchaseInvoice::query()->value('id'));
    }

    /** @param  array<string, mixed>  $overrides */
    private function draft(User $actor, array $overrides = []): PurchaseInvoice
    {
        $expense = app(ChartOfAccountsService::class)->operatingExpense();

        return app(PurchaseInvoiceService::class)->createDraft(array_merge([
            'supplier_name' => 'Kampala Uniforms Ltd',
            'supplier_tin' => '100200300',
            'bill_date' => now()->toDateString(),
            'subtotal' => 100000,
            'tax_amount' => 18000,
            'expense_account_id' => $expense->id,
            'description' => 'Guard uniforms',
        ], $overrides), $actor);
    }
}
