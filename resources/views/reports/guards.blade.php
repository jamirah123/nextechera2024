@extends('layouts.app')

@section('title', 'Guards Report')
@section('page-title', 'Guards report')
@section('page-subtitle', 'Employment and operational status')

@section('content')
@php
    use App\Enums\EmploymentStatus;
    use App\Enums\OperationalStatus;
@endphp
<div class="space-y-3">
    <x-page-header
        title="Guards report"
        subtitle="Company-wide employment and operational status snapshot."
        :back="route('reports.index')"
    >
        <x-slot:actions>
            <x-report-actions
                :csv="route('reports.guards.export', array_merge($exportQuery, ['format' => 'csv']))"
            />
        </x-slot:actions>
    </x-page-header>

    <div class="report-print-area space-y-3">
        <x-print.report-header title="Guards report" subtitle="Company-wide employment and operational status snapshot." />
        <section class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3">
            @foreach ([['Total','total','text-slate-700'],['Active','active','text-emerald-700'],['On leave','on_leave','text-sky-700'],['Absent','absent','text-amber-800'],['Deserted','deserted','text-rose-700']] as [$label,$key,$tone])
                <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                    <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }} sm:text-[11px]">{{ $label }}</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-lg">{{ $summary[$key] }}</p>
                </div>
            @endforeach
        </section>

        <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
            <form method="GET" action="{{ route('reports.guards') }}" x-data x-ref="filterForm" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                <x-form-field label="Region" name="region_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                    <option value="">All regions</option>
                    @foreach ($regions as $region)
                        <option value="{{ $region->id }}" @selected((string) ($filters['region_id'] ?? '') === (string) $region->id)>{{ $region->name }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Employment" name="employment_status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                    <option value="">All</option>
                    @foreach (EmploymentStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['employment_status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Operational" name="operational_status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                    <option value="">All</option>
                    @foreach (OperationalStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['operational_status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-filter-reset :href="route('reports.guards')" />
            </form>
        </section>

        @if ($rows->isEmpty())
            <x-empty-state title="No guards match" description="Clear filters to see the full registry." icon="shield" />
        @else
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="w-14 px-3 py-2">#</th>
                                <th class="px-3 py-2">Guard</th>
                                <th class="px-3 py-2">Region</th>
                                <th class="px-3 py-2">Site</th>
                                <th class="px-3 py-2">Employment</th>
                                <th class="px-3 py-2">Operational</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($rows as $guard)
                                <tr class="hover:bg-slate-50/80">
                                    <td class="px-3 py-2"><x-table-serial :iteration="$loop->iteration" /></td>
                                    <td class="px-3 py-2">
                                        <p class="font-semibold text-slate-900">{{ $guard->full_name }}</p>
                                        <p class="text-xs text-slate-500">{{ $guard->employment_id }}</p>
                                    </td>
                                    <td class="px-3 py-2 text-slate-700">{{ $guard->region?->name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-slate-700">{{ $guard->currentSite?->name ?? '—' }}</td>
                                    <td class="px-3 py-2"><x-status-badge :tone="$guard->employment_status->tone()" :label="$guard->employment_status->label()" /></td>
                                    <td class="px-3 py-2"><x-status-badge :tone="$guard->operational_status->tone()" :label="$guard->operational_status->label()" /></td>
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
