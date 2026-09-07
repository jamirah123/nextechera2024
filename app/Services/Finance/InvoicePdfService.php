<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Services\SystemSettingService;
use App\Support\Documents\DompdfRenderer;
use Illuminate\Support\Facades\Storage;

class InvoicePdfService
{
    public function __construct(
        private DompdfRenderer $pdf,
        private SystemSettingService $settings,
    ) {
    }

    public function renderBinary(Invoice $invoice): string
    {
        return $this->pdf->renderView('documents.pdf.invoice', $this->viewData($invoice, embedLogo: true));
    }

    /**
     * @return array{invoice: Invoice, paymentTerms: string, bankDetails: string|null, companyLogo: string|null}
     */
    public function viewData(Invoice $invoice, bool $embedLogo = false): array
    {
        $invoice->loadMissing(['client', 'site', 'lines']);

        return [
            'invoice' => $invoice,
            'paymentTerms' => $this->paymentTerms($invoice),
            'bankDetails' => $this->bankDetails(),
            'companyLogo' => $embedLogo
                ? ($this->companyLogoDataUri() ?? $this->companyLogoUrl())
                : $this->companyLogoUrl(),
        ];
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

    private function companyLogoDataUri(): ?string
    {
        $absolutePath = $this->companyLogoAbsolutePath();

        if ($absolutePath === null || ! is_readable($absolutePath)) {
            return null;
        }

        $binary = @file_get_contents($absolutePath);

        if ($binary === false || $binary === '') {
            return null;
        }

        $mime = @mime_content_type($absolutePath) ?: 'image/jpeg';

        if (! str_starts_with($mime, 'image/')) {
            $mime = 'image/jpeg';
        }

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }

    public function companyLogoUrl(): ?string
    {
        $settings = $this->settings->current();

        if (filled($settings->logo_path) && Storage::disk('public')->exists($settings->logo_path)) {
            return $settings->resolvedLogoUrl();
        }

        $fallback = public_path(config('psg.fallback_logo', 'images/logo.jpeg'));

        return is_file($fallback) ? asset(config('psg.fallback_logo', 'images/logo.jpeg')) : null;
    }

    private function companyLogoAbsolutePath(): ?string
    {
        $settings = $this->settings->current();

        if (filled($settings->logo_path) && Storage::disk('public')->exists($settings->logo_path)) {
            return Storage::disk('public')->path($settings->logo_path);
        }

        $fallback = public_path(config('psg.fallback_logo', 'images/logo.jpeg'));

        return is_file($fallback) ? $fallback : null;
    }
}
