<?php

namespace App\Services\Finance\Ledger;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\GlJournalSource;
use App\Enums\PurchaseInvoiceStatus;
use App\Models\GlAccount;
use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseInvoiceService
{
    public function __construct(
        private LedgerPostingService $ledger,
        private ChartOfAccountsService $coa,
        private AuditService $audit,
    ) {}

    /**
     * @param  array{
     *     supplier_name: string,
     *     supplier_tin?: string|null,
     *     supplier_invoice_no?: string|null,
     *     bill_date: string,
     *     due_date?: string|null,
     *     subtotal: float|int|string,
     *     tax_amount?: float|int|string,
     *     expense_account_id?: int|null,
     *     description?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function createDraft(array $data, ?User $actor = null): PurchaseInvoice
    {
        $subtotal = round((float) $data['subtotal'], 2);
        $tax = round((float) ($data['tax_amount'] ?? 0), 2);

        if ($subtotal <= 0) {
            throw new InvalidArgumentException('Purchase subtotal must be greater than zero.');
        }

        if ($tax < 0) {
            throw new InvalidArgumentException('VAT cannot be negative.');
        }

        $accountId = (int) ($data['expense_account_id'] ?? $this->coa->operatingExpense()->id);
        $this->assertExpenseAccount($accountId);

        return PurchaseInvoice::query()->create([
            'reference' => $this->nextReference($data['bill_date']),
            'supplier_name' => $data['supplier_name'],
            'supplier_tin' => $data['supplier_tin'] ?? null,
            'supplier_invoice_no' => $data['supplier_invoice_no'] ?? null,
            'bill_date' => $data['bill_date'],
            'due_date' => $data['due_date'] ?? null,
            'status' => PurchaseInvoiceStatus::Draft,
            'currency' => Money::currency(),
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total' => round($subtotal + $tax, 2),
            'expense_account_id' => $accountId,
            'description' => $data['description'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor?->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(PurchaseInvoice $bill, array $data): PurchaseInvoice
    {
        if (! $bill->isEditable()) {
            throw new InvalidArgumentException('Only draft purchase invoices can be edited.');
        }

        $subtotal = round((float) ($data['subtotal'] ?? $bill->subtotal), 2);
        $tax = round((float) ($data['tax_amount'] ?? $bill->tax_amount), 2);
        $accountId = (int) ($data['expense_account_id'] ?? $bill->expense_account_id);
        $this->assertExpenseAccount($accountId);

        $bill->update([
            'supplier_name' => $data['supplier_name'] ?? $bill->supplier_name,
            'supplier_tin' => $data['supplier_tin'] ?? $bill->supplier_tin,
            'supplier_invoice_no' => $data['supplier_invoice_no'] ?? $bill->supplier_invoice_no,
            'bill_date' => $data['bill_date'] ?? $bill->bill_date,
            'due_date' => array_key_exists('due_date', $data) ? $data['due_date'] : $bill->due_date,
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total' => round($subtotal + $tax, 2),
            'expense_account_id' => $accountId,
            'description' => $data['description'] ?? $bill->description,
            'notes' => $data['notes'] ?? $bill->notes,
        ]);

        return $bill->fresh('expenseAccount');
    }

    public function post(PurchaseInvoice $bill, ?User $actor = null): PurchaseInvoice
    {
        if (! $bill->isEditable()) {
            throw new InvalidArgumentException('Only draft purchase invoices can be posted.');
        }

        if ((float) $bill->total <= 0) {
            throw new InvalidArgumentException('Cannot post a purchase with zero total.');
        }

        return DB::transaction(function () use ($bill, $actor) {
            $bill->update([
                'status' => PurchaseInvoiceStatus::Posted,
                'posted_at' => now(),
                'posted_by' => $actor?->id,
            ]);

            $this->ledger->postPurchase($bill->fresh('expenseAccount'), $actor);

            $this->audit->log(
                action: 'ledger.purchase_posted',
                summary: 'Purchase '.$bill->reference.' posted for '.$bill->supplier_name.'.',
                category: AuditCategory::Finance,
                severity: AuditSeverity::Notice,
                subject: $bill,
                actor: $actor,
            );

            return $bill->fresh(['expenseAccount', 'poster']);
        });
    }

    public function cancel(PurchaseInvoice $bill, ?User $actor = null): PurchaseInvoice
    {
        if ($bill->status === PurchaseInvoiceStatus::Cancelled) {
            throw new InvalidArgumentException('This purchase is already cancelled.');
        }

        return DB::transaction(function () use ($bill, $actor) {
            if ($bill->status === PurchaseInvoiceStatus::Posted) {
                $this->ledger->reverseForDocument(GlJournalSource::Purchase, $bill, $actor, 'Purchase cancelled');
            }

            $bill->update(['status' => PurchaseInvoiceStatus::Cancelled]);

            $this->audit->log(
                action: 'ledger.purchase_cancelled',
                summary: 'Purchase '.$bill->reference.' cancelled.',
                category: AuditCategory::Finance,
                severity: AuditSeverity::Warning,
                subject: $bill,
                actor: $actor,
            );

            return $bill->fresh();
        });
    }

    private function assertExpenseAccount(int $accountId): void
    {
        $account = GlAccount::query()->postable()->find($accountId);
        if (! $account || $account->type->value !== 'expense') {
            throw new InvalidArgumentException('Choose an active expense account.');
        }
    }

    private function nextReference(string $billDate): string
    {
        $stamp = Carbon::parse($billDate)->format('Ym');
        $prefix = 'PINV-'.$stamp.'-';
        $latest = PurchaseInvoice::query()
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('reference');

        $sequence = 1;
        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }
}
