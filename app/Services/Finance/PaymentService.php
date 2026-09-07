<?php

namespace App\Services\Finance;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PayrollRunStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Finance\Ledger\LedgerPostingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(
        private InvoiceService $invoices,
        private AuditService $audit,
        private LedgerPostingService $ledger,
    ) {
    }

    /**
     * @param  array{
     *     invoice_id: int,
     *     amount: float|int|string,
     *     payment_date: string,
     *     method?: string,
     *     external_reference?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function record(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($data['invoice_id']);

            if (in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Cancelled], true)) {
                throw new InvalidArgumentException('Payments can only be recorded against issued invoices.');
            }

            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0) {
                throw new InvalidArgumentException('Payment amount must be greater than zero.');
            }

            $balance = (float) $invoice->balance;
            if ($amount > $balance + 0.009) {
                throw new InvalidArgumentException('Payment exceeds outstanding balance of '.$balance.'.');
            }

            $payment = Payment::query()->create([
                'reference' => $this->nextReference($data['payment_date']),
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'amount' => $amount,
                'payment_date' => $data['payment_date'],
                'method' => $data['method'] ?? PaymentMethod::BankTransfer->value,
                'external_reference' => $data['external_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => auth()->id(),
            ]);

            $this->invoices->refreshPaymentState($invoice);

            $this->audit->log(
                action: 'finance.payment_recorded',
                summary: 'Payment '.$payment->reference.' recorded for invoice '.$invoice->reference.'.',
                category: AuditCategory::Finance,
                severity: AuditSeverity::Notice,
                subject: $payment,
                context: [
                    'invoice_id' => $invoice->id,
                    'amount' => $amount,
                ],
            );

            $fresh = $payment->fresh(['invoice', 'client', 'recorder']);
            $this->ledger->postPayment($fresh, auth()->user());

            return $fresh;
        });
    }

    public function recordPayrollDisbursement(PayrollRun $run, ?User $actor = null): Payment
    {
        $existing = Payment::query()->where('payroll_run_id', $run->id)->first();
        if ($existing) {
            return $existing;
        }

        $amount = round((float) $run->net_total, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Cannot record a payroll disbursement with zero net pay.');
        }

        $paymentDate = ($run->paid_at ?? now())->toDateString();

        $payment = Payment::query()->create([
            'reference' => $this->nextReference($paymentDate),
            'payroll_run_id' => $run->id,
            'invoice_id' => null,
            'client_id' => null,
            'amount' => $amount,
            'payment_date' => $paymentDate,
            'method' => PaymentMethod::BankTransfer->value,
            'external_reference' => $run->reference,
            'notes' => 'Payroll disbursement for '.$run->periodLabel().' ('.$run->guard_count.' guards).',
            'recorded_by' => $actor?->id,
        ]);

        $this->audit->log(
            action: 'finance.payroll_disbursement_recorded',
            summary: 'Payroll disbursement '.$payment->reference.' recorded for '.$run->reference.'.',
            category: AuditCategory::Finance,
            severity: AuditSeverity::Notice,
            subject: $payment,
            context: [
                'payroll_run_id' => $run->id,
                'amount' => $amount,
            ],
        );

        return $payment->fresh(['payrollRun', 'recorder']);
    }

    /** Backfill payment records for payroll runs marked paid before disbursement linking existed. */
    public function syncMissingPayrollDisbursements(): int
    {
        $synced = 0;

        PayrollRun::query()
            ->where('status', PayrollRunStatus::Paid)
            ->where('net_total', '>', 0)
            ->whereDoesntHave('payment')
            ->orderBy('id')
            ->each(function (PayrollRun $run) use (&$synced): void {
                try {
                    $this->recordPayrollDisbursement($run, $run->payer);
                    $synced++;
                } catch (InvalidArgumentException) {
                    // Skip runs that cannot be synced.
                }
            });

        return $synced;
    }

    public function removePayrollDisbursement(PayrollRun $run): void
    {
        $payment = Payment::query()->where('payroll_run_id', $run->id)->first();

        if ($payment === null) {
            return;
        }

        $reference = $payment->reference;
        $payment->delete();

        $this->audit->log(
            action: 'finance.payroll_disbursement_removed',
            summary: 'Payroll disbursement '.$reference.' removed after '.$run->reference.' was cancelled.',
            category: AuditCategory::Finance,
            severity: AuditSeverity::Warning,
            subject: $run,
            context: [
                'payment_reference' => $reference,
            ],
        );
    }

    public function nextReference(string $paymentDate): string
    {
        $prefix = 'PAY-'.Carbon::parse($paymentDate)->format('Ym').'-';
        $latest = Payment::query()
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')
            ->value('reference');

        $seq = 1;
        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
