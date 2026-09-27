@extends('layouts.app')

@section('title', 'Trial balance')
@section('page-title', 'Trial balance')

@section('content')
<div class="space-y-3">
    <x-page-header title="Trial balance" subtitle="Closing balances of posted journals as at period end.">
        <x-slot:actions>
            <x-report-actions :csv="route('ledger.reports.trial-balance', ['period_id' => $period->id, 'export' => 1])" />
            <a href="{{ route('ledger.reports.profit-loss', ['period_id' => $period->id]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm">P&amp;L</a>
            <a href="{{ route('ledger.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm">Ledger hub</a>
        </x-slot:actions>
    </x-page-header>

    <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <form method="GET" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4 xl:items-end" x-data x-ref="filterForm">
            <x-form-field label="As at period" name="period_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                @foreach ($periodOptions as $option)
                    <option value="{{ $option->id }}" @selected($period->id === $option->id)>{{ $option->label() }}</option>
                @endforeach
            </x-form-field>
        </form>
    </section>

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-sky-700">Debit total</p>
            <p class="mt-0.5 text-sm font-semibold tabular-nums">{{ \App\Support\Money::format($report['debit_total']) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-indigo-700">Credit total</p>
            <p class="mt-0.5 text-sm font-semibold tabular-nums">{{ \App\Support\Money::format($report['credit_total']) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide {{ abs($report['debit_total'] - $report['credit_total']) < 0.009 ? 'text-emerald-700' : 'text-rose-700' }}">Difference</p>
            <p class="mt-0.5 text-sm font-semibold tabular-nums">{{ \App\Support\Money::format($report['debit_total'] - $report['credit_total']) }}</p>
        </div>
    </section>

    @if ($report['rows']->isEmpty())
        <x-empty-state title="No posted activity" description="Issue invoices, post purchases or record a manual journal first." icon="wallet" />
    @else
        <div class="psg-stack overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <table class="min-w-full divide-y divide-slate-100 text-left text-xs dark:divide-slate-800">
                <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Account</th>
                        <th class="px-3 py-2">Type</th>
                        <th class="px-3 py-2 text-right">Debit</th>
                        <th class="px-3 py-2 text-right">Credit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($report['rows'] as $row)
                        <tr>
                            <td class="px-3 py-2 font-semibold" data-label="Account">{{ $row['account']->label() }}</td>
                            <td class="px-3 py-2" data-label="Type"><x-status-badge :tone="$row['account']->type->tone()" :label="$row['account']->type->label()" /></td>
                            <td class="px-3 py-2 text-right tabular-nums" data-label="Debit">{{ $row['debit'] > 0 ? \App\Support\Money::format($row['debit']) : '—' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums" data-label="Credit">{{ $row['credit'] > 0 ? \App\Support\Money::format($row['credit']) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-50 text-xs font-semibold">
                    <tr>
                        <td class="px-3 py-2" colspan="2" data-label="">Totals</td>
                        <td class="px-3 py-2 text-right tabular-nums" data-label="Debit">{{ \App\Support\Money::format($report['debit_total']) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums" data-label="Credit">{{ \App\Support\Money::format($report['credit_total']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
@endsection
