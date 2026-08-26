@extends('layouts.app')

@section('title', 'Weekly Shifts Report')
@section('page-title', 'Weekly shifts')
@section('page-subtitle', 'Week board with completion rollups')

@section('content')
<div class="space-y-6">
    <x-page-header
        title="Weekly shifts report"
        subtitle="{{ \Carbon\Carbon::parse($week_start)->format('d M Y') }} – {{ \Carbon\Carbon::parse($week_end)->format('d M Y') }}"
        :back="route('reports.index')"
    >
        <x-slot:actions>
            <x-report-actions
                :csv="route('reports.weekly-shifts.export', array_merge($exportQuery, ['format' => 'csv']))"
            />
        </x-slot:actions>
    </x-page-header>

    <div class="report-print-area space-y-6">
        <section class="flex flex-row gap-2 sm:gap-3">
            @foreach ([['Total','total','text-slate-700'],['Completed','completed','text-emerald-700'],['Overtime','overtime','text-amber-800'],['Missed','missed','text-rose-700']] as [$label,$key,$tone])
                <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                    <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }} sm:text-[11px]">{{ $label }}</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ $summary[$key] }}</p>
                </div>
            @endforeach
        </section>

        <section class="no-print rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <form method="GET" action="{{ route('reports.weekly-shifts') }}" x-data x-ref="filterForm" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                <x-form-field label="Week starting" name="week_start" type="date" :value="$filters['week_start']" x-on:change="$refs.filterForm.requestSubmit()" />
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
                <x-filter-reset :href="route('reports.weekly-shifts')" />
            </form>
        </section>

        @if ($rows->isEmpty())
            <x-empty-state title="No shifts this week" description="Choose another week start date or clear filters." icon="calendar" />
        @else
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="w-14 px-5 py-3">#</th>
                                <th class="px-5 py-3">Date</th>
                                <th class="px-5 py-3">Guard</th>
                                <th class="px-5 py-3">Site</th>
                                <th class="px-5 py-3">Type</th>
                                <th class="px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($rows as $shift)
                                <tr class="hover:bg-slate-50/80">
                                    <td class="px-5 py-3.5"><x-table-serial :iteration="$loop->iteration" /></td>
                                    <td class="px-5 py-3.5 text-slate-700">{{ $shift->shift_date->format('D d M') }}</td>
                                    <td class="px-5 py-3.5">
                                        <p class="font-semibold text-slate-900">{{ $shift->assignedGuard?->full_name }}</p>
                                        <p class="text-xs text-slate-500">{{ $shift->assignedGuard?->employment_id }}</p>
                                    </td>
                                    <td class="px-5 py-3.5 text-slate-700">{{ $shift->site?->name }}</td>
                                    <td class="px-5 py-3.5 text-slate-700">{{ $shift->shift_type->label() }}</td>
                                    <td class="px-5 py-3.5"><x-status-badge :tone="$shift->status->tone()" :label="$shift->status->label()" /></td>
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
