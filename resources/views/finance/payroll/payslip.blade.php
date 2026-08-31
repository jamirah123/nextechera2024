@extends('layouts.app')

@section('title', 'Payslip — '.$payslip->employment_id)
@section('page-title', 'Payslip')

@section('content')
<div class="space-y-3">
    <x-page-header :title="$payslip->full_name" :subtitle="$payslip->employment_id.' · '.$run->reference" :back="route('payroll.show', $run)">
        <x-slot:actions>
            <x-report-actions
                :csv="route('payroll.payslips.export', [$run, $payslip])"
            >
                <a
                    href="{{ route('payroll.payslips.print', [$run, $payslip, 'format' => 'pdf']) }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                >
                    <x-icon name="download" class="h-3.5 w-3.5" />
                    Download PDF
                </a>
                <a
                    href="{{ route('payroll.payslips.print', [$run, $payslip, 'download' => 1]) }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                >
                    <x-icon name="download" class="h-3.5 w-3.5" />
                    Save PDF
                </a>
            </x-report-actions>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('status') }}</p>
    @endif
    @error('deduction')
        <p class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">{{ $message }}</p>
    @enderror

    <div class="report-print-area">
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
            ], [
                'label' => 'NSSF / TIN',
                'value' => trim(collect([$payslip->nssf_number ? 'NSSF '.$payslip->nssf_number : null, $payslip->tin_number ? 'TIN '.$payslip->tin_number : null])->filter()->implode(' · ') ?: '—'),
            ]]"
            footer-note="{{ $payslip->isFixedSalary() ? 'This payslip reflects a fixed monthly salary for the pay period.' : 'This payslip is generated from completed shifts recorded in the operations system.' }}"
        >
            <div class="mb-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ $payslip->isFixedSalary() ? 'Salary earnings' : 'Shift earnings' }}</p>
                    <dl class="mt-2 space-y-1 text-sm">
                        @if ($payslip->isFixedSalary())
                            @php
                                $eligibleDays = $payslip->assignedStaff
                                    ? \App\Support\Finance\PayrollRates::staffEligibleDays($payslip->assignedStaff, $run)
                                    : null;
                                $periodDays = \App\Support\Finance\PayrollRates::periodDays($run);
                            @endphp
                            <div class="flex justify-between"><dt>Monthly gross</dt><dd>{{ \App\Support\Money::format($payslip->base_shift_rate, $run->currency) }}</dd></div>
                            @if ($eligibleDays !== null && $eligibleDays < $periodDays)
                                <div class="flex justify-between text-slate-600"><dt>Days paid</dt><dd>{{ $eligibleDays }} of {{ $periodDays }}</dd></div>
                            @endif
                            <div class="flex justify-between"><dt>Period gross</dt><dd>{{ \App\Support\Money::format($payslip->gross_pay, $run->currency) }}</dd></div>
                        @else
                            <div class="flex justify-between"><dt>Normal ({{ $payslip->normal_shifts }})</dt><dd>{{ \App\Support\Money::format($payslip->normal_shifts * $payslip->base_shift_rate, $run->currency) }}</dd></div>
                            <div class="flex justify-between"><dt>Overtime ({{ $payslip->overtime_shifts }})</dt><dd>{{ \App\Support\Money::format($payslip->overtime_shifts * $payslip->overtime_shift_rate, $run->currency) }}</dd></div>
                            @if ($payslip->relief_shifts + $payslip->replacement_shifts + $payslip->special_duty_shifts > 0)
                                <div class="flex justify-between text-slate-600"><dt>Other worked shifts</dt><dd>{{ $payslip->relief_shifts + $payslip->replacement_shifts + $payslip->special_duty_shifts }}</dd></div>
                            @endif
                        @endif
                        <div class="flex justify-between border-t border-slate-200 pt-2 font-semibold dark:border-slate-600"><dt>Gross pay</dt><dd>{{ \App\Support\Money::format($payslip->gross_pay, $run->currency) }}</dd></div>
                    </dl>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Rates applied</p>
                    <dl class="mt-2 space-y-1 text-sm">
                        @if ($payslip->isFixedSalary())
                            <div class="flex justify-between"><dt>Pay type</dt><dd>{{ $payslip->compensation_type->shortLabel() }}</dd></div>
                            <div class="flex justify-between"><dt>Monthly salary</dt><dd>{{ \App\Support\Money::format($payslip->base_shift_rate, $run->currency) }}</dd></div>
                        @else
                            <div class="flex justify-between"><dt>Base shift rate</dt><dd>{{ \App\Support\Money::format($payslip->base_shift_rate, $run->currency) }}</dd></div>
                            <div class="flex justify-between"><dt>Overtime rate</dt><dd>{{ \App\Support\Money::format($payslip->overtime_shift_rate, $run->currency) }}</dd></div>
                            <div class="flex justify-between"><dt>Linked shifts</dt><dd>{{ $payslip->shifts->count() }}</dd></div>
                        @endif
                        <div class="flex justify-between border-t border-slate-200 pt-2 font-semibold text-emerald-700 dark:border-slate-600"><dt>Net pay</dt><dd>{{ \App\Support\Money::format($payslip->net_pay, $run->currency) }}</dd></div>
                    </dl>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-slate-700">
                    <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800">
                        <tr>
                            <th class="px-4 py-3 text-left">Deduction</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            @if ($canManage && $run->status->canAddDeductions())
                                <th class="px-4 py-3 text-right no-print">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @forelse ($payslip->deductions as $deduction)
                            <tr>
                                <td class="px-4 py-3">
                                    {{ $deduction->label }}
                                    @if ($deduction->is_statutory)
                                        <span class="ml-1 text-[10px] uppercase text-slate-400">Statutory</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">{{ \App\Support\Money::format($deduction->amount, $run->currency) }}</td>
                                @if ($canManage && $run->status->canAddDeductions())
                                    <td class="px-4 py-3 text-right no-print">
                                        @if (! $deduction->is_statutory)
                                            <form method="POST" action="{{ route('payroll.payslips.deductions.destroy', [$run, $payslip, $deduction]) }}" class="inline" onsubmit="return confirm('Remove this deduction?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs text-rose-600 hover:underline">Remove</button>
                                            </form>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-slate-500">No deductions recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-finance.document>
    </div>

    @if ($canManage && $run->status->canAddDeductions())
        <section class="form-card no-print max-w-xl">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Add deduction</h2>
            <p class="mt-1 text-xs text-slate-500">Advances and penalties can be added before approval. Statutory deductions are applied on calculate.</p>
            <form method="POST" action="{{ route('payroll.payslips.deductions.store', [$run, $payslip]) }}" class="mt-4 grid gap-3 sm:grid-cols-3">
                @csrf
                <x-form-field label="Type" name="type" type="select" :required="true">
                    @foreach (\App\Enums\PayrollDeductionType::cases() as $type)
                        @if (! $type->isStatutory())
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endif
                    @endforeach
                </x-form-field>
                <x-form-field label="Label" name="label" :value="old('label')" placeholder="Optional" />
                <x-form-field label="Amount" name="amount" type="number" step="0.01" min="0.01" :required="true" />
                <div class="sm:col-span-3">
                    <button type="submit" class="btn btn-primary">Add deduction</button>
                </div>
            </form>
        </section>
    @endif
</div>
@endsection
