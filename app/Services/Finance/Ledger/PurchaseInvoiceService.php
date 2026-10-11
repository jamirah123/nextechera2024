<?php

namespace App\Services\Finance\Ledger;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\GlJournalSource;
use App\Enums\PurchaseInvoiceStatus;
use App\Enums\VatTreatment;
use App\Models\GlAccount;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
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
        return DB::transaction(function () use ($data, $actor): PurchaseInvoice {
            $prepared = $this->prepareAmounts($data);

            return PurchaseInvoice::query()->create([
                'reference' => $this->nextReference($data['bill_date']),
                'supplier_id' => $prepared['supplier_id'],
                'supplier_name' => $prepared['supplier_name'],
                'supplier_tin' => $prepared['supplier_tin'],
                'supplier_invoice_no' => $prepared['supplier_invoice_no'],
                'bill_date' => $data['bill_date'],
                'due_date' => $data['due_date'] ?? null,
                'status' => PurchaseInvoiceStatus::Draft,
                'currency' => Money::currency(),
                'vat_treatment' => $prepared['vat_treatment'],
                'subtotal' => $prepared['subtotal'],
                'tax_amount' => $prepared['tax'],
                'total' => $prepared['total'],
                'expense_account_id' => $prepared['account_id'],
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'attachment_path' => $data['attachment_path'] ?? null,
                'created_by' => $actor?->id,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(PurchaseInvoice $bill, array $data): PurchaseInvoice
    {
        if (! $bill->isEditable()) {
            throw new InvalidArgumentException('Only draft purchase invoices can be edited.');
        }

        $prepared = $this->prepareAmounts(array_merge([
            'supplier_id' => $bill->supplier_id,
            'supplier_name' => $bill->supplier_name,
            'supplier_tin' => $bill->supplier_tin,
            'supplier_invoice_no' => $bill->supplier_invoice_no,
            'subtotal' => $bill->subtotal,
            'tax_amount' => $bill->tax_amount,
            'vat_treatment' => $bill->vat_treatment?->value ?? VatTreatment::Standard->value,
            'expense_account_id' => $bill->expense_account_id,
        ], $data), $bill->id);

        $bill->update([
            'supplier_id' => $prepared['supplier_id'],
            'supplier_name' => $prepared['supplier_name'],
            'supplier_tin' => $prepared['supplier_tin'],
            'supplier_invoice_no' => $prepared['supplier_invoice_no'],
            'bill_date' => $data['bill_date'] ?? $bill->bill_date,
            'due_date' => array_key_exists('due_date', $data) ? $data['due_date'] : $bill->due_date,
            'vat_treatment' => $prepared['vat_treatment'],
            'subtotal' => $prepared['subtotal'],
            'tax_amount' => $prepared['tax'],
            'total' => $prepared['total'],
            'expense_account_id' => $prepared['account_id'],
            'description' => $data['description'] ?? $bill->description,
            'notes' => $data['notes'] ?? $bill->notes,
            'attachment_path' => $data['attachment_path'] ?? $bill->attachment_path,
        ]);

        return $bill->fresh(['expenseAccount', 'supplier']);
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
            $locked = PurchaseInvoice::query()->whereKey($bill->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isEditable()) {
                throw new InvalidArgumentException('Only draft purchase invoices can be posted.');
            }

            $this->prepareAmounts([
                'supplier_id' => $locked->supplier_id,
                'supplier_name' => $locked->supplier_name,
                'supplier_tin' => $locked->supplier_tin,
                'supplier_invoice_no' => $locked->supplier_invoice_no,
                'subtotal' => $locked->subtotal,
                'tax_amount' => $locked->tax_amount,
                'vat_treatment' => $locked->vat_treatment?->value ?? VatTreatment::Standard->value,
                'expense_account_id' => $locked->expense_account_id,
                'bill_date' => $locked->bill_date?->toDateString(),
            ], $locked->id);

            $locked->update([
                'status' => PurchaseInvoiceStatus::Posted,
                'posted_at' => now(),
                'posted_by' => $actor?->id,
            ]);

            $journal = $this->ledger->postPurchase($locked->fresh('expenseAccount'), $actor);

            if ($journal !== null && ! $journal->fresh('lines')->isBalanced()) {
                throw new InvalidArgumentException('The supplier bill journal does not balance.');
            }

            $bill = $locked;

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

    public function cancel(PurchaseInvoice $bill, ?User $actor = null, ?string $reason = null): PurchaseInvoice
    {
        $reason = trim((string) $reason);

        if ($reason === '') {
            throw new InvalidArgumentException('A cancellation reason is required.');
        }

        return DB::transaction(function () use ($bill, $actor, $reason) {
            $locked = PurchaseInvoice::query()->whereKey($bill->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PurchaseInvoiceStatus::Cancelled) {
                throw new InvalidArgumentException('This purchase is already cancelled.');
            }

            if ($locked->status === PurchaseInvoiceStatus::Posted) {
                $this->ledger->reverseForDocument(GlJournalSource::Purchase, $locked, $actor, 'Purchase cancelled: '.$reason);
            }

            $locked->update([
                'status' => PurchaseInvoiceStatus::Cancelled,
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
                'cancelled_by' => $actor?->id,
            ]);

            $bill = $locked;

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

    /**
     * @param  array<string, mixed>  $data
     * @return array{supplier_id: int|null, supplier_name: string, supplier_tin: string|null, supplier_invoice_no: string|null, vat_treatment: string, subtotal: float, tax: float, total: float, account_id: int}
     */
    private function prepareAmounts(array $data, ?int $ignoreBillId = null): array
    {
        $supplier = filled($data['supplier_id'] ?? null)
            ? Supplier::query()->find($data['supplier_id'])
            : null;

        if (filled($data['supplier_id'] ?? null) && $supplier === null) {
            throw new InvalidArgumentException('Choose a valid supplier.');
        }

        $name = trim((string) ($supplier?->name ?? $data['supplier_name'] ?? ''));
        $tin = $supplier?->tin ?: ($data['supplier_tin'] ?? null);
        $invoiceNo = filled($data['supplier_invoice_no'] ?? null) ? trim((string) $data['supplier_invoice_no']) : null;

        if ($name === '') {
            throw new InvalidArgumentException('Supplier name is required.');
        }

        $subtotal = round((float) ($data['subtotal'] ?? 0), 2);
        $tax = round((float) ($data['tax_amount'] ?? 0), 2);
        $treatment = VatTreatment::tryFrom((string) ($data['vat_treatment'] ?? VatTreatment::Standard->value))
            ?? VatTreatment::Standard;

        if ($subtotal <= 0) {
            throw new InvalidArgumentException('Purchase subtotal must be greater than zero.');
        }

        if ($tax < 0) {
            throw new InvalidArgumentException('VAT cannot be negative.');
        }

        if (! $treatment->chargesVat() && $tax > 0) {
            throw new InvalidArgumentException($treatment->label().' purchases cannot claim input VAT. Ask Finance to review the tax treatment.');
        }

        if ($tax > 0 && blank($tin)) {
            throw new InvalidArgumentException('A supplier TIN is required before input VAT can be claimed.');
        }

        $accountId = (int) ($data['expense_account_id'] ?? $this->coa->operatingExpense()->id);
        $this->assertExpenseAccount($accountId);
        $this->assertUniqueSupplierInvoice($name, $invoiceNo, $ignoreBillId);

        return [
            'supplier_id' => $supplier?->id,
            'supplier_name' => $name,
            'supplier_tin' => filled($tin) ? trim((string) $tin) : null,
            'supplier_invoice_no' => $invoiceNo,
            'vat_treatment' => $treatment->value,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => round($subtotal + $tax, 2),
            'account_id' => $accountId,
        ];
    }

    private function assertUniqueSupplierInvoice(string $supplierName, ?string $invoiceNo, ?int $ignoreBillId): void
    {
        if ($invoiceNo === null || $invoiceNo === '') {
            return;
        }

        $exists = PurchaseInvoice::query()
            ->where('supplier_invoice_no', $invoiceNo)
            ->where('supplier_name', $supplierName)
            ->where('status', '!=', PurchaseInvoiceStatus::Cancelled->value)
            ->when($ignoreBillId, fn ($query) => $query->whereKeyNot($ignoreBillId))
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('This supplier invoice number is already on another open bill.');
        }
    }

    private function nextReference(string $billDate): string
    {
        $stamp = Carbon::parse($billDate)->format('Ym');
        $prefix = 'PINV-'.$stamp.'-';
        $latest = PurchaseInvoice::query()
            ->where('reference', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('reference')
            ->value('reference');

        $sequence = 1;
        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
