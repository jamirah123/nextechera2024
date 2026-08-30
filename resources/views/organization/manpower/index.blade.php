@extends('layouts.app')

@section('title', 'Manpower Coverage')
@section('page-title', 'Manpower Coverage')
@section('page-subtitle', 'Required vs deployed guards by site')

@section('content')
<div class="space-y-3">
    <x-page-header
        title="Manpower coverage"
        subtitle="Required vs deployed guards by site."
        :back="route('organization.index')"
    >
        <x-slot:actions>
            <x-report-actions
                :csv="route('manpower.coverage.export', array_merge($exportQuery, ['format' => 'csv']))"
            >
                <a href="{{ route('sites.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Manage sites
                </a>
            </x-report-actions>
        </x-slot:actions>
    </x-page-header>

    <div class="report-print-area space-y-3">
        <x-print.report-header title="Manpower coverage" subtitle="Required vs deployed guards by site." />
    <section class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-5">
        <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Required</p>
            <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ number_format($company['required']) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700">Deployed</p>
            <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ number_format($company['deployed']) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-rose-700">Shortage</p>
            <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ number_format($company['shortage']) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700">Surplus</p>
            <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ number_format($company['surplus']) }}</p>
        </div>
        <div class="col-span-2 rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm sm:col-span-1">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-indigo-700">Coverage</p>
            <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ $company['coverage_percent'] }}%</p>
            <p class="text-[10px] text-slate-500">{{ $company['status']->label() }}</p>
        </div>
    </section>

    <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <form
            method="GET"
            action="{{ route('manpower.coverage') }}"
            x-data
            x-ref="filterForm"
            class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 lg:items-end"
        >
            <x-form-field label="Region" name="region_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All regions</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" @selected((string) ($filters['region_id'] ?? '') === (string) $region->id)>
                        {{ $region->name }} ({{ $region->code }})
                    </option>
                @endforeach
            </x-form-field>

            <x-form-field label="Site status" name="status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? 'active') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </x-form-field>

            <x-filter-reset :href="route('manpower.coverage')" />
        </form>
    </section>

    @if ($rows->isEmpty())
        <x-empty-state
            title="No sites match these filters"
            description="Try another region or status to review manpower coverage. You can still export the current filter set."
            icon="chart"
        >
            <x-slot:actions>
                <a
                    href="{{ route('manpower.coverage.export', array_merge($exportQuery, ['format' => 'csv'])) }}"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Export empty CSV
                </a>
            </x-slot:actions>
        </x-empty-state>
    @else
        <div class="no-print flex flex-wrap items-center justify-between gap-2 rounded-lg border border-brand-100 bg-brand-50 px-3 py-2 text-xs text-brand-950">
            <p>
                <span class="font-semibold">Coverage report ready.</span>
                Export the filtered site manpower summary.
            </p>
            <a
                href="{{ route('manpower.coverage.export', array_merge($exportQuery, ['format' => 'csv'])) }}"
                class="inline-flex items-center gap-1 rounded-lg bg-brand-700 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-brand-800"
            >
                Export CSV
            </a>
        </div>
        {{-- Desktop table --}}
        <div class="data-table-shell hidden lg:block">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-10">#</th>
                            <th>Site</th>
                            <th>Region</th>
                            <th class="text-right">Required</th>
                            <th class="text-right">Deployed</th>
                            <th class="text-right">Shortage</th>
                            <th class="text-right">Coverage</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $site = $row['site'];
                                $mp = $row['manpower'];
                            @endphp
                            <tr class="hover:bg-slate-50/80">
                                <td class="text-slate-500">
                                    <x-table-serial :paginator="$rows" :index="$loop->index" />
                                </td>
                                <td>
                                    <a href="{{ route('sites.show', $site) }}" class="font-semibold text-slate-900 hover:text-brand-700">
                                        {{ $site->name }}
                                    </a>
                                    <p class="text-[10px] text-slate-500">{{ $site->code }}</p>
                                </td>
                                <td class="text-slate-600">
                                    {{ $site->region?->name ?? '—' }}
                                </td>
                                <td class="text-right font-medium text-slate-900">{{ $mp['required'] }}</td>
                                <td class="text-right text-slate-700">{{ $mp['deployed'] }}</td>
                                <td class="text-right font-medium text-rose-700">{{ $mp['shortage'] }}</td>
                                <td class="text-right font-semibold text-slate-900">{{ $mp['coverage_percent'] }}%</td>
                                <td>
                                    <x-status-badge :tone="$mp['status']->tone()" :label="$mp['status']->label()" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Mobile / tablet cards --}}
        <div class="grid gap-2 lg:hidden">
            @foreach ($rows as $row)
                @php
                    $site = $row['site'];
                    $mp = $row['manpower'];
                @endphp
                <a href="{{ route('sites.show', $site) }}" class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate text-xs font-semibold text-slate-900">{{ $site->name }}</p>
                            <p class="mt-0.5 text-[10px] text-slate-500">{{ $site->code }} · {{ $site->region?->name ?? 'No region' }}</p>
                        </div>
                        <x-status-badge :tone="$mp['status']->tone()" :label="$mp['status']->label()" />
                    </div>
                    <dl class="mt-2 grid grid-cols-4 gap-1.5 text-center">
                        <div class="rounded-md bg-slate-50 px-1.5 py-1">
                            <dt class="text-[9px] font-medium uppercase text-slate-500">Req</dt>
                            <dd class="mt-0.5 text-xs font-semibold text-slate-900">{{ $mp['required'] }}</dd>
                        </div>
                        <div class="rounded-md bg-slate-50 px-1.5 py-1">
                            <dt class="text-[9px] font-medium uppercase text-slate-500">Dep</dt>
                            <dd class="mt-0.5 text-xs font-semibold text-slate-900">{{ $mp['deployed'] }}</dd>
                        </div>
                        <div class="rounded-md bg-slate-50 px-1.5 py-1">
                            <dt class="text-[9px] font-medium uppercase text-slate-500">Short</dt>
                            <dd class="mt-0.5 text-xs font-semibold text-rose-700">{{ $mp['shortage'] }}</dd>
                        </div>
                        <div class="rounded-md bg-slate-50 px-1.5 py-1">
                            <dt class="text-[9px] font-medium uppercase text-slate-500">Cov</dt>
                            <dd class="mt-0.5 text-xs font-semibold text-slate-900">{{ $mp['coverage_percent'] }}%</dd>
                        </div>
                    </dl>
                </a>
            @endforeach
        </div>

        @if ($rows->hasPages() || $rows->total() > 0)
            <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[10px] text-slate-500">
                    Showing {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} of {{ $rows->total() }}
                </p>
                <div>{{ $rows->links() }}</div>
            </div>
        @endif
    @endif
    </div>
</div>
@endsection
