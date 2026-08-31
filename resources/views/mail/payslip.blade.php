<x-mail::message>
# Payslip for {{ $run->periodLabel() }}

Hello {{ $payslip->full_name }},

Your payslip for **{{ $run->period_start->format('d M Y') }} – {{ $run->period_end->format('d M Y') }}** is attached.

**Net pay:** {{ \App\Support\Money::format($payslip->net_pay, $run->currency) }}

Reference: {{ $run->reference }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
