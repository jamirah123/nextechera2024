@extends('layouts.app')

@section('title', 'Monthly Shift Summary')
@section('page-title', 'Monthly shifts')
@section('page-subtitle', 'Normal + overtime month-end totals')

@section('content')
<div class="space-y-6">
    <x-page-header
        title="Monthly shift summary"
        subtitle="Completed shifts by guard for payroll-ready month-end review."
        :back="route('reports.index')"
    >
        <x-slot:actions>
            <x-report-actions
                :csv="route('reports.monthly-shifts.export', array_merge($exportQuery, ['format' => 'csv']))"
            />
        </x-slot:actions>
    </x-page-header>

    <div class="report-print-area space-y-6">
    <section class="flex flex-row gap-2 sm:gap-3">
        <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-brand-800 sm:text-[11px]">Normal</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ number_format($totals['normal']) }}</p>
        </div>
        <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-amber-800 sm:text-[11px]">Overtime</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ number_format($totals['overtime']) }}</p>
        </div>
        <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-emerald-700 sm:text-[11px]">Total worked</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ number_format($totals['total']) }}</p>
        </div>
        <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-slate-600 sm:text-[11px]">Guards</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ number_format($rows->count()) }}</p>
        </div>
    </section>

    <section class="no-print rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <form method="GET" action="{{ route('reports.monthly-shifts') }}" x-data x-ref="filterForm" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
            <x-form-field label="Year" name="year" type="number" :value="$filters['year']" min="2020" max="2100" x-on:change="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Month" name="month" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected((int) $filters['month'] === $m)>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                @endfor
            </x-form-field>
            <x-form-field label="Region" name="region_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All regions</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" @selected((string) ($filters['region_id'] ?? '') === (string) $region->id)>{{ $region->name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Site" name="site_id" type="select" data-searchable="true" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All sites</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}" @selected((string) ($filters['site_id'] ?? '') === (string) $site->id)>{{ $site->name }} ({{ $site->code }})</option>
                @endforeach
            </x-form-field>
            <x-filter-reset :href="route('reports.monthly-shifts')" />
        </form>
    </section>

    @if ($rows->isEmpty())
        <x-empty-state title="No guards for this filter" description="Adjust year, month, region or site to build a monthly summary." icon="report" />
    @else
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-14 px-5 py-3">#</th>
                            <th class="px-5 py-3">Guard</th>
                            <th class="px-5 py-3">Region / Site</th>
                            <th class="px-5 py-3 text-right">Normal</th>
                            <th class="px-5 py-3 text-right">OT</th>
                            <th class="px-5 py-3 text-right">Other</th>
                            <th class="px-5 py-3 text-right">Total</th>
                            <th class="px-5 py-3 text-right">Missed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $row)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3.5"><x-table-serial :iteration="$loop->iteration" /></td>
                                <td class="px-5 py-3.5">
                                    <p class="font-semibold text-slate-900">{{ $row['full_name'] }}</p>
                                    <p class="text-xs text-slate-500">{{ $row['employment_id'] }}</p>
                                </td>
                                <td class="px-5 py-3.5 text-slate-700">
                                    <p>{{ $row['region'] ?? '—' }}</p>
                                    <p class="text-xs text-slate-500">{{ $row['site'] ?? 'No site' }}</p>
                                </td>
                                <td class="px-5 py-3.5 text-right font-medium text-slate-900">{{ $row['normal_shifts'] }}</td>
                                <td class="px-5 py-3.5 text-right font-medium text-amber-800">{{ $row['overtime_shifts'] }}</td>
                                <td class="px-5 py-3.5 text-right text-slate-600">{{ $row['relief_shifts'] + $row['replacement_shifts'] + $row['special_duty_shifts'] }}</td>
                                <td class="px-5 py-3.5 text-right font-semibold text-brand-800">{{ $row['total_shifts'] }}</td>
                                <td class="px-5 py-3.5 text-right text-rose-700">{{ $row['missed_shifts'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
    </div>
</div>
@endsection
