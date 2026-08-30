@extends('layouts.app')

@section('title', 'Organization')
@section('page-title', 'Organization')
@section('page-subtitle', 'Regions, supervisors, clients, sites and manpower coverage')

@section('content')
<div class="space-y-3">
    <x-page-header
        title="Organization overview"
        subtitle="Monitor structure, coverage and recent site activity across the company."
    >
        <x-slot:actions>
            <a href="{{ route('manpower.coverage') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                <x-icon name="chart" class="h-3.5 w-3.5" />
                Coverage report
            </a>
            @if ($canManage)
                <a href="{{ route('sites.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">
                    <x-icon name="plus" class="h-3.5 w-3.5" />
                    New site
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4 sm:gap-4">
        <x-kpi-card label="Regions" :value="$stats['regions']" :hint="$stats['active_regions'].' active'" tone="brand" />
        <x-kpi-card label="Supervisors" :value="$stats['supervisors']" :hint="$stats['active_supervisors'].' active'" tone="indigo" />
        <x-kpi-card label="Clients" :value="$stats['clients']" :hint="$stats['active_clients'].' active contracts'" tone="sky" />
        <x-kpi-card label="Sites" :value="$stats['sites']" :hint="$stats['active_sites'].' active'" tone="emerald" />
    </section>

    <section class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-2.5 shadow-sm lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Company manpower</h2>
                    <p class="mt-1 text-sm text-slate-500">Required vs deployed across active sites.</p>
                </div>
                <x-status-badge :tone="$manpower['status']->tone()" :label="$manpower['status']->label()" />
            </div>

            <div class="mt-5 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Required</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900">{{ number_format($manpower['required']) }}</p>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Deployed</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900">{{ number_format($manpower['deployed']) }}</p>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Shortage</p>
                    <p class="mt-1 text-xl font-semibold text-rose-700">{{ number_format($manpower['shortage']) }}</p>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Coverage</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900">{{ $manpower['coverage_percent'] }}%</p>
                </div>
            </div>

            <div class="mt-4">
                <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                    <div
                        class="h-full rounded-full bg-brand-600 transition-all"
                        style="width: {{ min(100, $manpower['coverage_percent']) }}%"
                    ></div>
                </div>
                <p class="mt-2 text-xs text-slate-500">
                    Surplus {{ number_format($manpower['surplus']) }}
                    · {{ $manpower['understaffed_sites'] ?? 0 }} understaffed sites
                </p>
            </div>
        </div>

        <div class="form-card">
            <h2 class="text-base font-semibold text-slate-900">Quick links</h2>
            <p class="mt-1 text-sm text-slate-500">Jump into organization modules.</p>
            <div class="mt-4 space-y-2">
                <a href="{{ route('regions.index') }}" class="flex items-center justify-between rounded-xl border border-slate-100 px-3.5 py-3 text-sm font-medium text-slate-700 hover:border-brand-200 hover:bg-brand-50/50">
                    <span class="inline-flex items-center gap-2"><x-icon name="map" class="h-3.5 w-3.5 text-brand-700" /> Regions</span>
                    <x-icon name="chevron" class="h-3.5 w-3.5 text-slate-400" />
                </a>
                <a href="{{ route('supervisors.index') }}" class="flex items-center justify-between rounded-xl border border-slate-100 px-3.5 py-3 text-sm font-medium text-slate-700 hover:border-brand-200 hover:bg-brand-50/50">
                    <span class="inline-flex items-center gap-2"><x-icon name="users" class="h-3.5 w-3.5 text-indigo-600" /> Supervisors</span>
                    <x-icon name="chevron" class="h-3.5 w-3.5 text-slate-400" />
                </a>
                <a href="{{ route('clients.index') }}" class="flex items-center justify-between rounded-xl border border-slate-100 px-3.5 py-3 text-sm font-medium text-slate-700 hover:border-brand-200 hover:bg-brand-50/50">
                    <span class="inline-flex items-center gap-2"><x-icon name="building" class="h-3.5 w-3.5 text-sky-600" /> Clients</span>
                    <x-icon name="chevron" class="h-3.5 w-3.5 text-slate-400" />
                </a>
                <a href="{{ route('sites.index') }}" class="flex items-center justify-between rounded-xl border border-slate-100 px-3.5 py-3 text-sm font-medium text-slate-700 hover:border-brand-200 hover:bg-brand-50/50">
                    <span class="inline-flex items-center gap-2"><x-icon name="shield" class="h-3.5 w-3.5 text-emerald-600" /> Sites</span>
                    <x-icon name="chevron" class="h-3.5 w-3.5 text-slate-400" />
                </a>
                <a href="{{ route('manpower.coverage') }}" class="flex items-center justify-between rounded-xl border border-slate-100 px-3.5 py-3 text-sm font-medium text-slate-700 hover:border-brand-200 hover:bg-brand-50/50">
                    <span class="inline-flex items-center gap-2"><x-icon name="chart" class="h-3.5 w-3.5 text-amber-600" /> Manpower coverage</span>
                    <x-icon name="chevron" class="h-3.5 w-3.5 text-slate-400" />
                </a>
            </div>
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2.5">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Recent sites</h2>
                    <p class="text-sm text-slate-500">Latest security sites added.</p>
                </div>
                <a href="{{ route('sites.index') }}" class="text-xs font-semibold text-brand-700 hover:text-brand-800">View all</a>
            </div>

            @if ($recentSites->isEmpty())
                <div class="p-5 sm:p-6">
                    <x-empty-state
                        title="No sites yet"
                        description="Create your first security site to start tracking manpower."
                        icon="shield"
                    >
                        @if ($canManage)
                            <x-slot:actions>
                                <a href="{{ route('sites.create') }}" class="btn btn-primary">
                                    Create site
                                </a>
                            </x-slot:actions>
                        @endif
                    </x-empty-state>
                </div>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($recentSites as $site)
                        <li>
                            <a href="{{ route('sites.show', $site) }}" class="flex items-start justify-between gap-3 px-3 py-2.5 hover:bg-slate-50 sm:px-6">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $site->name }}</p>
                                    <p class="mt-0.5 truncate text-xs text-slate-500">
                                        {{ $site->code }}
                                        @if ($site->client) · {{ $site->client->name }} @endif
                                        @if ($site->region) · {{ $site->region->name }} @endif
                                    </p>
                                </div>
                                <x-status-badge :tone="$site->status->tone()" :label="$site->status->label()" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2.5">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Coverage watchlist</h2>
                    <p class="text-sm text-slate-500">High-requirement active sites.</p>
                </div>
                <a href="{{ route('manpower.coverage') }}" class="text-xs font-semibold text-brand-700 hover:text-brand-800">Full report</a>
            </div>

            @if (count($understaffedPreview) === 0)
                <div class="p-5 sm:p-6">
                    <x-empty-state
                        title="No sites to review"
                        description="Active sites with manpower requirements will appear here."
                        icon="chart"
                    />
                </div>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($understaffedPreview as $row)
                        @php
                            $site = $row['site'];
                            $mp = $row['manpower'];
                        @endphp
                        <li class="flex items-start justify-between gap-3 px-3 py-2.5">
                            <div class="min-w-0">
                                <a href="{{ route('sites.show', $site) }}" class="truncate text-sm font-semibold text-slate-900 hover:text-brand-700">
                                    {{ $site->name }}
                                </a>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    Req {{ $mp['required'] }} · Dep {{ $mp['deployed'] }} · Short {{ $mp['shortage'] }}
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-sm font-semibold text-slate-900">{{ $mp['coverage_percent'] }}%</p>
                                <x-status-badge class="mt-1" :tone="$mp['status']->tone()" :label="$mp['status']->label()" />
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
</div>
@endsection
