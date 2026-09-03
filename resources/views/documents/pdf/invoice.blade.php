<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->reference }}</title>
    @include('documents.pdf.partials.styles')
</head>
<body>
    @include('documents.pdf.partials.letterhead', [
        'documentTitle' => ((float) $invoice->tax_amount > 0) ? 'Tax Invoice' : 'Invoice',
        'documentReference' => $invoice->reference,
        'documentDate' => $invoice->issue_date?->format('d M Y'),
    ])

    <table class="meta-table">
        <tr>
            <td>
                <p class="meta-label">Bill to</p>
                <p class="meta-value">{{ $invoice->client?->name }}</p>
                @if ($invoice->client?->contact_person)
                    <p class="meta-hint">{{ $invoice->client->contact_person }}</p>
                @endif
                @if ($invoice->client?->email)
                    <p class="meta-hint">{{ $invoice->client->email }}</p>
                @endif
            </td>
            <td>
                <p class="meta-label">Service location</p>
                <p class="meta-value">{{ $invoice->site?->name ?? 'All client sites' }}</p>
                @if ($invoice->site?->code)
                    <p class="meta-hint">{{ $invoice->site->code }}</p>
                @endif
            </td>
        </tr>
        <tr>
            <td>
                <p class="meta-label">Billing period</p>
                <p class="meta-value">{{ $invoice->period_start->format('d M Y') }} – {{ $invoice->period_end->format('d M Y') }}</p>
            </td>
            <td>
                <p class="meta-label">Status / due date</p>
                <p class="meta-value">{{ $invoice->status->label() }}</p>
                @if ($invoice->due_date)
                    <p class="meta-hint">Due {{ $invoice->due_date->format('d M Y') }}</p>
                @endif
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 6%;">#</th>
                <th>Description</th>
                <th class="text-right" style="width: 12%;">Qty</th>
                <th class="text-right" style="width: 18%;">Unit</th>
                <th class="text-right" style="width: 18%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->lines as $line)
                <tr>
                    <td class="text-muted">{{ $loop->iteration }}</td>
                    <td>{{ $line->description }}</td>
                    <td class="text-right">{{ number_format((float) $line->quantity, 2) }}</td>
                    <td class="text-right">{{ \App\Support\Money::format($line->unit_price, $invoice->currency) }}</td>
                    <td class="text-right">{{ \App\Support\Money::format($line->line_total, $invoice->currency) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted">No line items.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-right text-muted">Subtotal</td>
                <td class="text-right">{{ \App\Support\Money::format($invoice->subtotal, $invoice->currency) }}</td>
            </tr>
            <tr>
                <td colspan="4" class="text-right text-muted">VAT</td>
                <td class="text-right">{{ \App\Support\Money::format($invoice->tax_amount, $invoice->currency) }}</td>
            </tr>
            <tr>
                <td colspan="4" class="text-right">Total</td>
                <td class="text-right amount-total">{{ \App\Support\Money::format($invoice->total, $invoice->currency) }}</td>
            </tr>
            <tr>
                <td colspan="4" class="text-right text-muted">Paid</td>
                <td class="text-right">{{ \App\Support\Money::format($invoice->amount_paid, $invoice->currency) }}</td>
            </tr>
            <tr>
                <td colspan="4" class="text-right">Balance due</td>
                <td class="text-right amount-due">{{ \App\Support\Money::format($invoice->balance, $invoice->currency) }}</td>
            </tr>
        </tfoot>
    </table>

    @if ($invoice->notes)
        <p class="section-title">Notes</p>
        <p class="body-text">{{ $invoice->notes }}</p>
    @endif

    <div class="payment-box">
        <p class="section-title" style="margin-top: 0;">Payment terms &amp; bank details</p>
        <p class="body-text">{{ $paymentTerms }}</p>
        @if ((float) $invoice->tax_amount <= 0)
            <p class="body-text">No VAT charged on this invoice. Settle the full balance due.</p>
        @endif
        @if ($bankDetails)
            <p class="body-text" style="margin-bottom: 0;"><strong>Bank:</strong> {{ $bankDetails }}</p>
        @endif
    </div>

    <table class="signature-grid">
        <tr>
            <td>
                <p class="meta-label">Prepared by</p>
                <div class="signature-line"></div>
                <p class="signature-caption">Authorized signature · {{ config('psg.company') }}</p>
            </td>
            <td>
                <p class="meta-label">Client acknowledgment</p>
                <div class="signature-line"></div>
                <p class="signature-caption">Name / signature / date</p>
            </td>
        </tr>
    </table>

    <p class="footer-note">{{ config('psg.email_footer') ?: (((float) $invoice->tax_amount > 0) ? 'This is a computer-generated tax invoice.' : 'This is a computer-generated invoice.') }}</p>
</body>
</html>
