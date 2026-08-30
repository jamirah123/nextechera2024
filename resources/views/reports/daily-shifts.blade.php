@extends('layouts.app')

@section('title', 'Daily Shifts Report')
@section('page-title', 'Daily shifts')
@section('page-subtitle', 'Shift board for a selected date')

@section('content')
<div class="space-y-3">
    <x-page-header
        title="Daily shifts report"
        subtitle="Scheduled, completed, missed and overtime for a single day."
        :back="route('reports.index')"
    >
        <x-slot:actions>
            <x-report-actions
                :csv="route('reports.daily-shifts.export', array_merge($exportQuery, ['format' => 'csv']))"
            />
        </x-slot:actions>
    </x-page-header>

    <div class="report-print-area space-y-3">
        <x-print.report-header title="Daily shifts report" subtitle="Scheduled, completed, missed and overtime for a selected date." />
        <section class="flex flex-row gap-2 sm:gap-3">
            @foreach ([['Total','total','text-slate-700'],['Completed','completed','text-emerald-700'],['Missed','missed','text-rose-700'],['Overtime','overtime','text-amber-800']] as [$label,$key,$tone])
                <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                    <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }} sm:text-[11px]">{{ $label }}</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-lg">{{ $summary[$key] }}</p>
                </div>
            @endforeach
        </section>

    <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <form method="GET" action="{{ route('reports.daily-shifts') }}" x-data x-ref="filterForm" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
            <x-form-field label="Date" name="date" type="date" :value="$filters['date']" x-on:change="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Region" name="region_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All regions</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" @selected((string) ($filters['region_id'] ?? '') === (string) $region->id)>{{ $region->name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Site" name="site_id" type="select" data-searchable="true" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All sites</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}" @selected((string) ($filters['site_id'] ?? '') === (string) $site->id)>{{ $site->name }}</option>
                @endforeach
            </x-form-field>
            <x-filter-reset :href="route('reports.daily-shifts')" />
        </form>
    </section>

    @if ($rows->isEmpty())
        <x-empty-state title="No shifts on this date" description="Pick another date or clear region/site filters." icon="calendar" />
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-14 px-3 py-2">#</th>
                            <th class="px-3 py-2">Reference</th>
                            <th class="px-3 py-2">Guard</th>
                            <th class="px-3 py-2">Site</th>
                            <th class="px-3 py-2">Period / Type</th>
                            <th class="px-3 py-2">Time</th>
                            <th class="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $shift)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-3 py-2"><x-table-serial :iteration="$loop->iteration" /></td>
                                <td class="px-3 py-2 font-medium text-slate-900">{{ $shift->reference }}</td>
                                <td class="px-3 py-2">
                                    <p class="font-semibold text-slate-900">{{ $shift->assignedGuard?->full_name }}</p>
                                    <p class="text-xs text-slate-500">{{ $shift->assignedGuard?->employment_id }}</p>
                                </td>
                                <td class="px-3 py-2 text-slate-700">{{ $shift->site?->name }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $shift->period->label() }} · {{ $shift->shift_type->label() }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $shift->starts_at->format('H:i') }}–{{ $shift->ends_at->format('H:i') }}</td>
                                <td class="px-3 py-2"><x-status-badge :tone="$shift->status->tone()" :label="$shift->status->label()" /></td>
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
