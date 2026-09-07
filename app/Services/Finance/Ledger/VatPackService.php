<?php

namespace App\Services\Finance\Ledger;

use App\Enums\GlJournalStatus;
use App\Enums\PurchaseInvoiceStatus;
use App\Models\GlJournalLine;
use App\Models\GlPeriod;
use App\Models\Invoice;
use App\Models\PurchaseInvoice;
use App\Support\Money;
use Illuminate\Support\Collection;

class VatPackService
{
    public function __construct(private ChartOfAccountsService $coa)
    {
    }

    public function defaultRate(): float
    {
        return (float) config('psg.vat_rate', 18);
    }

    /**
     * @return array{
     *     period: GlPeriod,
     *     rate: float,
     *     output_vat: float,
     *     input_vat: float,
     *     net_vat: float,
     *     taxable_sales: float,
     *     zero_rated_sales: float,
     *     taxable_purchases: float,
     *     invoice_count: int,
     *     purchase_count: int,
     *     journal_output_vat: float,
     *     journal_input_vat: float,
     *     invoices: Collection<int, Invoice>,
     *     purchases: Collection<int, PurchaseInvoice>
     * }
     */
    public function returnForPeriod(GlPeriod $period): array
    {
        $invoices = Invoice::query()
            ->with('client:id,name')
            ->whereNotNull('issue_date')
            ->whereDate('issue_date', '>=', $period->starts_on)
            ->whereDate('issue_date', '<=', $period->ends_on)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->orderBy('issue_date')
            ->get();

        $purchases = PurchaseInvoice::query()
            ->with('expenseAccount:id,code,name')
            ->where('status', PurchaseInvoiceStatus::Posted->value)
            ->whereDate('bill_date', '>=', $period->starts_on)
            ->whereDate('bill_date', '<=', $period->ends_on)
            ->orderBy('bill_date')
            ->get();

        $taxable = $invoices->filter(fn (Invoice $invoice) => (float) $invoice->tax_amount > 0);
        $zeroRated = $invoices->filter(fn (Invoice $invoice) => (float) $invoice->tax_amount <= 0);
        $taxablePurchases = $purchases->filter(fn (PurchaseInvoice $bill) => (float) $bill->tax_amount > 0);

        $vatOutput = $this->coa->vatOutput();
        $vatInput = $this->coa->vatInput();

        $journalOutput = $this->accountNetCredit($vatOutput->id, $period);
        $journalInput = $this->accountNetDebit($vatInput->id, $period);

        $outputVat = round((float) $taxable->sum('tax_amount'), 2);
        $inputVat = round((float) $purchases->sum('tax_amount'), 2);

        return [
            'period' => $period,
            'rate' => $this->defaultRate(),
            'output_vat' => $outputVat,
            'input_vat' => $inputVat,
            'net_vat' => round($outputVat - $inputVat, 2),
            'taxable_sales' => round((float) $taxable->sum('subtotal'), 2),
            'zero_rated_sales' => round((float) $zeroRated->sum('subtotal'), 2),
            'taxable_purchases' => round((float) $taxablePurchases->sum('subtotal'), 2),
            'invoice_count' => $invoices->count(),
            'purchase_count' => $purchases->count(),
            'journal_output_vat' => round($journalOutput, 2),
            'journal_input_vat' => round($journalInput, 2),
            'invoices' => $invoices,
            'purchases' => $purchases,
            'vat_account' => $vatOutput,
            'vat_input_account' => $vatInput,
        ];
    }

    public function suggestTaxFromSubtotal(float $subtotal, bool $cashNoTax = false): float
    {
        if ($cashNoTax || $subtotal <= 0) {
            return 0.0;
        }

        return round($subtotal * ($this->defaultRate() / 100), Money::decimals() > 0 ? 2 : 0);
    }

    private function accountNetCredit(int $accountId, GlPeriod $period): float
    {
        $credit = (float) GlJournalLine::query()
            ->where('account_id', $accountId)
            ->whereHas('journal', fn ($q) => $q->where('period_id', $period->id)->where('status', GlJournalStatus::Posted->value))
            ->sum('credit');
        $debit = (float) GlJournalLine::query()
            ->where('account_id', $accountId)
            ->whereHas('journal', fn ($q) => $q->where('period_id', $period->id)->where('status', GlJournalStatus::Posted->value))
            ->sum('debit');

        return $credit - $debit;
    }

    private function accountNetDebit(int $accountId, GlPeriod $period): float
    {
        return -$this->accountNetCredit($accountId, $period);
    }
}
