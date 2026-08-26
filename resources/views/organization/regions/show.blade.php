@extends('layouts.app')

@section('title', $region->name)
@section('page-title', 'Region details')
@section('page-subtitle', $region->code)

@section('content')
<div class="space-y-6">
    <x-page-header
        :title="$region->name"
        :subtitle="$region->description ?: 'Operational region details and coverage.'"
        :back="route('regions.index')"
    >
        <x-slot:actions>
            <a href="{{ route('ops-dashboards.region', $region) }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">
                Ops dashboard
            </a>
            @if ($canManage)
                <a href="{{ route('regions.edit', $region) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Edit
                </a>
            @endif
            @if ($canDelete ?? false)
                <x-delete-button
                    :action="route('regions.destroy', $region)"
                    label="Delete"
                    size="md"
                    confirm="Delete this region? It will be archived if it has no linked supervisors or sites."
                />
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-base font-semibold text-slate-900">Region details</h2>
                    <x-status-badge :tone="$region->status->tone()" :label="$region->status->label()" />
                </div>
            </div>
            <dl class="grid gap-0 sm:grid-cols-2">
                <div class="border-b border-slate-100 px-5 py-4 sm:border-r sm:px-6">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Code</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $region->code }}</dd>
                </div>
                <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Manager</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $region->manager_name ?: '—' }}</dd>
                </div>
                <div class="border-b border-slate-100 px-5 py-4 sm:border-r sm:border-b-0 sm:px-6 sm:py-5">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Manager phone</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $region->manager_phone ?: '—' }}</dd>
                </div>
                <div class="px-5 py-4 sm:px-6 sm:py-5">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Description</dt>
                    <dd class="mt-1 text-sm text-slate-700">{{ $region->description ?: '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-base font-semibold text-slate-900">Manpower</h2>
                <x-status-badge :tone="$manpower['status']->tone()" :label="$manpower['status']->label()" />
            </div>
            <dl class="mt-4 space-y-3">
                <div class="flex items-center justify-between text-sm">
                    <dt class="text-slate-500">Required</dt>
                    <dd class="font-semibold text-slate-900">{{ number_format($manpower['required']) }}</dd>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <dt class="text-slate-500">Deployed</dt>
                    <dd class="font-semibold text-slate-900">{{ number_format($manpower['deployed']) }}</dd>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <dt class="text-slate-500">Shortage</dt>
                    <dd class="font-semibold text-rose-700">{{ number_format($manpower['shortage']) }}</dd>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <dt class="text-slate-500">Coverage</dt>
                    <dd class="font-semibold text-slate-900">{{ $manpower['coverage_percent'] }}%</dd>
                </div>
            </dl>
            <p class="mt-4 text-xs text-slate-500">
                {{ $manpower['sites_count'] ?? 0 }} active sites · {{ $manpower['understaffed_sites'] ?? 0 }} understaffed
            </p>
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6">
                <h2 class="text-base font-semibold text-slate-900">Supervisors</h2>
                <span class="text-xs font-medium text-slate-500">{{ $region->supervisors->count() }}</span>
            </div>
            @if ($region->supervisors->isEmpty())
                <div class="p-5 sm:p-6">
                    <x-empty-state title="No supervisors" description="Assign supervisors to this region." icon="users" />
                </div>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($region->supervisors as $supervisor)
                        <li>
                            <a href="{{ route('supervisors.show', $supervisor) }}" class="flex items-center justify-between gap-3 px-5 py-3.5 hover:bg-slate-50 sm:px-6">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $supervisor->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $supervisor->supervisor_code }} · {{ $supervisor->sites_count }} sites</p>
                                </div>
                                <x-status-badge :tone="$supervisor->status->tone()" :label="$supervisor->status->label()" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6">
                <h2 class="text-base font-semibold text-slate-900">Sites</h2>
                <span class="text-xs font-medium text-slate-500">{{ $region->sites->count() }}</span>
            </div>
            @if ($region->sites->isEmpty())
                <div class="p-5 sm:p-6">
                    <x-empty-state title="No sites" description="Security sites in this region will list here." icon="shield" />
                </div>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($region->sites as $site)
                        <li>
                            <a href="{{ route('sites.show', $site) }}" class="flex items-center justify-between gap-3 px-5 py-3.5 hover:bg-slate-50 sm:px-6">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $site->name }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $site->code }}
                                        @if ($site->client) · {{ $site->client->name }} @endif
                                    </p>
                                </div>
                                <x-status-badge :tone="$site->status->tone()" :label="$site->status->label()" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
</div>
@endsection
