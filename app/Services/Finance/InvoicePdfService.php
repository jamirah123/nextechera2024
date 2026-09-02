<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Support\Documents\DompdfRenderer;

class InvoicePdfService
{
    public function __construct(private DompdfRenderer $pdf)
    {
    }

    public function renderBinary(Invoice $invoice): string
    {
        $invoice->loadMissing(['client', 'site', 'lines']);

        return $this->pdf->renderView('documents.pdf.invoice', [
            'invoice' => $invoice,
            'paymentTerms' => $this->paymentTerms($invoice),
            'bankDetails' => $this->bankDetails(),
        ]);
    }

    public function filename(Invoice $invoice): string
    {
        return 'invoice-'.$invoice->reference.'.pdf';
    }

    private function paymentTerms(Invoice $invoice): string
    {
        $custom = config('psg.invoice_payment_terms');

        if (filled($custom)) {
            return (string) $custom;
        }

        $due = $invoice->due_date?->format('d M Y') ?? 'the stated due date';

        return 'Please settle the balance due by '.$due.'. Bank transfers must quote invoice reference '.$invoice->reference.'.';
    }

    private function bankDetails(): ?string
    {
        $parts = array_filter([
            config('psg.company_bank_name'),
            config('psg.company_bank_branch'),
            config('psg.company_bank_account') ? 'Account: '.config('psg.company_bank_account') : null,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
