<?php

namespace App\Services\Finance\Ledger;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\GlJournalSource;
use App\Enums\GlJournalStatus;
use App\Models\GlAccount;
use App\Models\GlJournal;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LedgerPostingService
{
    public function __construct(
        private ChartOfAccountsService $coa,
        private GlPeriodService $periods,
        private AuditService $audit,
    ) {}

    public function postInvoice(Invoice $invoice, ?User $actor = null): ?GlJournal
    {
        if ($this->existingPosted(GlJournalSource::Invoice, $invoice)) {
            return $this->existingPosted(GlJournalSource::Invoice, $invoice);
        }

        $subtotal = round((float) $invoice->subtotal, 2);
        $tax = round((float) $invoice->tax_amount, 2);
        $total = round((float) $invoice->total, 2);

        if ($total <= 0) {
            return null;
        }

        $lines = [
            ['account' => $this->coa->accountsReceivable(), 'debit' => $total, 'credit' => 0, 'memo' => 'AR '.$invoice->reference],
            ['account' => $this->coa->revenueServices(), 'debit' => 0, 'credit' => $subtotal, 'memo' => 'Revenue '.$invoice->reference],
        ];

        if ($tax > 0) {
            $lines[] = [
                'account' => $this->coa->vatOutput(),
                'debit' => 0,
                'credit' => $tax,
                'memo' => 'VAT output '.$invoice->reference,
            ];
        }

        return $this->post(
            source: GlJournalSource::Invoice,
            document: $invoice,
            journalDate: optional($invoice->issue_date)?->toDateString() ?? now()->toDateString(),
            description: 'Issue invoice '.$invoice->reference,
            lines: $lines,
            actor: $actor,
            currency: $invoice->currency ?: Money::currency(),
        );
    }

    public function postPayment(Payment $payment, ?User $actor = null): ?GlJournal
    {
        if ($payment->isDisbursement()) {
            return null;
        }

        if ($this->existingPosted(GlJournalSource::Payment, $payment)) {
            return $this->existingPosted(GlJournalSource::Payment, $payment);
        }

        $amount = round((float) $payment->amount, 2);
        if ($amount <= 0) {
            return null;
        }

        return $this->post(
            source: GlJournalSource::Payment,
            document: $payment,
            journalDate: optional($payment->payment_date)?->toDateString() ?? now()->toDateString(),
            description: 'Client payment '.$payment->reference.($payment->invoice ? ' for '.$payment->invoice->reference : ''),
            lines: [
                ['account' => $this->coa->cashBank(), 'debit' => $amount, 'credit' => 0, 'memo' => 'Bank receipt '.$payment->reference],
                ['account' => $this->coa->accountsReceivable(), 'debit' => 0, 'credit' => $amount, 'memo' => 'Clear AR '.$payment->reference],
            ],
            actor: $actor,
            currency: Money::currency(),
        );
    }

    public function postPurchase(PurchaseInvoice $bill, ?User $actor = null): ?GlJournal
    {
        if ($this->existingPosted(GlJournalSource::Purchase, $bill)) {
            return $this->existingPosted(GlJournalSource::Purchase, $bill);
        }

        $subtotal = round((float) $bill->subtotal, 2);
        $tax = round((float) $bill->tax_amount, 2);
        $total = round((float) $bill->total, 2);

        if ($total <= 0) {
            return null;
        }

        $expense = $bill->expenseAccount ?? $this->coa->operatingExpense();

        $lines = [
            ['account' => $expense, 'debit' => $subtotal, 'credit' => 0, 'memo' => 'Expense '.$bill->reference],
            ['account' => $this->coa->accountsPayable(), 'debit' => 0, 'credit' => $total, 'memo' => 'AP '.$bill->reference],
        ];

        if ($tax > 0) {
            array_splice($lines, 1, 0, [[
                'account' => $this->coa->vatInput(),
                'debit' => $tax,
                'credit' => 0,
                'memo' => 'VAT input '.$bill->reference,
            ]]);
        }

        return $this->post(
            source: GlJournalSource::Purchase,
            document: $bill,
            journalDate: optional($bill->bill_date)?->toDateString() ?? now()->toDateString(),
            description: 'Purchase '.$bill->reference.' · '.$bill->supplier_name,
            lines: $lines,
            actor: $actor,
            currency: $bill->currency ?: Money::currency(),
        );
    }

    public function postPayrollAccrual(PayrollRun $run, ?User $actor = null): ?GlJournal
    {
        if ($this->existingPosted(GlJournalSource::PayrollAccrual, $run)) {
            return $this->existingPosted(GlJournalSource::PayrollAccrual, $run);
        }

        $gross = round((float) $run->gross_total, 2);
        $net = round(min(max((float) $run->net_total, 0), $gross), 2);
        // Net pay is never negative. When statutory deductions are larger than a small gross,
        // the withheld amount is gross minus net, which is what the journal can credit.
        $withheld = round($gross - $net, 2);

        if ($gross <= 0) {
            return null;
        }

        $lines = [
            ['account' => $this->coa->payrollExpense(), 'debit' => $gross, 'credit' => 0, 'memo' => 'Gross payroll '.$run->reference],
        ];

        if ($net > 0) {
            $lines[] = [
                'account' => $this->coa->payrollPayable(),
                'debit' => 0,
                'credit' => $net,
                'memo' => 'Net pay '.$run->reference,
            ];
        }

        if ($withheld > 0) {
            $lines[] = [
                'account' => $this->coa->payrollDeductions(),
                'debit' => 0,
                'credit' => $withheld,
                'memo' => 'Deductions '.$run->reference,
            ];
        }

        return $this->post(
            source: GlJournalSource::PayrollAccrual,
            document: $run,
            journalDate: optional($run->approved_at)?->toDateString() ?? now()->toDateString(),
            description: 'Payroll accrual '.$run->reference.' ('.$run->periodLabel().')',
            lines: $lines,
            actor: $actor,
            currency: $run->currency ?: Money::currency(),
        );
    }

    public function postPayrollPayment(PayrollRun $run, ?User $actor = null): ?GlJournal
    {
        if ($this->existingPosted(GlJournalSource::PayrollPayment, $run)) {
            return $this->existingPosted(GlJournalSource::PayrollPayment, $run);
        }

        $net = round((float) $run->net_total, 2);
        if ($net <= 0) {
            return null;
        }

        return $this->post(
            source: GlJournalSource::PayrollPayment,
            document: $run,
            journalDate: optional($run->paid_at)?->toDateString() ?? now()->toDateString(),
            description: 'Payroll disbursement '.$run->reference.' ('.$run->periodLabel().')',
            lines: [
                ['account' => $this->coa->payrollPayable(), 'debit' => $net, 'credit' => 0, 'memo' => 'Clear net pay '.$run->reference],
                ['account' => $this->coa->cashBank(), 'debit' => 0, 'credit' => $net, 'memo' => 'Bank payment '.$run->reference],
            ],
            actor: $actor,
            currency: $run->currency ?: Money::currency(),
        );
    }

    public function reverseForDocument(GlJournalSource $source, Model $document, ?User $actor = null, ?string $reason = null): ?GlJournal
    {
        $original = $this->existingPosted($source, $document);
        if (! $original) {
            return null;
        }

        return $this->reverse($original, $actor, $reason);
    }

    public function reverse(GlJournal $journal, ?User $actor = null, ?string $reason = null): GlJournal
    {
        if ($journal->status !== GlJournalStatus::Posted) {
            throw new InvalidArgumentException('Only posted journals can be reversed.');
        }

        if ($journal->reversals()->posted()->exists()) {
            throw new InvalidArgumentException('This journal already has a posted reversal.');
        }

        $journal->loadMissing('lines.account');

        $lines = $journal->lines->map(fn ($line) => [
            'account' => $line->account,
            'debit' => (float) $line->credit,
            'credit' => (float) $line->debit,
            'memo' => 'Reversal of '.$journal->reference.($line->memo ? ' · '.$line->memo : ''),
        ])->all();

        return DB::transaction(function () use ($journal, $lines, $actor, $reason) {
            $reversal = $this->post(
                source: GlJournalSource::Reversal,
                document: $journal->sourceDocument ?? $journal,
                journalDate: now()->toDateString(),
                description: 'Reversal of '.$journal->reference.($reason ? ' — '.$reason : ''),
                lines: $lines,
                actor: $actor,
                currency: $journal->currency,
                reversalOfId: $journal->id,
            );

            $journal->update([
                'status' => GlJournalStatus::Void,
                'voided_at' => now(),
                'voided_by' => $actor?->id,
            ]);

            return $reversal;
        });
    }

    /**
     * @param  list<array{account: GlAccount, debit: float|int, credit: float|int, memo?: string|null}>  $lines
     */
    public function post(
        GlJournalSource $source,
        ?Model $document,
        string $journalDate,
        string $description,
        array $lines,
        ?User $actor = null,
        ?string $currency = null,
        ?int $reversalOfId = null,
    ): GlJournal {
        $period = $this->periods->assertOpenForDate($journalDate);
        $normalized = [];
        $debitTotal = 0.0;
        $creditTotal = 0.0;

        foreach ($lines as $index => $line) {
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            if ($debit < 0 || $credit < 0) {
                throw new InvalidArgumentException('Journal line amounts cannot be negative.');
            }

            if (($debit > 0 && $credit > 0) || ($debit == 0 && $credit == 0)) {
                throw new InvalidArgumentException('Each journal line must have either a debit or a credit.');
            }

            /** @var GlAccount $account */
            $account = $line['account'];
            if (! $account->is_postable || ! $account->is_active) {
                throw new InvalidArgumentException('Account '.$account->code.' is not postable.');
            }

            $debitTotal += $debit;
            $creditTotal += $credit;
            $normalized[] = [
                'account_id' => $account->id,
                'debit' => $debit,
                'credit' => $credit,
                'memo' => $line['memo'] ?? null,
                'sort_order' => $index,
            ];
        }

        if (abs($debitTotal - $creditTotal) > 0.009) {
            throw new InvalidArgumentException(
                'Journal is out of balance (debit '.Money::format($debitTotal).' vs credit '.Money::format($creditTotal).').'
            );
        }

        if ($normalized === []) {
            throw new InvalidArgumentException('Journal requires at least one line.');
        }

        return DB::transaction(function () use ($source, $document, $journalDate, $description, $normalized, $actor, $currency, $reversalOfId, $period, $debitTotal) {
            $journal = GlJournal::query()->create([
                'reference' => $this->nextReference($journalDate),
                'journal_date' => $journalDate,
                'period_id' => $period->id,
                'source' => $source,
                'source_document_type' => $document ? $document::class : null,
                'source_document_id' => $document?->getKey(),
                'description' => $description,
                'status' => GlJournalStatus::Posted,
                'currency' => $currency ?: Money::currency(),
                'posted_at' => now(),
                'posted_by' => $actor?->id ?? auth()->id(),
                'reversal_of_id' => $reversalOfId,
            ]);

            foreach ($normalized as $line) {
                $journal->lines()->create($line);
            }

            $this->audit->log(
                action: 'ledger.journal_posted',
                summary: 'Journal '.$journal->reference.' posted ('.Money::format($debitTotal).').',
                category: AuditCategory::Finance,
                severity: AuditSeverity::Notice,
                subject: $journal,
                actor: $actor,
                context: [
                    'source' => $source->value,
                    'period_id' => $period->id,
                ],
            );

            return $journal->fresh(['lines.account', 'period']);
        });
    }

    private function existingPosted(GlJournalSource $source, Model $document): ?GlJournal
    {
        return GlJournal::query()
            ->posted()
            ->where('source', $source->value)
            ->where('source_document_type', $document::class)
            ->where('source_document_id', $document->getKey())
            ->latest('id')
            ->first();
    }

    private function nextReference(string $journalDate): string
    {
        $stamp = Carbon::parse($journalDate)->format('Ymd');
        $prefix = 'JE-'.$stamp.'-';
        $latest = GlJournal::query()
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('reference');

        $sequence = 1;
        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
