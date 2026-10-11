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
            :subtitle="$payment->isDisbursement() ? 'Salary bank payout' : null"
            :status-tone="$payment->method->tone()"
            :status-label="$payment->isDisbursement() ? $payment->method->label() : null"
            :meta="$payment->isDisbursement() ? $disbursementMeta : []"
            :compact="$payment->isDisbursement()"
            :footer-note="$payment->isDisbursement() ? null : 'This receipt confirms payment received by '.config('psg.company').'. Retain for your records.'"
        >
            @if ($payment->isDisbursement())
                <dl class="grid gap-2 sm:grid-cols-2">
                    <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-3 dark:border-emerald-900/40 dark:bg-emerald-950/30">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-300">Amount disbursed</dt>
                        <dd class="mt-1">
                            <x-money-stat class="text-emerald-900 dark:text-emerald-100">{{ \App\Support\Money::format($payment->amount) }}</x-money-stat>
                        </dd>
                        <dd class="mt-0.5 text-xs text-emerald-800 dark:text-emerald-300">{{ $payment->payment_date->format('d M Y') }}</dd>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/50">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Linked payroll run</dt>
                        <dd class="mt-1 text-sm font-semibold">
                            @if ($payment->payrollRun)
                                <a href="{{ route('payroll.show', $payment->payrollRun) }}" class="text-brand-700 hover:text-brand-800 dark:text-brand-400">{{ $payment->payrollRun->reference }}</a>
                            @else
                                —
                            @endif
                        </dd>
                        @if ($payment->external_reference)
                            <dd class="mt-1 text-xs text-slate-600 dark:text-slate-400">Bank / ref: {{ $payment->external_reference }}</dd>
                        @endif
                    </div>
                    @if ($payment->notes)
                        <div class="sm:col-span-2 text-xs text-slate-600 dark:text-slate-400"><span class="font-semibold text-slate-700 dark:text-slate-300">Notes:</span> {{ $payment->notes }}</div>
                    @endif
                </dl>
            @else
                <div class="receipt-summary">
                    <div class="min-w-0">
                        <p class="receipt-kicker">Received from</p>
                        <p class="receipt-client">{{ $payment->client?->name ?? '—' }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ $payment->method->label() }}
                            <span class="mx-1.5 text-slate-300">·</span>
                            {{ $payment->payment_date->format('d F Y') }}
                        </p>
                    </div>
                    <div class="sm:text-right">
                        <p class="receipt-kicker">Amount received</p>
                        <p class="receipt-amount">{{ \App\Support\Money::format($payment->amount) }}</p>
                    </div>
                </div>

                <table class="receipt-grid">
                    <thead>
                        <tr>
                            <th>Particular</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Client</td>
                            <td class="font-semibold">{{ $payment->client?->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td>Payment method</td>
                            <td>{{ $payment->method->label() }}</td>
                        </tr>
                        <tr>
                            <td>Amount received</td>
                            <td class="receipt-money">{{ \App\Support\Money::format($payment->amount) }}</td>
                        </tr>
                        <tr>
                            <td>Payment date</td>
                            <td>{{ $payment->payment_date->format('d F Y') }}</td>
                        </tr>
                        <tr>
                            <td>Applied to invoice</td>
                            <td>
                                @if ($payment->invoice)
                                    <a href="{{ route('invoices.show', $payment->invoice) }}" class="font-semibold text-brand-700 hover:text-brand-800 dark:text-brand-400">{{ $payment->invoice->reference }}</a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td>Bank / reference</td>
                            <td>{{ $payment->external_reference ?: '—' }}</td>
                        </tr>
                        @if ($payment->notes)
                            <tr>
                                <td>Notes</td>
                                <td>{{ $payment->notes }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>

                @if (count($recordMeta))
                    <div class="receipt-signoff">
                        @foreach ($recordMeta as $item)
                            <div>
                                <p class="receipt-kicker">{{ $item['label'] }}</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $item['value'] }}</p>
                                @if (! empty($item['hint']))
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $item['hint'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </x-finance.document>
    </div>

    <x-finance.history-timeline :logs="$history" class="no-print" />
</div>
@endsection
