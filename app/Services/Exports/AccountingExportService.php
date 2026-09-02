<?php

namespace App\Services\Exports;

use App\Enums\InvoiceStatus;
use App\Enums\PayrollRunStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\SystemSetting;
use App\Support\Money;
use App\Services\SystemSettingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class AccountingExportService
{
    public function __construct(
        private SystemSettingService $settings,
    ) {
    }

    /**
     * @return list<string> Written file paths (relative to local disk)
     */
    public function run(?Carbon $since = null, ?SystemSetting $settings = null): array
    {
        $settings ??= $this->settings->current();

        if (! ($settings->accounting_export_enabled ?? false)) {
            return [];
        }

        $since ??= $settings->accounting_export_last_run_at
            ? Carbon::parse($settings->accounting_export_last_run_at)
            : now()->subMonth();

        $directory = trim((string) ($settings->accounting_export_path ?: 'exports/accounting'), '/');
        $stamp = now()->format('Y-m-d_His');
        $written = [];

        $invoiceRows = $this->invoiceJournalRows($since);
        if ($invoiceRows->isNotEmpty()) {
            $path = "{$directory}/invoices-{$stamp}.csv";
            $this->writeCsv($path, $this->invoiceHeaders(), $invoiceRows);
            $written[] = $path;
        }

        $paymentRows = $this->paymentJournalRows($since);
        if ($paymentRows->isNotEmpty()) {
            $path = "{$directory}/payments-{$stamp}.csv";
            $this->writeCsv($path, $this->paymentHeaders(), $paymentRows);
            $written[] = $path;
        }

        $payrollRows = $this->payrollJournalRows($since);
        if ($payrollRows->isNotEmpty()) {
            $path = "{$directory}/payroll-{$stamp}.csv";
            $this->writeCsv($path, $this->payrollHeaders(), $payrollRows);
            $written[] = $path;
        }

        if ($written !== []) {
            $settings->update(['accounting_export_last_run_at' => now()]);
            $this->settings->flushCache();
        }

        return $written;
    }

    /** @return list<string> */
    public function invoiceHeaders(): array
    {
        return [
            'Journal Date',
            'Reference',
            'Client',
            'Site',
            'Period Start',
            'Period End',
            'Due Date',
            'Status',
            'Debit Account',
            'Credit Account',
            'Amount',
            'Tax',
            'Total',
            'Amount Paid',
            'Balance',
            'Currency',
            'Notes',
        ];
    }

    /** @return Collection<int, list<string|int|float|null>> */
    public function invoiceJournalRows(Carbon $since): Collection
    {
        return Invoice::query()
            ->with(['client:id,name', 'site:id,name,code'])
            ->where('updated_at', '>=', $since)
            ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Cancelled->value])
            ->latest('updated_at')
            ->limit(5000)
            ->get()
            ->map(fn (Invoice $invoice) => [
                optional($invoice->issue_date)?->toDateString() ?? $invoice->updated_at->toDateString(),
                $invoice->reference,
                $invoice->client?->name,
                $invoice->site?->code ?? $invoice->site?->name,
                $invoice->period_start?->toDateString(),
                $invoice->period_end?->toDateString(),
                $invoice->due_date?->toDateString(),
                $invoice->status->label(),
                'Accounts Receivable',
                'Security Services Revenue',
                (float) $invoice->subtotal,
                (float) $invoice->tax_amount,
                (float) $invoice->total,
                (float) $invoice->amount_paid,
                (float) $invoice->balance,
                $invoice->currency,
                $invoice->notes,
            ]);
    }

    /** @return list<string> */
    public function paymentHeaders(): array
    {
        return [
            'Journal Date',
            'Payment Reference',
            'Invoice Reference',
            'Client',
            'Method',
            'Debit Account',
            'Credit Account',
            'Amount',
            'Currency',
            'Notes',
        ];
    }

    /** @return Collection<int, list<string|int|float|null>> */
    public function paymentJournalRows(Carbon $since): Collection
    {
        return Payment::query()
            ->with(['invoice.client:id,name', 'invoice:id,reference,client_id'])
            ->where('updated_at', '>=', $since)
            ->whereNotNull('invoice_id')
            ->latest('updated_at')
            ->limit(5000)
            ->get()
            ->map(fn (Payment $payment) => [
                $payment->payment_date?->toDateString() ?? $payment->created_at->toDateString(),
                $payment->reference,
                $payment->invoice?->reference,
                $payment->invoice?->client?->name,
                $payment->method?->label() ?? $payment->method,
                'Bank / Cash',
                'Accounts Receivable',
                (float) $payment->amount,
                Money::currency(),
                $payment->notes,
            ]);
    }

    /** @return list<string> */
    public function payrollHeaders(): array
    {
        return [
            'Journal Date',
            'Payroll Reference',
            'Period',
            'Status',
            'Debit Account',
            'Credit Account',
            'Gross Total',
            'Deductions',
            'Net Total',
            'Currency',
        ];
    }

    /** @return Collection<int, list<string|int|float|null>> */
    public function payrollJournalRows(Carbon $since): Collection
    {
        return PayrollRun::query()
            ->where('updated_at', '>=', $since)
            ->whereIn('status', [PayrollRunStatus::Approved->value, PayrollRunStatus::Paid->value])
            ->latest('updated_at')
            ->limit(500)
            ->get()
            ->map(fn (PayrollRun $run) => [
                optional($run->approved_at)?->toDateString() ?? $run->updated_at->toDateString(),
                $run->reference,
                sprintf('%02d/%04d', $run->period_month, $run->period_year),
                $run->status->label(),
                'Payroll Expense',
                'Payroll Payable',
                (float) $run->gross_total,
                (float) $run->deductions_total,
                (float) $run->net_total,
                $run->currency ?? Money::currency(),
            ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  Collection<int, list<string|int|float|null>>  $rows
     */
    private function writeCsv(string $path, array $headers, Collection $rows): void
    {
        Storage::disk('local')->makeDirectory(dirname($path));

        $temp = fopen('php://temp', 'r+');
        fwrite($temp, "\xEF\xBB\xBF");
        fputcsv($temp, $headers);

        foreach ($rows as $row) {
            fputcsv($temp, array_map(fn ($value) => $value === null ? '' : (string) $value, $row));
        }

        rewind($temp);
        Storage::disk('local')->put($path, stream_get_contents($temp) ?: '');
        fclose($temp);
    }
}
