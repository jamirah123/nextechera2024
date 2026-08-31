@extends('layouts.app')

@section('title', $payment->reference)
@section('page-title', $payment->isDisbursement() ? 'Payroll disbursement' : 'Payment receipt')

@section('content')
@php
    $disbursementMeta = collect($recordMeta)
        ->filter(fn (array $item) => in_array($item['label'], ['Recorded by', 'Created'], true))
        ->values()
        ->all();
@endphp
<div class="space-y-3">
    <x-page-header
        :title="$payment->reference"
        :subtitle="$payment->isDisbursement() ? ($payment->payrollRun?->reference ?? 'Payroll disbursement') : $payment->client?->name"
        :back="route('payments.index')"
    >
        <x-slot:actions>
            <x-report-actions :show-print="true" />
        </x-slot:actions>
    </x-page-header>

    <div class="report-print-area">
        <x-finance.document
            :title="$payment->isDisbursement() ? 'Payroll disbursement' : 'Payment receipt'"
            :reference="$payment->reference"
            :subtitle="$payment->isDisbursement() ? 'Salary bank payout' : $payment->client?->name"
            :status-tone="$payment->method->tone()"
            :status-label="$payment->method->label()"
            :meta="$payment->isDisbursement() ? $disbursementMeta : $recordMeta"
            :compact="$payment->isDisbursement()"
            :footer-note="$payment->isDisbursement() ? null : 'This receipt confirms payment received by '.config('psg.company').'. Retain for your records.'"
        >
            <dl @class([
                'grid gap-2 sm:grid-cols-2' => $payment->isDisbursement(),
                'grid gap-4 sm:grid-cols-2' => ! $payment->isDisbursement(),
            ])>
                <div @class([
                    'rounded-lg border border-emerald-100 bg-emerald-50 p-3 dark:border-emerald-900/40 dark:bg-emerald-950/30' => $payment->isDisbursement(),
                    'rounded-xl border border-emerald-100 bg-emerald-50 p-5 dark:border-emerald-900/40 dark:bg-emerald-950/30' => ! $payment->isDisbursement(),
                ])>
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-300">
                        {{ $payment->isDisbursement() ? 'Amount disbursed' : 'Amount received' }}
                    </dt>
                    <dd @class([
                        'mt-1 text-xl font-bold text-emerald-900 dark:text-emerald-100' => $payment->isDisbursement(),
                        'mt-2 text-3xl font-bold text-emerald-900 dark:text-emerald-100' => ! $payment->isDisbursement(),
                    ])>{{ \App\Support\Money::format($payment->amount) }}</dd>
                    <dd class="mt-0.5 text-xs text-emerald-800 dark:text-emerald-300">{{ $payment->payment_date->format('d M Y') }}</dd>
                </div>
                <div @class([
                    'rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/50' => $payment->isDisbursement(),
                    'rounded-xl border border-slate-100 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-800/50' => ! $payment->isDisbursement(),
                ])>
                    @if ($payment->isDisbursement())
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Linked payroll run</dt>
                        <dd class="mt-1 text-sm font-semibold">
                            @if ($payment->payrollRun)
                                <a href="{{ route('payroll.show', $payment->payrollRun) }}" class="text-brand-700 hover:text-brand-800 dark:text-brand-400">{{ $payment->payrollRun->reference }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    @else
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Applied to invoice</dt>
                        <dd class="mt-2 text-lg font-semibold">
                            <a href="{{ route('invoices.show', $payment->invoice) }}" class="text-brand-700 hover:text-brand-800 dark:text-brand-400">{{ $payment->invoice?->reference }}</a>
                        </dd>
                    @endif
                    @if ($payment->external_reference)
                        <dd class="mt-1 text-xs text-slate-600 dark:text-slate-400">Bank / ref: {{ $payment->external_reference }}</dd>
                    @endif
                </div>
                @if ($payment->notes)
                    <div class="sm:col-span-2 text-xs text-slate-600 dark:text-slate-400"><span class="font-semibold text-slate-700 dark:text-slate-300">Notes:</span> {{ $payment->notes }}</div>
                @endif
            </dl>
        </x-finance.document>
    </div>

    <x-finance.history-timeline :logs="$history" class="no-print" />
</div>
@endsection
