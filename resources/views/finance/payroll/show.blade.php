@extends('layouts.app')

@section('title', $run->reference)
@section('page-title', 'Payroll Run')

@section('content')
<div class="space-y-3">
    <x-page-header :title="$run->reference" :subtitle="'Period '.$run->periodLabel()" :back="route('payroll.index')">
        <x-slot:actions>
            <x-report-actions
                :csv="$run->guard_count > 0 ? route('payroll.export.payslips', $run) : null"
            />
            @if ($run->status === \App\Enums\PayrollRunStatus::Paid && $run->payment)
                <a href="{{ route('payments.show', $run->payment) }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">Payment record</a>
            @endif
            @if (in_array($run->status, [\App\Enums\PayrollRunStatus::Approved, \App\Enums\PayrollRunStatus::Paid], true))
                <a href="{{ route('payroll.export.bank', $run) }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">Bank file</a>
            @endif
            @if ($canSubmit && $run->status->canCalculate())
                <form method="POST" action="{{ route('payroll.calculate', $run) }}" class="inline-flex">
                    @csrf
                    <button type="submit" class="btn btn-primary shadow-sm">
                        {{ $run->status === \App\Enums\PayrollRunStatus::Draft ? 'Calculate from shifts' : 'Recalculate' }}
                    </button>
                </form>
            @endif
            @if ($canSubmit && $run->status->canSubmit())
                <form method="POST" action="{{ route('payroll.submit', $run) }}" class="inline-flex">
                    @csrf
                    <button type="submit" class="inline-flex rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-800">Submit for approval</button>
                </form>
            @endif
            @if ($canApprove && $run->status->canApprove())
                <form method="POST" action="{{ route('payroll.approve', $run) }}" class="inline-flex">
                    @csrf
                    <button type="submit" class="inline-flex rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-800">Approve payroll</button>
                </form>
            @endif
            @if (\App\Support\Finance\PayrollAccess::canReject(auth()->user(), $run))
                <x-payroll-reject-action :action="route('payroll.reject', $run)" :reference="$run->reference" />
            @endif
            @if ($canSubmit && $run->status === \App\Enums\PayrollRunStatus::Approved)
                <form method="POST" action="{{ route('payroll.pay', $run) }}" class="inline-flex">
                    @csrf
                    <button type="submit" class="inline-flex rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-800">Mark as paid</button>
                </form>
            @endif
            @if (\App\Support\Finance\PayrollAccess::canCancel(auth()->user(), $run))
                @php
                    $cancelLabel = \App\Support\Finance\PayrollAccess::cancelLabel($run->status, auth()->user());
                    $isDelete = str_contains(strtolower($cancelLabel), 'delete');
                @endphp
                <x-confirm-action
                    :action="route('payroll.cancel', $run)"
                    :title="$isDelete ? 'Delete payroll run' : 'Cancel payroll run'"
                    :confirm="match ($run->status) {
                        \App\Enums\PayrollRunStatus::Paid => 'This run was marked as paid. Deleting it will remove all payslips, restore salary advance balances, and unlink the payment record. Only proceed if this run was created in error.',
                        \App\Enums\PayrollRunStatus::Approved => 'This will delete the approved payroll run, remove all payslips, and restore salary advance balances. Finance can submit a corrected run for this period.',
                        \App\Enums\PayrollRunStatus::Submitted => 'Delete this submitted payroll run? Finance will need to recalculate and resubmit.',
                        \App\Enums\PayrollRunStatus::Calculated => 'This will delete the payroll run and remove calculated payslips. Salary advance balances will be restored.',
                        default => 'This will cancel the payroll run. You can create a new run for this period if needed.',
                    }"
                    :label="$cancelLabel"
                    :confirm-label="$isDelete ? 'Yes, delete run' : 'Yes, cancel'"
                    :cancel-label="$run->status === \App\Enums\PayrollRunStatus::Draft ? 'Keep payroll run' : 'Go back'"
                />
            @endif
        </x-slot:actions>
    </x-page-header>

    @error('payroll')
        <p class="form-alert form-alert--error text-sm">{{ $message }}</p>
    @enderror
    @error('reason')
        <p class="form-alert form-alert--error text-sm">{{ $message }}</p>
    @enderror

    @if ($run->status === \App\Enums\PayrollRunStatus::Calculated && $canSubmit && filled($run->notes) && str_contains($run->notes, 'Returned to finance:'))
        <p class="form-alert form-alert--warning text-sm whitespace-pre-line">{{ $run->notes }}</p>
    @endif

    @if ($run->status === \App\Enums\PayrollRunStatus::Calculated && $canSubmit)
        <p class="form-alert form-alert--info text-sm">Review payslips and deductions, then submit for Managing Director approval.</p>
    @endif
    @if ($run->status === \App\Enums\PayrollRunStatus::Submitted)
        <p class="form-alert form-alert--warning text-sm">Submitted for Managing Director approval. MD can approve or return to finance for revision.</p>
    @endif

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
        @foreach ([
            ['Payslips', number_format($paySummary['count'] ?? $run->payslipCount()), 'text-slate-500', false],
            ['Gross', \App\Support\Money::format($paySummary['gross'] ?? $run->gross_total, $run->currency), 'text-emerald-700', true],
            ['Deductions', \App\Support\Money::format($paySummary['deductions'] ?? $run->deductions_total, $run->currency), 'text-amber-700', true],
            ['Net pay', \App\Support\Money::format($paySummary['net'] ?? $run->net_total, $run->currency), 'text-brand-700', true],
        ] as [$label, $value, $tone, $isMoney])
            <div class="flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                <p @class([
                    'mt-0.5 font-semibold tabular-nums text-slate-900 dark:text-slate-100',
                    'break-words text-xs leading-snug sm:text-sm' => $isMoney,
                    'text-base' => ! $isMoney,
                ])>{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <div class="form-card">
        <div class="flex flex-wrap items-center gap-3">
            <x-status-badge :tone="$run->status->tone()" :label="$run->status->label()" />
            <p class="text-sm text-slate-600 dark:text-slate-400">
                {{ $run->period_start->format('d M Y') }} – {{ $run->period_end->format('d M Y') }}
                ·
                @if ($run->site)
                    Site: {{ $run->site->name }}
                @elseif ($run->region)
                    Region: {{ $run->region->name }}
                @else
                    Company-wide
                @endif
            </p>
        </div>
        @if ($run->notes)
            <p class="mt-3 text-sm text-slate-600 whitespace-pre-line dark:text-slate-400">{{ $run->notes }}</p>
        @endif
    </div>

    @if ($payslips->isEmpty())
        <x-empty-state title="No payslips yet" description="Calculate this run to pull shift-based guards with completed shifts and fixed-salary staff for the period." icon="payroll" />
    @else
        <div class="psg-stack overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="w-12">#</th>
                        <th>Employee</th>
                        <th>Pay type</th>
                        <th>Shifts / basis</th>
                        <th>Gross</th>
                        <th>Deductions</th>
                        <th>Net pay</th>
                        <th class="text-right">Payslip</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payslips as $payslip)
                        @php
                            $supervisorPayHidden = auth()->user()
                                && \App\Support\Access\SupervisorPayAccess::hidesSupervisorPay(auth()->user())
                                && $payslip->belongsToSupervisor();
                        @endphp
                        <tr>
                            <td data-label="#"><x-table-serial :paginator="$payslips" :index="$loop->index" /></td>
                            <td data-label="Employee">
                                <p class="font-semibold">{{ $payslip->full_name }}</p>
                                <p class="text-[10px] text-slate-500 font-mono">{{ $payslip->employment_id }}</p>
                            </td>
                            <td data-label="Pay type">{{ $payslip->compensation_type?->shortLabel() ?? 'Shift pay' }}</td>
                            <td data-label="Basis">
                                @if ($payslip->isFixedSalary())
                                    <span class="text-slate-600 dark:text-slate-400">Fixed salary</span>
                                @else
                                    {{ $payslip->total_shifts }} <span class="text-slate-500">({{ $payslip->normal_shifts }}N / {{ $payslip->overtime_shifts }}OT)</span>
                                @endif
                            </td>
                            <td data-label="Gross">{{ $supervisorPayHidden ? '—' : \App\Support\Money::format($payslip->gross_pay, $run->currency) }}</td>
                            <td data-label="Deductions">{{ $supervisorPayHidden ? '—' : \App\Support\Money::format($payslip->total_deductions, $run->currency) }}</td>
                            <td class="font-semibold" data-label="Net pay">{{ $supervisorPayHidden ? '—' : \App\Support\Money::format($payslip->net_pay, $run->currency) }}</td>
                            <td class="text-right" data-label="Payslip">
                                @unless ($supervisorPayHidden)
                                    <a href="{{ route('payroll.payslips.show', [$run, $payslip]) }}" class="text-brand-700 hover:underline dark:text-brand-400">View</a>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($payslips->hasPages())
            <div class="no-print">{{ $payslips->links() }}</div>
        @endif
    @endif
</div>
@endsection
