@extends('layouts.app')

@section('title', 'Profitability')
@section('page-title', 'Profitability')

@section('content')
<div class="space-y-3">
    <x-page-header title="Profitability analysis" subtitle="Revenue vs estimated payroll — export after generating.">
        <x-slot:actions>
            <x-report-actions :csv="route('profitability.export', $exportQuery)" />
        </x-slot:actions>
    </x-page-header>

    <div class="report-print-area space-y-3">
        <x-print.report-header
            title="Profitability analysis"
            :subtitle="'Revenue vs estimated payroll · '.\Carbon\Carbon::parse($report['from'])->format('d M Y').' – '.\Carbon\Carbon::parse($report['to'])->format('d M Y')"
        />
        <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
            <form method="GET" class="grid gap-3 sm:grid-cols-4 sm:items-end">
                <x-form-field label="From" name="from" type="date" :value="$filters['from']" />
                <x-form-field label="To" name="to" type="date" :value="$filters['to']" />
                <button class="btn btn-primary">Generate report</button>
            </form>
        </section>

        <p class="text-sm text-slate-500">Period: <span class="font-semibold text-slate-800">{{ \Carbon\Carbon::parse($report['from'])->format('d M Y') }} – {{ \Carbon\Carbon::parse($report['to'])->format('d M Y') }}</span></p>

        <section class="flex flex-row gap-2 sm:gap-3">
            @foreach ([
                ['Invoiced', $report['totals']['invoiced'], 'text-sky-700'],
                ['Collected', $report['totals']['collected'], 'text-emerald-700'],
                ['Outstanding', $report['totals']['outstanding'], 'text-amber-800'],
                ['Overdue', $report['totals']['overdue'], 'text-rose-700'],
            ] as [$label, $value, $tone])
                <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                    <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }} sm:text-[11px]">{{ $label }}</p>
                    <p class="mt-1 text-base font-semibold text-slate-900 sm:text-lg">{{ \App\Support\Money::format($value) }}</p>
                </div>
            @endforeach
        </section>

        @foreach ([
            ['By client', $report['by_client']],
            ['By site', $report['by_site']],
            ['By region', $report['by_region']],
        ] as [$title, $rows])
            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-3 py-2.5">
                    <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
                </div>
                @if (empty($rows))
                    <p class="px-5 py-6 text-sm text-slate-500">No activity in this period.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-sm">
                            <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-3 py-2 text-left">Name</th>
                                    <th class="px-3 py-2 text-right">Revenue</th>
                                    <th class="px-3 py-2 text-right">Est. cost</th>
                                    <th class="px-3 py-2 text-right">Profit</th>
                                    <th class="px-3 py-2 text-right">Margin</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($rows as $row)
                                    <tr class="hover:bg-slate-50/60">
                                        <td class="px-3 py-2">
                                            <p class="font-semibold text-slate-900">{{ $row['label'] }}</p>
                                            <p class="text-xs text-slate-500">{{ $row['code'] ?? '' }}{{ isset($row['client']) ? ' · '.$row['client'] : '' }}</p>
                                        </td>
                                        <td class="px-3 py-2 text-right">{{ \App\Support\Money::format($row['revenue']) }}</td>
                                        <td class="px-3 py-2 text-right">{{ \App\Support\Money::format($row['cost']) }}</td>
                                        <td class="px-3 py-2 text-right font-semibold @if($row['profit'] >= 0) text-emerald-700 @else text-rose-700 @endif">{{ \App\Support\Money::format($row['profit']) }}</td>
                                        <td class="px-3 py-2 text-right">{{ $row['margin'] !== null ? $row['margin'].'%' : '—' }}</td>
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
