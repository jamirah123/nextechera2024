<?php

namespace App\Services\Finance;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(
        private InvoiceService $invoices,
        private AuditService $audit,
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

            return $payment->fresh(['invoice', 'client', 'recorder']);
        });
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
