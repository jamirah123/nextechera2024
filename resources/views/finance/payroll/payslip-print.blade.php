@extends('layouts.print')

@section('title', 'Payslip — '.$payslip->employment_id)

@section('content')
<div class="mx-auto max-w-3xl">
    <x-finance.document
        :title="'Payslip'"
        :reference="$run->reference"
        :subtitle="$payslip->full_name.' · '.$payslip->employment_id"
        :status-tone="$run->status->tone()"
        :status-label="$run->status->label()"
        :meta="[[
            'label' => 'Pay period',
            'value' => $run->period_start->format('d M Y').' – '.$run->period_end->format('d M Y'),
        ], [
            'label' => 'Bank details',
            'value' => trim(($payslip->bank_name ?? '—').($payslip->bank_account ? ' · '.$payslip->bank_account : '')),
        ]]"
        footer-note="Save as PDF using your browser print dialog (Print → Save as PDF)."
    >
        <div class="mb-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ $payslip->isFixedSalary() ? 'Salary earnings' : 'Shift earnings' }}</p>
                <dl class="mt-2 space-y-1 text-sm">
                    @if ($payslip->hasMixedSalary())
                        @foreach ($payslip->salary_breakdown as $slice)
                            <div class="flex justify-between gap-3">
                                <dt>{{ \Carbon\Carbon::parse($slice['from'])->format('d M') }} – {{ \Carbon\Carbon::parse($slice['to'])->format('d M') }}</dt>
                                <dd>{{ \App\Support\Money::format($slice['amount'], $run->currency) }}</dd>
                            </div>
                        @endforeach
                    @elseif ($payslip->isFixedSalary())
                        @php
                            $eligibleDays = $payslip->assignedStaff
                                ? \App\Support\Finance\PayrollRates::staffEligibleDays($payslip->assignedStaff, $run)
                                : null;
                            $periodDays = \App\Support\Finance\PayrollRates::periodDays($run);
                            $overtimePay = round((float) $payslip->overtime_shifts * (float) $payslip->overtime_shift_rate, 2);
                            $salaryPortion = round((float) $payslip->gross_pay - $overtimePay, 2);
                        @endphp
                        <div class="flex justify-between"><dt>Monthly gross</dt><dd>{{ \App\Support\Money::format($payslip->base_shift_rate, $run->currency) }}</dd></div>
                        @if ($eligibleDays !== null && $eligibleDays < $periodDays)
                            <div class="flex justify-between text-slate-600"><dt>Days paid</dt><dd>{{ $eligibleDays }} of {{ $periodDays }}</dd></div>
                        @endif
                        <div class="flex justify-between"><dt>Period salary</dt><dd>{{ \App\Support\Money::format($salaryPortion, $run->currency) }}</dd></div>
                        @if ($payslip->overtime_shifts > 0)
                            <div class="flex justify-between"><dt>Overtime ({{ $payslip->overtime_shifts }})</dt><dd>{{ \App\Support\Money::format($overtimePay, $run->currency) }}</dd></div>
                        @endif
                    @else
                        <div class="flex justify-between"><dt>Normal ({{ $payslip->normal_shifts }})</dt><dd>{{ \App\Support\Money::format($payslip->normal_shifts * $payslip->base_shift_rate, $run->currency) }}</dd></div>
                        <div class="flex justify-between"><dt>Overtime ({{ $payslip->overtime_shifts }})</dt><dd>{{ \App\Support\Money::format($payslip->overtime_shifts * $payslip->overtime_shift_rate, $run->currency) }}</dd></div>
                    @endif
                    <div class="flex justify-between border-t border-slate-200 pt-2 font-semibold"><dt>Gross pay</dt><dd>{{ \App\Support\Money::format($payslip->gross_pay, $run->currency) }}</dd></div>
                </dl>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Summary</p>
                <dl class="mt-2 space-y-1 text-sm">
                    @if ($payslip->isFixedSalary())
                        <div class="flex justify-between"><dt>Pay type</dt><dd>{{ $payslip->compensation_type->shortLabel() }}</dd></div>
                    @else
                        <div class="flex justify-between"><dt>Total shifts</dt><dd>{{ $payslip->total_shifts }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt>Deductions</dt><dd>{{ \App\Support\Money::format($payslip->total_deductions, $run->currency) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2 font-semibold text-emerald-700"><dt>Net pay</dt><dd>{{ \App\Support\Money::format($payslip->net_pay, $run->currency) }}</dd></div>
                </dl>
            </div>
        </div>

        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Deduction</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($payslip->deductions as $deduction)
                    <tr>
                        <td class="px-4 py-3">{{ $deduction->label }}</td>
                        <td class="px-4 py-3 text-right">{{ \App\Support\Money::format($deduction->amount, $run->currency) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="px-4 py-4 text-center text-slate-500">No deductions.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-finance.document>
</div>
@endsection
