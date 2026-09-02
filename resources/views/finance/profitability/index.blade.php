@extends('layouts.app')

@section('title', 'Profitability')
@section('page-title', 'Profitability')

@section('content')
<div class="space-y-3">
    <x-page-header title="Profitability analysis" subtitle="Revenue vs payroll cost — uses paid payroll when available, otherwise billing estimates.">
        <x-slot:actions>
            <x-report-actions :csv="route('profitability.export', $exportQuery)" />
        </x-slot:actions>
    </x-page-header>

    <div class="report-print-area space-y-3">
        <x-print.report-header
            title="Profitability analysis"
            :subtitle="'Revenue vs payroll cost · '.\Carbon\Carbon::parse($report['from'])->format('d M Y').' – '.\Carbon\Carbon::parse($report['to'])->format('d M Y')"
        />
        <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <form method="GET" class="grid gap-3 sm:grid-cols-4 sm:items-end">
                <x-form-field label="From" name="from" type="date" :value="$filters['from']" />
                <x-form-field label="To" name="to" type="date" :value="$filters['to']" />
                <button class="btn btn-primary">Generate report</button>
            </form>
        </section>

        <p class="text-sm text-slate-500 dark:text-slate-400">Period: <span class="font-semibold text-slate-800 dark:text-slate-200">{{ \Carbon\Carbon::parse($report['from'])->format('d M Y') }} – {{ \Carbon\Carbon::parse($report['to'])->format('d M Y') }}</span></p>

        <section class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5 lg:gap-3">
            @foreach ([
                ['Invoiced', $report['totals']['invoiced'], 'text-sky-700 dark:text-sky-400'],
                ['Collected', $report['totals']['collected'], 'text-emerald-700 dark:text-emerald-400'],
                ['Payroll cost', $report['totals']['payroll_cost'], 'text-amber-800 dark:text-amber-400'],
                ['Outstanding', $report['totals']['outstanding'], 'text-amber-800 dark:text-amber-400'],
                ['Overdue', $report['totals']['overdue'], 'text-rose-700 dark:text-rose-400'],
            ] as [$label, $value, $tone])
                <div class="flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                    <p class="mt-0.5 break-words text-xs font-semibold tabular-nums leading-snug text-slate-900 dark:text-slate-100 sm:text-sm">{{ \App\Support\Money::format($value) }}</p>
                </div>
            @endforeach
        </section>

        <p class="text-xs text-slate-500 dark:text-slate-400">
            Payroll cost basis:
            <span class="font-semibold text-slate-700 dark:text-slate-200">
                @if ($report['totals']['payroll_cost_source'] === 'actual')
                    paid payroll runs
                @else
                    billing estimates (no paid payroll in this period)
                @endif
            </span>
        </p>

        @foreach ([
            ['By client', $report['by_client']],
            ['By site', $report['by_site']],
            ['By region', $report['by_region']],
        ] as [$title, $rows])
            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-3 py-2.5 dark:border-slate-700 dark:from-slate-800 dark:to-slate-900">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ $title }}</h2>
                </div>
                @if (empty($rows))
                    <p class="px-5 py-6 text-sm text-slate-500 dark:text-slate-400">No activity in this period.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-slate-700">
                            <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                                <tr>
                                    <th class="px-3 py-2 text-left">Name</th>
                                    <th class="px-3 py-2 text-right">Revenue</th>
                                    <th class="px-3 py-2 text-right">Payroll cost</th>
                                    <th class="px-3 py-2 text-right">Profit</th>
                                    <th class="px-3 py-2 text-right">Margin</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                @foreach ($rows as $row)
                                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/60">
                                        <td class="px-3 py-2">
                                            <p class="font-semibold text-slate-900 dark:text-slate-100">{{ $row['label'] }}</p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $row['code'] ?? '' }}{{ isset($row['client']) ? ' · '.$row['client'] : '' }}</p>
                                        </td>
                                        <td class="px-3 py-2 text-right text-slate-900 dark:text-slate-100">{{ \App\Support\Money::format($row['revenue']) }}</td>
                                        <td class="px-3 py-2 text-right text-slate-900 dark:text-slate-100">
                                            {{ \App\Support\Money::format($row['cost']) }}
                                            <span class="block text-[10px] font-normal text-slate-500 dark:text-slate-400">{{ ($row['cost_source'] ?? 'estimated') === 'actual' ? 'Paid' : 'Est.' }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-right font-semibold @if($row['profit'] >= 0) text-emerald-700 dark:text-emerald-400 @else text-rose-700 dark:text-rose-400 @endif">{{ \App\Support\Money::format($row['profit']) }}</td>
                                        <td class="px-3 py-2 text-right text-slate-900 dark:text-slate-100">{{ $row['margin'] !== null ? $row['margin'].'%' : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endforeach
    </div>
</div>
@endsection
