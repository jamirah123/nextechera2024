@extends('layouts.app')

@section('title', 'Manpower Coverage')
@section('page-title', 'Manpower Coverage')
@section('page-subtitle', 'Required vs deployed guards by site')

@section('content')
<div class="space-y-6">
    <x-page-header
        title="Manpower coverage"
        subtitle="Track staffing gaps across active and filtered sites."
        :back="route('organization.index')"
    >
        <x-slot:actions>
            <div class="flex flex-wrap items-center gap-2">
                <a
                    href="{{ route('manpower.coverage.export', array_merge($exportQuery, ['format' => 'csv'])) }}"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                >
                    <x-icon name="download" class="h-4 w-4" />
                    Export CSV
                </a>
                <a
                    href="{{ route('manpower.coverage.export', array_merge($exportQuery, ['format' => 'excel'])) }}"
                    class="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800"
                >
                    <x-icon name="report" class="h-4 w-4" />
                    Export Excel
                </a>
                <a href="{{ route('sites.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Manage sites
                </a>
            </div>
        </x-slot:actions>
    </x-page-header>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5 sm:gap-4">
        <x-kpi-card label="Required" :value="number_format($company['required'])" tone="brand" />
        <x-kpi-card label="Deployed" :value="number_format($company['deployed'])" tone="emerald" />
        <x-kpi-card label="Shortage" :value="number_format($company['shortage'])" tone="rose" />
        <x-kpi-card label="Surplus" :value="number_format($company['surplus'])" tone="amber" />
        <x-kpi-card
            label="Coverage"
            :value="$company['coverage_percent'].'%'"
            :hint="$company['status']->label()"
            tone="indigo"
        />
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <form
            method="GET"
            action="{{ route('manpower.coverage') }}"
            x-data
            x-ref="filterForm"
            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end"
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
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-950">
            <p>
                <span class="font-semibold">Coverage report ready.</span>
                Export the filtered site manpower summary for management review.
            </p>
            <div class="flex flex-wrap gap-2">
                <a
                    href="{{ route('manpower.coverage.export', array_merge($exportQuery, ['format' => 'csv'])) }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-brand-200 bg-white px-3 py-1.5 text-xs font-semibold text-brand-800 hover:bg-brand-50"
                >
                    CSV
                </a>
                <a
                    href="{{ route('manpower.coverage.export', array_merge($exportQuery, ['format' => 'excel'])) }}"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-800"
                >
                    Excel
                </a>
            </div>
        </div>
        {{-- Desktop table --}}
        <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:block">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-14 px-5 py-3">#</th>
                            <th class="px-5 py-3">Site</th>
                            <th class="px-5 py-3">Region</th>
                            <th class="px-5 py-3 text-right">Required</th>
                            <th class="px-5 py-3 text-right">Deployed</th>
                            <th class="px-5 py-3 text-right">Shortage</th>
                            <th class="px-5 py-3 text-right">Coverage</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $row)
                            @php
                                $site = $row['site'];
                                $mp = $row['manpower'];
                            @endphp
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3.5">
                                    <x-table-serial :paginator="$rows" :index="$loop->index" />
                                </td>
                                <td class="px-5 py-3.5">
                                    <a href="{{ route('sites.show', $site) }}" class="font-semibold text-slate-900 hover:text-brand-700">
                                        {{ $site->name }}
                                    </a>
                                    <p class="text-xs text-slate-500">{{ $site->code }}</p>
                                </td>
                                <td class="px-5 py-3.5 text-slate-600">
                                    {{ $site->region?->name ?? '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-right font-medium text-slate-900">{{ $mp['required'] }}</td>
                                <td class="px-5 py-3.5 text-right text-slate-700">{{ $mp['deployed'] }}</td>
                                <td class="px-5 py-3.5 text-right font-medium text-rose-700">{{ $mp['shortage'] }}</td>
                                <td class="px-5 py-3.5 text-right font-semibold text-slate-900">{{ $mp['coverage_percent'] }}%</td>
                                <td class="px-5 py-3.5">
                                    <x-status-badge :tone="$mp['status']->tone()" :label="$mp['status']->label()" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Mobile / tablet cards --}}
        <div class="grid gap-3 lg:hidden">
            @foreach ($rows as $row)
                @php
                    $site = $row['site'];
                    $mp = $row['manpower'];
                @endphp
                <a href="{{ route('sites.show', $site) }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-slate-900">{{ $site->name }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $site->code }} · {{ $site->region?->name ?? 'No region' }}</p>
                        </div>
                        <x-status-badge :tone="$mp['status']->tone()" :label="$mp['status']->label()" />
                    </div>
                    <dl class="mt-4 grid grid-cols-4 gap-2 text-center">
                        <div class="rounded-xl bg-slate-50 px-2 py-2">
                            <dt class="text-[10px] font-medium uppercase text-slate-500">Req</dt>
                            <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ $mp['required'] }}</dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-2 py-2">
                            <dt class="text-[10px] font-medium uppercase text-slate-500">Dep</dt>
                            <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ $mp['deployed'] }}</dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-2 py-2">
                            <dt class="text-[10px] font-medium uppercase text-slate-500">Short</dt>
                            <dd class="mt-0.5 text-sm font-semibold text-rose-700">{{ $mp['shortage'] }}</dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-2 py-2">
                            <dt class="text-[10px] font-medium uppercase text-slate-500">Cov</dt>
                            <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ $mp['coverage_percent'] }}%</dd>
                        </div>
                    </dl>
                </a>
            @endforeach
        </div>

        @if ($rows->hasPages() || $rows->total() > 0)
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500">
                    Showing {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} of {{ $rows->total() }}
                </p>
                <div>{{ $rows->links() }}</div>
            </div>
        @endif
    @endif
</div>
@endsection
