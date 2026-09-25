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

<style>
    .invoice-doc {
        background: #fff;
        color: #0f172a;
        font-family: DejaVu Sans, sans-serif;
        font-size: 10px;
        line-height: 1.45;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .invoice-doc table { border-collapse: collapse; }
    .invoice-doc p { margin: 0; }
    .invoice-doc .brand-bar {
        background: #1E5D48;
        height: 4px;
        margin: 0 0 14px;
        width: 100%;
    }
    .invoice-doc .header { margin-bottom: 16px; width: 100%; }
    .invoice-doc .header td { vertical-align: top; }
    .invoice-doc .brand-block { width: 54%; }
    .invoice-doc .brand-table { width: 100%; }
    .invoice-doc .brand-table td { vertical-align: middle; }
    .invoice-doc .brand-logo-cell { padding-right: 10px; width: 72px; }
    .invoice-doc .brand-logo {
        display: block;
        height: 54px;
        max-width: 68px;
        object-fit: contain;
        width: auto;
    }
    .invoice-doc .company-name {
        color: #0f172a;
        font-size: 16px;
        font-weight: 700;
        letter-spacing: -0.02em;
        margin: 0;
    }
    .invoice-doc .company-tagline {
        color: #8B1E1E;
        font-size: 8px;
        font-weight: 700;
        letter-spacing: 0.14em;
        margin: 3px 0 0;
        text-transform: uppercase;
    }
    .invoice-doc .company-contact {
        color: #64748b;
        font-size: 8.5px;
        margin-top: 6px;
    }
    .invoice-doc .doc-panel {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-left: 3px solid #1E5D48;
        padding: 10px 12px;
        text-align: right;
        width: 46%;
    }
    .invoice-doc .doc-title {
        color: #0f172a;
        font-size: 18px;
        font-weight: 700;
        letter-spacing: 0.04em;
        margin: 0;
        text-transform: uppercase;
    }
    .invoice-doc .doc-meta { margin-top: 8px; width: 100%; }
    .invoice-doc .doc-meta td {
        font-size: 9px;
        padding: 1px 0;
        vertical-align: top;
    }
    .invoice-doc .doc-meta .k {
        color: #64748b;
        padding-right: 10px;
        text-align: left;
        white-space: nowrap;
        width: 42%;
    }
    .invoice-doc .doc-meta .v {
        color: #0f172a;
        font-weight: 700;
        text-align: right;
    }
    .invoice-doc .parties { margin-bottom: 14px; width: 100%; }
    .invoice-doc .parties > tbody > tr > td {
        vertical-align: top;
        width: 50%;
    }
    .invoice-doc .parties > tbody > tr > td:first-child { padding-right: 10px; }
    .invoice-doc .parties > tbody > tr > td:last-child { padding-left: 10px; }
    .invoice-doc .party-box {
        border: 1px solid #e2e8f0;
        min-height: 78px;
        padding: 9px 11px;
    }
    .invoice-doc .party-label {
        color: #1E5D48;
        font-size: 8px;
        font-weight: 700;
        letter-spacing: 0.12em;
        margin: 0 0 6px;
        text-transform: uppercase;
    }
    .invoice-doc .party-name {
        font-size: 11px;
        font-weight: 700;
        margin: 0 0 3px;
    }
    .invoice-doc .party-line {
        color: #475569;
        font-size: 9px;
        margin: 1px 0 0;
    }
    .invoice-doc .items { margin: 0 0 8px; width: 100%; }
    .invoice-doc .items thead th {
        background: #1E5D48;
        border: 1px solid #1E5D48;
        color: #ffffff;
        font-size: 8px;
        font-weight: 700;
        letter-spacing: 0.08em;
        padding: 7px 8px;
        text-align: left;
        text-transform: uppercase;
    }
    .invoice-doc .items thead th.num { text-align: right; }
    .invoice-doc .items tbody td {
        border-bottom: 1px solid #e2e8f0;
        border-left: 1px solid #e2e8f0;
        border-right: 1px solid #e2e8f0;
        font-size: 9.5px;
        padding: 7px 8px;
        vertical-align: top;
    }
    .invoice-doc .items tbody tr:nth-child(even) td { background: #f8fafc; }
    .invoice-doc .items .muted { color: #94a3b8; }
    .invoice-doc .items .desc { color: #0f172a; }
    .invoice-doc .items .num { text-align: right; white-space: nowrap; }
    .invoice-doc .items .amount { font-weight: 700; }
    .invoice-doc .lower { margin-top: 4px; width: 100%; }
    .invoice-doc .lower td { vertical-align: top; }
    .invoice-doc .notes-col { padding-right: 14px; width: 54%; }
    .invoice-doc .totals-col { width: 46%; }
    .invoice-doc .totals { border: 1px solid #e2e8f0; width: 100%; }
    .invoice-doc .totals td { font-size: 9.5px; padding: 6px 10px; }
    .invoice-doc .totals .label { color: #64748b; text-align: right; width: 55%; }
    .invoice-doc .totals .value {
        border-left: 1px solid #e2e8f0;
        font-weight: 600;
        text-align: right;
        white-space: nowrap;
    }
    .invoice-doc .totals tr.divider td { border-top: 1px solid #e2e8f0; }
    .invoice-doc .totals tr.total td {
        background: #ecfdf5;
        border-top: 1px solid #a7f3d0;
        color: #14532d;
        font-size: 11px;
        font-weight: 700;
        padding-top: 8px;
        padding-bottom: 8px;
    }
    .invoice-doc .totals tr.due td {
        background: #fff1f2;
        border-top: 1px solid #fecdd3;
        color: #9f1239;
        font-size: 11px;
        font-weight: 700;
        padding-top: 8px;
        padding-bottom: 8px;
    }
    .invoice-doc .section-label {
        color: #1E5D48;
        font-size: 8px;
        font-weight: 700;
        letter-spacing: 0.12em;
        margin: 0 0 5px;
        text-transform: uppercase;
    }
    .invoice-doc .notes-text { color: #334155; font-size: 9px; line-height: 1.5; }
    .invoice-doc .payment-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        margin-top: 14px;
        padding: 10px 12px;
    }
    .invoice-doc .payment-box .section-label { margin-bottom: 6px; }
    .invoice-doc .payment-text { color: #334155; font-size: 9px; margin: 0 0 4px; }
    .invoice-doc .payment-bank {
        color: #0f172a;
        font-size: 9px;
        font-weight: 700;
        margin: 6px 0 0;
    }
    .invoice-doc .signatures { margin-top: 28px; width: 100%; }
    .invoice-doc .signatures td {
        padding-top: 8px;
        vertical-align: top;
        width: 50%;
    }
    .invoice-doc .signatures td:first-child { padding-right: 18px; }
    .invoice-doc .signatures td:last-child { padding-left: 18px; }
    .invoice-doc .sig-line { border-top: 1px solid #94a3b8; margin-top: 34px; }
    .invoice-doc .sig-caption { color: #64748b; font-size: 8.5px; margin-top: 5px; }
    .invoice-doc .doc-footer {
        border-top: 1px solid #e2e8f0;
        color: #94a3b8;
        font-size: 8px;
        margin-top: 22px;
        padding-top: 8px;
        text-align: center;
    }

    /* On-screen dark mode (app preview). Print/PDF stay light paper. */
    html.dark .invoice-doc {
        background: #0f172a;
        color: #e2e8f0;
    }
    html.dark .invoice-doc .company-name,
    html.dark .invoice-doc .doc-title,
    html.dark .invoice-doc .doc-meta .v,
    html.dark .invoice-doc .party-name,
    html.dark .invoice-doc .items .desc,
    html.dark .invoice-doc .payment-bank {
        color: #f8fafc;
    }
    html.dark .invoice-doc .company-contact,
    html.dark .invoice-doc .doc-meta .k,
    html.dark .invoice-doc .party-line,
    html.dark .invoice-doc .totals .label,
    html.dark .invoice-doc .sig-caption,
    html.dark .invoice-doc .doc-footer,
    html.dark .invoice-doc .items .muted {
        color: #94a3b8;
    }
    html.dark .invoice-doc .notes-text,
    html.dark .invoice-doc .payment-text {
        color: #cbd5e1;
    }
    html.dark .invoice-doc .doc-panel,
    html.dark .invoice-doc .payment-box {
        background: #1e293b;
        border-color: #334155;
    }
    html.dark .invoice-doc .party-box,
    html.dark .invoice-doc .totals {
        border-color: #334155;
    }
    html.dark .invoice-doc .items tbody td {
        border-color: #334155;
    }
    html.dark .invoice-doc .items tbody tr:nth-child(even) td {
        background: #1e293b;
    }
    html.dark .invoice-doc .totals .value {
        border-left-color: #334155;
    }
    html.dark .invoice-doc .totals tr.divider td {
        border-top-color: #334155;
    }
    html.dark .invoice-doc .totals tr.total td {
        background: #064e3b;
        border-top-color: #059669;
        color: #a7f3d0;
    }
    html.dark .invoice-doc .totals tr.due td {
        background: #4c0519;
        border-top-color: #be123c;
        color: #fecdd3;
    }
    html.dark .invoice-doc .sig-line {
        border-top-color: #64748b;
    }
    html.dark .invoice-doc .doc-footer {
        border-top-color: #334155;
    }

    @media print {
        html.dark .invoice-doc {
            background: #fff !important;
            color: #0f172a !important;
        }
        html.dark .invoice-doc .company-name,
        html.dark .invoice-doc .doc-title,
        html.dark .invoice-doc .doc-meta .v,
        html.dark .invoice-doc .party-name,
        html.dark .invoice-doc .items .desc,
        html.dark .invoice-doc .payment-bank {
            color: #0f172a !important;
        }
        html.dark .invoice-doc .doc-panel,
        html.dark .invoice-doc .payment-box,
        html.dark .invoice-doc .items tbody tr:nth-child(even) td {
            background: #f8fafc !important;
        }
        html.dark .invoice-doc .totals tr.total td {
            background: #ecfdf5 !important;
            color: #14532d !important;
        }
        html.dark .invoice-doc .totals tr.due td {
            background: #fff1f2 !important;
            color: #9f1239 !important;
        }
    }
</style>

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
