@php
    $isTaxInvoice = (float) $invoice->tax_amount > 0;
    $documentTitle = $isTaxInvoice ? 'Tax Invoice' : 'Invoice';
    $company = config('psg.company', 'Platinum Security Group');
    $tagline = config('psg.tagline', 'New Age Security and Protection');
    $email = config('psg.support_email');
    $phone = config('psg.support_phone');
    $currency = $invoice->currency ?: config('psg.currency', 'UGX');
    $companyLogo = $companyLogo ?? null;
    $paymentTerms = $paymentTerms ?? '';
    $bankDetails = $bankDetails ?? null;
@endphp

@include('documents.partials.formal-styles')

<div class="invoice-doc">
    <div class="brand-bar"></div>

    <table class="header">
        <tr>
            <td class="brand-block">
                <table class="brand-table">
                    <tr>
                        @if (! empty($companyLogo))
                            <td class="brand-logo-cell">
                                <img src="{{ $companyLogo }}" alt="{{ $company }}" class="brand-logo">
                            </td>
                        @endif
                        <td>
                            <p class="company-name">{{ $company }}</p>
                            <p class="company-tagline">{{ $tagline }}</p>
                            @if (filled($email) || filled($phone))
                                <p class="company-contact">
                                    @if (filled($email)){{ $email }}@endif
                                    @if (filled($email) && filled($phone)) &nbsp;|&nbsp; @endif
                                    @if (filled($phone)){{ $phone }}@endif
                                </p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
            <td class="doc-panel">
                <p class="doc-title">{{ $documentTitle }}</p>
                <table class="doc-meta">
                    <tr>
                        <td class="k">Invoice No.</td>
                        <td class="v">{{ $invoice->reference }}</td>
                    </tr>
                    <tr>
                        <td class="k">Issue date</td>
                        <td class="v">{{ $invoice->issue_date?->format('d M Y') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="k">Due date</td>
                        <td class="v">{{ $invoice->due_date?->format('d M Y') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="k">Currency</td>
                        <td class="v">{{ $currency }}</td>
                    </tr>
                    <tr>
                        <td class="k">Status</td>
                        <td class="v">{{ $invoice->status->label() }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="party-box">
                    <p class="party-label">Bill to</p>
                    <p class="party-name">{{ $invoice->client?->name ?? '—' }}</p>
                    @if ($invoice->client?->contact_person)
                        <p class="party-line">{{ $invoice->client->contact_person }}</p>
                    @endif
                    @if ($invoice->client?->address)
                        <p class="party-line">{{ $invoice->client->address }}</p>
                    @endif
                    @if ($invoice->client?->phone)
                        <p class="party-line">Tel: {{ $invoice->client->phone }}</p>
                    @endif
                    @if ($invoice->client?->email)
                        <p class="party-line">{{ $invoice->client->email }}</p>
                    @endif
                </div>
            </td>
            <td>
                <div class="party-box">
                    <p class="party-label">Service details</p>
                    <p class="party-name">{{ $invoice->site?->name ?? 'All client sites' }}</p>
                    @if ($invoice->site?->code)
                        <p class="party-line">Site code: {{ $invoice->site->code }}</p>
                    @endif
                    <p class="party-line">
                        Period: {{ $invoice->period_start->format('d M Y') }} – {{ $invoice->period_end->format('d M Y') }}
                    </p>
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 6%;">#</th>
                <th>Description</th>
                <th class="num" style="width: 11%;">Qty</th>
                <th class="num" style="width: 18%;">Unit price</th>
                <th class="num" style="width: 18%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->lines as $line)
                <tr>
                    <td class="muted">{{ $loop->iteration }}</td>
                    <td class="desc">{{ $line->description }}</td>
                    <td class="num">{{ number_format((float) $line->quantity, 2) }}</td>
                    <td class="num">{{ \App\Support\Money::format($line->unit_price, $currency) }}</td>
                    <td class="num amount">{{ \App\Support\Money::format($line->line_total, $currency) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted" style="text-align: center; padding: 16px;">No line items.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="lower">
        <tr>
            <td class="notes-col">
                <p class="section-label">Notes</p>
                @if ($invoice->notes)
                    <p class="notes-text">{{ $invoice->notes }}</p>
                @else
                    <p class="notes-text" style="color:#94a3b8;">
                        @if ($isTaxInvoice)
                            This is a tax invoice. Please retain for your records.
                        @else
                            No VAT charged on this invoice. Please retain for your records.
                        @endif
                    </p>
                @endif
            </td>
            <td class="totals-col">
                <table class="totals">
                    <tr>
                        <td class="label">Subtotal</td>
                        <td class="value">{{ \App\Support\Money::format($invoice->subtotal, $currency) }}</td>
                    </tr>
                    <tr class="divider">
                        <td class="label">VAT</td>
                        <td class="value">{{ \App\Support\Money::format($invoice->tax_amount, $currency) }}</td>
                    </tr>
                    <tr class="total">
                        <td class="label">Total</td>
                        <td class="value">{{ \App\Support\Money::format($invoice->total, $currency) }}</td>
                    </tr>
                    <tr class="divider">
                        <td class="label">Amount paid</td>
                        <td class="value">{{ \App\Support\Money::format($invoice->amount_paid, $currency) }}</td>
                    </tr>
                    <tr class="due">
                        <td class="label">Balance due</td>
                        <td class="value">{{ \App\Support\Money::format($invoice->balance, $currency) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="payment-box">
        <p class="section-label">Payment instructions</p>
        <p class="payment-text">{{ $paymentTerms }}</p>
        @if (! $isTaxInvoice)
            <p class="payment-text">No VAT is charged on this invoice. Settle the full balance due.</p>
        @endif
        @if ($bankDetails)
            <p class="payment-bank">Bank details: {{ $bankDetails }}</p>
        @endif
        <p class="payment-text" style="margin-top: 6px; margin-bottom: 0;">
            Please quote invoice reference <strong>{{ $invoice->reference }}</strong> on all payments.
        </p>
    </div>

    <table class="signatures">
        <tr>
            <td>
                <p class="section-label">Prepared by</p>
                <div class="sig-line"></div>
                <p class="sig-caption">Authorized signature · {{ $company }}</p>
            </td>
            <td>
                <p class="section-label">Received by</p>
                <div class="sig-line"></div>
                <p class="sig-caption">Client name / signature / date</p>
            </td>
        </tr>
    </table>

    <p class="doc-footer">
        {{ config('psg.email_footer') ?: ($isTaxInvoice
            ? 'Computer-generated tax invoice from '.$company.'.'
            : 'Computer-generated invoice from '.$company.'.') }}
    </p>
</div>
