<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payslip — {{ $payslip->employment_id }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: 12px;
            line-height: 1.45;
            margin: 0;
            padding: 24px;
        }
        .header {
            border-bottom: 3px solid #1E5D48;
            margin-bottom: 18px;
            padding-bottom: 14px;
        }
        .company-name {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }
        .tagline {
            color: #8B1E1E;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.12em;
            margin: 4px 0 0;
            text-transform: uppercase;
        }
        .contact {
            color: #64748b;
            font-size: 10px;
            margin-top: 8px;
        }
        .doc-title {
            color: #1E5D48;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.12em;
            margin: 0;
            text-align: right;
            text-transform: uppercase;
        }
        .doc-heading {
            font-size: 22px;
            font-weight: 700;
            margin: 4px 0 0;
            text-align: right;
        }
        .doc-ref {
            color: #64748b;
            font-family: DejaVu Sans Mono, monospace;
            font-size: 11px;
            margin-top: 4px;
            text-align: right;
        }
        .meta-grid {
            margin: 16px 0 20px;
            width: 100%;
        }
        .meta-grid td {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px 12px;
            vertical-align: top;
            width: 50%;
        }
        .meta-label {
            color: #64748b;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.08em;
            margin: 0 0 4px;
            text-transform: uppercase;
        }
        .meta-value {
            font-size: 12px;
            font-weight: 700;
            margin: 0;
        }
        .section-title {
            color: #64748b;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.08em;
            margin: 0 0 8px;
            text-transform: uppercase;
        }
        .panel {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            margin-bottom: 14px;
            padding: 12px 14px;
        }
        .two-col {
            width: 100%;
        }
        .two-col td {
            vertical-align: top;
            width: 50%;
        }
        .two-col td:first-child {
            padding-right: 8px;
        }
        .two-col td:last-child {
            padding-left: 8px;
        }
        .row {
            margin-bottom: 6px;
            overflow: hidden;
        }
        .row-label {
            color: #475569;
            float: left;
        }
        .row-value {
            float: right;
            font-weight: 600;
        }
        .divider {
            border-top: 1px solid #cbd5e1;
            margin-top: 8px;
            padding-top: 8px;
        }
        .net-row .row-label,
        .net-row .row-value {
            color: #1E5D48;
            font-size: 14px;
            font-weight: 700;
        }
        table.deductions {
            border-collapse: collapse;
            margin-top: 8px;
            width: 100%;
        }
        table.deductions th {
            background: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.08em;
            padding: 8px 10px;
            text-align: left;
            text-transform: uppercase;
        }
        table.deductions td {
            border-bottom: 1px solid #f1f5f9;
            padding: 8px 10px;
        }
        table.deductions td.amount {
            text-align: right;
            white-space: nowrap;
        }
        .footer {
            border-top: 1px solid #e2e8f0;
            color: #94a3b8;
            font-size: 9px;
            margin-top: 24px;
            padding-top: 10px;
            text-align: center;
        }
        .status {
            background: #ecfdf5;
            border: 1px solid #bbf7d0;
            border-radius: 999px;
            color: #166534;
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 10px;
        }
    </style>
</head>
<body>
@php
    $company = config('psg.company', 'Platinum Security Group');
    $email = config('psg.support_email');
    $phone = config('psg.support_phone');
    $eligibleDays = null;
    $periodDays = \App\Support\Finance\PayrollRates::periodDays($run);

    if ($payslip->isFixedSalary()) {
        $eligibleDays = $payslip->assignedStaff
            ? \App\Support\Finance\PayrollRates::staffEligibleDays($payslip->assignedStaff, $run)
            : ($payslip->assignedGuard
                ? \App\Support\Finance\PayrollRates::guardEligibleDays($payslip->assignedGuard, $run)
                : null);
    }
@endphp

<table width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td>
            <p class="company-name">{{ $company }}</p>
            @if (filled(config('psg.tagline')))
                <p class="tagline">{{ config('psg.tagline') }}</p>
            @endif
            @if (filled($email) || filled($phone))
                <p class="contact">
                    @if (filled($email)){{ $email }}@endif
                    @if (filled($email) && filled($phone)) · @endif
                    @if (filled($phone)){{ $phone }}@endif
                </p>
            @endif
        </td>
        <td>
            <p class="doc-title">Official document</p>
            <p class="doc-heading">Payslip</p>
            <p class="doc-ref">{{ $run->reference }}</p>
            <p style="margin-top: 8px; text-align: right;"><span class="status">{{ $run->status->label() }}</span></p>
        </td>
    </tr>
</table>

<div class="header"></div>

<p style="font-size: 14px; font-weight: 700; margin: 0 0 4px;">{{ $payslip->full_name }}</p>
<p style="color: #64748b; font-family: DejaVu Sans Mono, monospace; font-size: 11px; margin: 0 0 16px;">{{ $payslip->employment_id }}</p>

<table class="meta-grid" cellpadding="0" cellspacing="8">
    <tr>
        <td>
            <p class="meta-label">Pay period</p>
            <p class="meta-value">{{ $run->period_start->format('d M Y') }} – {{ $run->period_end->format('d M Y') }}</p>
        </td>
        <td>
            <p class="meta-label">Bank details</p>
            <p class="meta-value">{{ trim(($payslip->bank_name ?? '—').($payslip->bank_account ? ' · '.$payslip->bank_account : '')) }}</p>
        </td>
    </tr>
    @if ($payslip->nssf_number || $payslip->tin_number)
    <tr>
        <td>
            <p class="meta-label">NSSF number</p>
            <p class="meta-value">{{ $payslip->nssf_number ?? '—' }}</p>
        </td>
        <td>
            <p class="meta-label">TIN number</p>
            <p class="meta-value">{{ $payslip->tin_number ?? '—' }}</p>
        </td>
    </tr>
    @endif
</table>

<table class="two-col" cellpadding="0" cellspacing="0">
    <tr>
        <td>
            <div class="panel">
                <p class="section-title">{{ $payslip->isFixedSalary() ? 'Salary earnings' : 'Shift earnings' }}</p>
                @if ($payslip->isFixedSalary())
                    <div class="row">
                        <span class="row-label">Monthly gross</span>
                        <span class="row-value">{{ \App\Support\Money::format($payslip->base_shift_rate, $run->currency) }}</span>
                    </div>
                    @if ($eligibleDays !== null && $eligibleDays < $periodDays)
                        <div class="row">
                            <span class="row-label">Days paid</span>
                            <span class="row-value">{{ $eligibleDays }} of {{ $periodDays }}</span>
                        </div>
                    @endif
                    @php
                        $overtimePay = round((float) $payslip->overtime_shifts * (float) $payslip->overtime_shift_rate, 2);
                        $salaryPortion = round((float) $payslip->gross_pay - $overtimePay, 2);
                    @endphp
                    <div class="row">
                        <span class="row-label">Period salary</span>
                        <span class="row-value">{{ \App\Support\Money::format($salaryPortion, $run->currency) }}</span>
                    </div>
                    @if ($payslip->overtime_shifts > 0)
                        <div class="row">
                            <span class="row-label">Overtime ({{ $payslip->overtime_shifts }})</span>
                            <span class="row-value">{{ \App\Support\Money::format($overtimePay, $run->currency) }}</span>
                        </div>
                    @endif
                @else
                    <div class="row">
                        <span class="row-label">Normal ({{ $payslip->normal_shifts }})</span>
                        <span class="row-value">{{ \App\Support\Money::format($payslip->normal_shifts * $payslip->base_shift_rate, $run->currency) }}</span>
                    </div>
                    <div class="row">
                        <span class="row-label">Overtime ({{ $payslip->overtime_shifts }})</span>
                        <span class="row-value">{{ \App\Support\Money::format($payslip->overtime_shifts * $payslip->overtime_shift_rate, $run->currency) }}</span>
                    </div>
                    @if ($payslip->relief_shifts || $payslip->replacement_shifts || $payslip->special_duty_shifts)
                        <div class="row">
                            <span class="row-label">Other shifts</span>
                            <span class="row-value">{{ $payslip->relief_shifts + $payslip->replacement_shifts + $payslip->special_duty_shifts }}</span>
                        </div>
                    @endif
                @endif
                <div class="row divider">
                    <span class="row-label">Gross pay</span>
                    <span class="row-value">{{ \App\Support\Money::format($payslip->gross_pay, $run->currency) }}</span>
                </div>
            </div>
        </td>
        <td>
            <div class="panel">
                <p class="section-title">Summary</p>
                @if ($payslip->isFixedSalary())
                    <div class="row">
                        <span class="row-label">Pay type</span>
                        <span class="row-value">{{ $payslip->compensation_type->shortLabel() }}</span>
                    </div>
                @else
                    <div class="row">
                        <span class="row-label">Total shifts</span>
                        <span class="row-value">{{ $payslip->total_shifts }}</span>
                    </div>
                @endif
                <div class="row">
                    <span class="row-label">Deductions</span>
                    <span class="row-value">{{ \App\Support\Money::format($payslip->total_deductions, $run->currency) }}</span>
                </div>
                <div class="row divider net-row">
                    <span class="row-label">Net pay</span>
                    <span class="row-value">{{ \App\Support\Money::format($payslip->net_pay, $run->currency) }}</span>
                </div>
            </div>
        </td>
    </tr>
</table>

<p class="section-title">Deductions</p>
<table class="deductions">
    <thead>
        <tr>
            <th>Description</th>
            <th style="text-align: right;">Amount ({{ $run->currency }})</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($payslip->deductions as $deduction)
            <tr>
                <td>{{ $deduction->label }}</td>
                <td class="amount">{{ \App\Support\Money::format($deduction->amount, $run->currency) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="2" style="color: #94a3b8; text-align: center;">No deductions recorded.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<p class="footer">
    Confidential payroll document generated by {{ $company }} · {{ now()->format('d M Y H:i') }}<br>
    This payslip is computer-generated and valid without a signature.
</p>
</body>
</html>
