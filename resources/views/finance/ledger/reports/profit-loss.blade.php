@extends('layouts.app')

@section('title', 'Profit and loss')
@section('page-title', 'Profit and loss')

@section('content')
<div class="space-y-3">
    <x-page-header title="Profit and loss" subtitle="Revenue and expense movements inside the selected period.">
        <x-slot:actions>
            <x-report-actions :csv="route('ledger.reports.profit-loss', ['period_id' => $period->id, 'export' => 1])" />
            <a href="{{ route('ledger.reports.trial-balance', ['period_id' => $period->id]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm">Trial balance</a>
            <a href="{{ route('ledger.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm">Ledger hub</a>
        </x-slot:actions>
    </x-page-header>

    <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <form method="GET" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4 xl:items-end" x-data x-ref="filterForm">
            <x-form-field label="Period" name="period_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                @foreach ($periodOptions as $option)
                    <option value="{{ $option->id }}" @selected($period->id === $option->id)>{{ $option->label() }}</option>
                @endforeach
            </x-form-field>
        </form>
    </section>

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700">Revenue</p>
            <p class="mt-0.5 text-sm font-semibold tabular-nums">{{ \App\Support\Money::format($report['revenue_total']) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-rose-700">Expenses</p>
            <p class="mt-0.5 text-sm font-semibold tabular-nums">{{ \App\Support\Money::format($report['expense_total']) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide {{ $report['net'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">Net {{ $report['net'] >= 0 ? 'profit' : 'loss' }}</p>
            <p class="mt-0.5 text-sm font-semibold tabular-nums">{{ \App\Support\Money::format($report['net']) }}</p>
        </div>
    </section>

    <div class="grid gap-3 lg:grid-cols-2">
        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h2 class="px-3 py-2 text-sm font-semibold">Revenue</h2>
            <table class="min-w-full divide-y divide-slate-100 text-xs">
                <tbody class="divide-y divide-slate-100">
                    @forelse ($report['revenue'] as $row)
                        <tr>
                            <td class="px-3 py-2">{{ $row['account']->label() }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::format($row['amount']) }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-3 py-3 text-slate-500" colspan="2">No revenue in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h2 class="px-3 py-2 text-sm font-semibold">Expenses</h2>
            <table class="min-w-full divide-y divide-slate-100 text-xs">
                <tbody class="divide-y divide-slate-100">
                    @forelse ($report['expenses'] as $row)
                        <tr>
                            <td class="px-3 py-2">{{ $row['account']->label() }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::format($row['amount']) }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-3 py-3 text-slate-500" colspan="2">No expenses in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
</div>
@endsection
