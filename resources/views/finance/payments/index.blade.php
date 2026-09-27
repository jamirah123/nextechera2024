@extends('layouts.app')

@section('title', 'Payments')
@section('page-title', 'Payments')

@section('content')
<div class="space-y-3">
    <x-page-header title="Payments" subtitle="Client collections and payroll disbursements.">
        <x-slot:actions>
            <x-finance.scope-tabs
                :current-url="route('payments.index', array_merge(request()->except('scope', 'page'), ['scope' => 'month']))"
                :all-url="route('payments.index', array_merge(request()->except('page'), ['scope' => 'all']))"
                :scope="$scope === 'all' ? 'all' : 'current'"
                current-label="This month"
                all-label="All history"
            />
            <x-report-actions :csv="route('payments.export', $exportQuery)" />
            @if ($canManage)
                <a href="{{ route('payments.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800"><x-icon name="plus" class="h-3.5 w-3.5" /> Record payment</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="report-print-area space-y-3">
        <x-print.report-header title="Payments register" subtitle="Client collections and payroll disbursements." />
        <section class="grid grid-cols-1 gap-2 sm:grid-cols-3 sm:gap-3">
            @foreach ([
                ['Collections today', \App\Support\Money::format($stats['today']), 'text-emerald-700'],
                ['Collections this month', \App\Support\Money::format($stats['month']), 'text-brand-700'],
                ['Payroll out this month', \App\Support\Money::format($stats['disbursements_month']), 'text-amber-700'],
            ] as [$label, $value, $tone])
                <div class="flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                    <p class="mt-0.5 break-words text-xs font-semibold tabular-nums leading-snug text-slate-900 dark:text-slate-100 sm:text-sm">{{ $value }}</p>
                </div>
            @endforeach
        </section>

        <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
            <form method="GET" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-5 xl:items-end" x-data x-ref="filterForm">
                @if ($scope === 'all')
                    <input type="hidden" name="scope" value="all">
                @elseif ($scope === 'month')
                    <input type="hidden" name="scope" value="month">
                @endif
                <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" class="sm:col-span-2" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
                <x-form-field label="Client" name="client_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                    <option value="">All clients</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected((string) ($filters['client_id'] ?? '') === (string) $client->id)>{{ $client->name }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Date" name="date" type="date" :value="$filters['date'] ?? ''" x-on:change="$refs.filterForm.requestSubmit()" />
                <x-filter-reset :href="route('payments.index', $scope === 'all' ? ['scope' => 'all'] : ['scope' => 'month'])" />
            </form>
        </section>

        @if ($payments->isEmpty())
            <x-empty-state title="No payments recorded" description="Client collections appear when recorded against invoices. Payroll disbursements appear when a payroll run is marked as paid." icon="payment" />
        @else
            <div class="grid gap-2 sm:grid-cols-2 lg:hidden print:hidden">
                @foreach ($payments as $payment)
                    <article class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('payments.show', $payment) }}" class="font-mono text-xs font-semibold text-slate-900 hover:text-brand-700 dark:text-slate-100">{{ $payment->reference }}</a>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $payment->payment_date->format('d M Y') }}</p>
                            </div>
                            <x-status-badge :tone="$payment->method->tone()" :label="$payment->method->label()" />
                        </div>
                        <div class="mt-2">
                            @if ($payment->isDisbursement())
                                <p class="text-xs font-medium text-slate-800 dark:text-slate-200">Payroll disbursement</p>
                                <p class="text-xs text-slate-500">{{ $payment->payrollRun?->reference }}</p>
                            @else
                                <p class="text-xs font-medium text-slate-800 dark:text-slate-200">{{ $payment->client?->name }}</p>
                                <p class="text-xs text-slate-500">{{ $payment->invoice?->reference }}</p>
                            @endif
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-3 dark:border-slate-800">
                            <p class="text-xs font-semibold text-slate-900 dark:text-slate-100">{{ \App\Support\Money::format($payment->amount) }}</p>
                            <x-action-icon :href="route('payments.show', $payment)" label="View" icon="eye" />
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="hidden lg:block print:block">
                <div class="data-table-shell">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="w-14">#</th>
                                <th>Payment</th>
                                <th>Client / payroll</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($payments as $payment)
                                <tr>
                                    <td class="whitespace-nowrap"><x-table-serial :paginator="$payments" :index="$loop->index" /></td>
                                    <td class="whitespace-nowrap">
                                        <p class="font-semibold font-mono text-slate-900 dark:text-slate-100">{{ $payment->reference }}</p>
                                        <p class="text-xs text-slate-500">{{ $payment->payment_date->format('d M Y') }}</p>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        @if ($payment->isDisbursement())
                                            <p>Payroll disbursement</p>
                                            <p class="text-xs text-slate-500">{{ $payment->payrollRun?->reference }}</p>
                                        @else
                                            <p>{{ $payment->client?->name }}</p>
                                            <p class="text-xs text-slate-500">{{ $payment->invoice?->reference }}</p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap font-semibold">{{ \App\Support\Money::format($payment->amount) }}</td>
                                    <td class="whitespace-nowrap"><x-status-badge :tone="$payment->method->tone()" :label="$payment->method->label()" /></td>
                                    <td class="whitespace-nowrap text-right"><x-action-icon :href="route('payments.show', $payment)" label="View" icon="eye" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="no-print">{{ $payments->links() }}</div>
        @endif
    </div>
</div>
@endsection
