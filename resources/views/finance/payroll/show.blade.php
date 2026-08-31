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
            @if ($canSubmit && $run->status === \App\Enums\PayrollRunStatus::Approved)
                <form method="POST" action="{{ route('payroll.pay', $run) }}" class="inline-flex">
                    @csrf
                    <button type="submit" class="inline-flex rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-800">Mark as paid</button>
                </form>
            @endif
            @if (\App\Support\Finance\PayrollAccess::canCancel(auth()->user(), $run))
                @php
                    $cancelLabel = \App\Support\Finance\PayrollAccess::cancelLabel($run->status, auth()->user());
                    $isReject = str_contains(strtolower($cancelLabel), 'reject');
                @endphp
                <x-confirm-action
                    :action="route('payroll.cancel', $run)"
                    :title="$isReject ? 'Reject payroll run' : 'Cancel payroll run'"
                    :confirm="match ($run->status) {
                        \App\Enums\PayrollRunStatus::Paid => 'This run was marked as paid. Rejecting it will remove all payslips and restore salary advance balances. Only proceed if this run was created in error.',
                        \App\Enums\PayrollRunStatus::Approved => 'This will reject the approved payroll run, remove all payslips, and restore salary advance balances. Finance can submit a corrected run for this period.',
                        \App\Enums\PayrollRunStatus::Submitted => 'Reject this submitted payroll run? Finance will need to recalculate and resubmit.',
                        \App\Enums\PayrollRunStatus::Calculated => 'This will cancel the payroll run and remove calculated payslips. Salary advance balances will be restored.',
                        default => 'This will cancel the payroll run. You can create a new run for this period if needed.',
                    }"
                    :label="$cancelLabel"
                    :confirm-label="$isReject ? 'Yes, reject run' : 'Yes, delete run'"
                    :cancel-label="$run->status === \App\Enums\PayrollRunStatus::Draft ? 'Keep payroll run' : 'Go back'"
                />
            @endif
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <p class="form-alert form-alert--success text-sm">{{ session('status') }}</p>
    @endif
    @error('payroll')
        <p class="form-alert form-alert--error text-sm">{{ $message }}</p>
    @enderror

    @if ($run->status === \App\Enums\PayrollRunStatus::Calculated && $canSubmit)
        <p class="form-alert form-alert--info text-sm">Review payslips and deductions, then submit for Managing Director approval.</p>
    @endif
    @if ($run->status === \App\Enums\PayrollRunStatus::Submitted)
        <p class="form-alert form-alert--warning text-sm">Submitted for Managing Director approval.</p>
    @endif

    <section class="flex flex-row gap-2 sm:gap-3">
        <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Guards paid</p>
            <p class="mt-1 text-xl font-semibold">{{ $run->guard_count }}</p>
        </div>
        <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700">Gross</p>
            <p class="mt-1 text-xl font-semibold">{{ \App\Support\Money::format($run->gross_total, $run->currency) }}</p>
        </div>
        <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700">Deductions</p>
            <p class="mt-1 text-xl font-semibold">{{ \App\Support\Money::format($run->deductions_total, $run->currency) }}</p>
        </div>
        <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-brand-700">Net pay</p>
            <p class="mt-1 text-xl font-semibold">{{ \App\Support\Money::format($run->net_total, $run->currency) }}</p>
        </div>
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
            <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">{{ $run->notes }}</p>
        @endif
    </div>

    @if ($run->guard_count === 0)
        <x-empty-state title="No payslips yet" description="Calculate this run to generate payslips from completed shifts in the period." icon="payroll" />
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="w-12">#</th>
                        <th>Guard</th>
                        <th>Shifts</th>
                        <th>Gross</th>
                        <th>Deductions</th>
                        <th>Net pay</th>
                        <th class="text-right">Payslip</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payslips as $payslip)
                        <tr>
                            <td><x-table-serial :paginator="$payslips" :index="$loop->index" /></td>
                            <td>
                                <p class="font-semibold">{{ $payslip->full_name }}</p>
                                <p class="text-[10px] text-slate-500 font-mono">{{ $payslip->employment_id }}</p>
                            </td>
                            <td>{{ $payslip->total_shifts }} <span class="text-slate-500">({{ $payslip->normal_shifts }}N / {{ $payslip->overtime_shifts }}OT)</span></td>
                            <td>{{ \App\Support\Money::format($payslip->gross_pay, $run->currency) }}</td>
                            <td>{{ \App\Support\Money::format($payslip->total_deductions, $run->currency) }}</td>
                            <td class="font-semibold">{{ \App\Support\Money::format($payslip->net_pay, $run->currency) }}</td>
                            <td class="text-right">
                                <a href="{{ route('payroll.payslips.show', [$run, $payslip]) }}" class="text-brand-700 hover:underline dark:text-brand-400">View</a>
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
