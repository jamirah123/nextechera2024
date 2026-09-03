@php
    $isTaxInvoice = (float) $invoice->tax_amount > 0;
    $documentTitle = $isTaxInvoice ? 'Tax Invoice' : 'Invoice';
    $company = config('psg.company', 'Platinum Security Group');
    $tagline = config('psg.tagline', 'New Age Security and Protection');
    $email = config('psg.support_email');
    $phone = config('psg.support_phone');
    $currency = $invoice->currency ?: config('psg.currency', 'UGX');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $documentTitle }} {{ $invoice->reference }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            color: #0f172a;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            line-height: 1.45;
            margin: 0;
            padding: 24px 28px 20px;
        }
        table { border-collapse: collapse; }
        p { margin: 0; }

        .brand-bar {
            background: #1E5D48;
            height: 4px;
            margin: 0 0 14px;
            width: 100%;
        }
        .header { margin-bottom: 16px; width: 100%; }
        .header td { vertical-align: top; }
        .brand-block { width: 54%; }
        .brand-table { width: 100%; }
        .brand-table td { vertical-align: middle; }
        .brand-logo-cell { padding-right: 10px; width: 72px; }
        .brand-logo {
            display: block;
            height: 54px;
            max-width: 68px;
            object-fit: contain;
            width: auto;
        }
        .company-name {
            color: #0f172a;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin: 0;
        }
        .company-tagline {
            color: #8B1E1E;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.14em;
            margin: 3px 0 0;
            text-transform: uppercase;
        }
        .company-contact {
            color: #64748b;
            font-size: 8.5px;
            margin-top: 6px;
        }
        .doc-panel {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 3px solid #1E5D48;
            padding: 10px 12px;
            text-align: right;
            width: 46%;
        }
        .doc-title {
            color: #0f172a;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.04em;
            margin: 0;
            text-transform: uppercase;
        }
        .doc-meta {
            margin-top: 8px;
            width: 100%;
        }
        .doc-meta td {
            font-size: 9px;
            padding: 1px 0;
            vertical-align: top;
        }
        .doc-meta .k {
            color: #64748b;
            padding-right: 10px;
            text-align: left;
            white-space: nowrap;
            width: 42%;
        }
        .doc-meta .v {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
        }

        .parties { margin-bottom: 14px; width: 100%; }
        .parties > tbody > tr > td {
            vertical-align: top;
            width: 50%;
        }
        .parties > tbody > tr > td:first-child { padding-right: 10px; }
        .parties > tbody > tr > td:last-child { padding-left: 10px; }
        .party-box {
            border: 1px solid #e2e8f0;
            min-height: 78px;
            padding: 9px 11px;
        }
        .party-label {
            color: #1E5D48;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.12em;
            margin: 0 0 6px;
            text-transform: uppercase;
        }
        .party-name {
            font-size: 11px;
            font-weight: 700;
            margin: 0 0 3px;
        }
        .party-line {
            color: #475569;
            font-size: 9px;
            margin: 1px 0 0;
        }

        .items {
            margin: 0 0 8px;
            width: 100%;
        }
        .items thead th {
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
        .items thead th.num { text-align: right; }
        .items tbody td {
            border-bottom: 1px solid #e2e8f0;
            border-left: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
            font-size: 9.5px;
            padding: 7px 8px;
            vertical-align: top;
        }
        .items tbody tr:nth-child(even) td { background: #f8fafc; }
        .items .muted { color: #94a3b8; }
        .items .desc { color: #0f172a; }
        .items .num { text-align: right; white-space: nowrap; }
        .items .amount { font-weight: 700; }

        .lower { margin-top: 4px; width: 100%; }
        .lower td { vertical-align: top; }
        .notes-col { padding-right: 14px; width: 54%; }
        .totals-col { width: 46%; }
        .totals {
            border: 1px solid #e2e8f0;
            width: 100%;
        }
        .totals td {
            font-size: 9.5px;
            padding: 6px 10px;
        }
        .totals .label {
            color: #64748b;
            text-align: right;
            width: 55%;
        }
        .totals .value {
            border-left: 1px solid #e2e8f0;
            font-weight: 600;
            text-align: right;
            white-space: nowrap;
        }
        .totals tr.divider td {
            border-top: 1px solid #e2e8f0;
        }
        .totals tr.total td {
            background: #ecfdf5;
            border-top: 1px solid #a7f3d0;
            color: #14532d;
            font-size: 11px;
            font-weight: 700;
            padding-top: 8px;
            padding-bottom: 8px;
        }
        .totals tr.due td {
            background: #fff1f2;
            border-top: 1px solid #fecdd3;
            color: #9f1239;
            font-size: 11px;
            font-weight: 700;
            padding-top: 8px;
            padding-bottom: 8px;
        }

        .section-label {
            color: #1E5D48;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.12em;
            margin: 0 0 5px;
            text-transform: uppercase;
        }
        .notes-text {
            color: #334155;
            font-size: 9px;
            line-height: 1.5;
        }

        .payment-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            margin-top: 14px;
            padding: 10px 12px;
        }
        .payment-box .section-label { margin-bottom: 6px; }
        .payment-text {
            color: #334155;
            font-size: 9px;
            margin: 0 0 4px;
        }
        .payment-bank {
            color: #0f172a;
            font-size: 9px;
            font-weight: 700;
            margin: 6px 0 0;
        }

        .signatures {
            margin-top: 28px;
            width: 100%;
        }
        .signatures td {
            padding-top: 8px;
            vertical-align: top;
            width: 50%;
        }
        .signatures td:first-child { padding-right: 18px; }
        .signatures td:last-child { padding-left: 18px; }
        .sig-line {
            border-top: 1px solid #94a3b8;
            margin-top: 34px;
        }
        .sig-caption {
            color: #64748b;
            font-size: 8.5px;
            margin-top: 5px;
        }

        .footer {
            border-top: 1px solid #e2e8f0;
            color: #94a3b8;
            font-size: 8px;
            margin-top: 22px;
            padding-top: 8px;
            text-align: center;
        }
    </style>
</head>
<body>
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
                @if ($invoice->notes)
                    <p class="section-label">Notes</p>
                    <p class="notes-text">{{ $invoice->notes }}</p>
                @else
                    <p class="section-label">Notes</p>
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

    <p class="footer">
        {{ config('psg.email_footer') ?: ($isTaxInvoice
            ? 'Computer-generated tax invoice from '.$company.'.'
            : 'Computer-generated invoice from '.$company.'.') }}
    </p>
</body>
</html>
