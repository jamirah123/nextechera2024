@extends('layouts.app')

@section('title', 'Deployments Report')
@section('page-title', 'Deployments report')
@section('page-subtitle', 'Active deployments and transfers')

@section('content')
<div class="space-y-3">
    <x-page-header
        title="Deployments report"
        subtitle="Current assignments and recent transfer activity."
        :back="route('reports.index')"
    >
        <x-slot:actions>
            <x-report-actions
                :csv="route('reports.deployments.export', array_merge($exportQuery, ['format' => 'csv']))"
                save-report="deployments"
                :save-fields="$exportQuery"
            />
        </x-slot:actions>
    </x-page-header>

    <div class="report-print-area space-y-3">
        <x-print.report-header title="Deployments report" subtitle="Current assignments and recent transfer activity." />
        <section class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3">
            @foreach ([['Active','active','text-emerald-700'],['Transferred','transferred','text-sky-700'],['Ended','ended','text-slate-600'],['Transfers listed','transfers','text-amber-800']] as [$label,$key,$tone])
                <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                    <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }} sm:text-[11px]">{{ $label }}</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-lg">{{ $summary[$key] }}</p>
                </div>
            @endforeach
        </section>

        <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
            <form method="GET" action="{{ route('reports.deployments') }}" x-data x-ref="filterForm" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
                <x-form-field label="Scope" name="current_only" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                    <option value="1" @selected(($filters['current_only'] ?? true))>Current only</option>
                    <option value="0" @selected(! ($filters['current_only'] ?? true))>All statuses</option>
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
                        <option value="{{ $site->id }}" @selected((string) ($filters['site_id'] ?? '') === (string) $site->id)>{{ $site->name }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Transfers from" name="from" type="date" :value="$filters['from'] ?? ''" x-on:change="$refs.filterForm.requestSubmit()" />
                <x-filter-reset :href="route('reports.deployments')" />
            </form>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-3 py-2.5">
                    <h2 class="text-sm font-semibold text-slate-900">Deployments</h2>
                </div>
                @if ($deployments->isEmpty())
                    <div class="p-6"><x-empty-state title="No deployments" description="No records match the current filters." icon="map" /></div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                            <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="w-14 px-3 py-2">#</th>
                                    <th class="px-3 py-2">Guard</th>
                                    <th class="px-3 py-2">Site</th>
                                    <th class="px-3 py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($deployments as $deployment)
                                    <tr class="hover:bg-slate-50/80">
                                        <td class="px-3 py-2"><x-table-serial :iteration="$loop->iteration" /></td>
                                        <td class="px-3 py-2">
                                            <p class="font-semibold text-slate-900">{{ $deployment->assignedGuard?->full_name }}</p>
                                            <p class="text-xs text-slate-500">{{ $deployment->assignedGuard?->employment_id }}</p>
                                        </td>
                                        <td class="px-3 py-2 text-slate-700">{{ $deployment->site?->name }}</td>
                                        <td class="px-3 py-2"><x-status-badge :tone="$deployment->status->tone()" :label="$deployment->status->label()" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-3 py-2.5">
                    <h2 class="text-sm font-semibold text-slate-900">Recent transfers</h2>
                </div>
                @if ($transfers->isEmpty())
                    <div class="p-6"><x-empty-state title="No transfers" description="No transfer activity in the selected window." icon="swap" /></div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                            <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="w-14 px-3 py-2">#</th>
                                    <th class="px-3 py-2">From → To</th>
                                    <th class="px-3 py-2">Effective</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($transfers as $transfer)
                                    <tr class="hover:bg-slate-50/80">
                                        <td class="px-3 py-2"><x-table-serial :iteration="$loop->iteration" /></td>
                                        <td class="px-3 py-2 text-slate-700">{{ $transfer->fromSite?->name ?? '—' }} → {{ $transfer->toSite?->name ?? '—' }}</td>
                                        <td class="px-3 py-2 text-slate-700">{{ optional($transfer->effective_at)->format('d M Y H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
@endsection
