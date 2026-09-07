@extends('layouts.app')

@section('title', $site->name)
@section('page-title', 'Site details')
@section('page-subtitle', $site->code)

@section('content')
<div class="space-y-3">
    <x-page-header
        :title="$site->name"
        :subtitle="$site->physical_location ?: 'Security site details and manpower.'"
        :back="route('sites.index')"
    >
        <x-slot:actions>
            <a href="{{ route('ops-dashboards.site', $site) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                Ops dashboard
            </a>
            @if ($canDeploy ?? false)
                <a href="{{ route('deployments.create', ['site_id' => $site->id]) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">
                    Deploy guard
                </a>
            @endif
            <a href="{{ route('deployments.index', ['site_id' => $site->id, 'current_only' => 1]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                View deployments
            </a>
            @if ($canManage)
                <a href="{{ route('sites.edit', $site) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Edit
                </a>
            @endif
            @if ($canDelete ?? false)
                <x-delete-button
                    :action="route('sites.destroy', $site)"
                    label="Delete"
                    size="md"
                    confirm="Delete this site? It will be archived from active operations."
                />
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="space-y-3 xl:col-span-2">

    <section class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4 sm:gap-4">
        <x-kpi-card label="Required" :value="$manpower['required']" tone="brand" />
        <x-kpi-card label="Deployed" :value="$manpower['deployed']" tone="emerald" />
        <x-kpi-card label="Shortage" :value="$manpower['shortage']" tone="rose" />
        <x-kpi-card
            label="Coverage"
            :value="$manpower['coverage_percent'].'%'"
            :hint="$manpower['status']->label()"
            tone="indigo"
        />
    </section>

    <section class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white shadow-sm lg:col-span-2">
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-3 py-2">
                <h2 class="text-sm font-semibold text-slate-900">Site details</h2>
                <x-status-badge :tone="$site->status->tone()" :label="$site->status->label()" />
            </div>
            <dl class="grid gap-0 sm:grid-cols-2 text-xs">
                <div class="border-b border-slate-100 px-3 py-2 sm:border-r sm:px-4">
                    <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Client</dt>
                    <dd class="mt-0.5 font-medium text-slate-900">
                        @if ($site->client)
                            <a href="{{ route('clients.show', $site->client) }}" class="text-brand-700 hover:text-brand-800">{{ $site->client->name }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="border-b border-slate-100 px-3 py-2">
                    <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Region</dt>
                    <dd class="mt-0.5 font-medium text-slate-900">
                        @if ($site->region)
                            <a href="{{ route('regions.show', $site->region) }}" class="text-brand-700 hover:text-brand-800">{{ $site->region->name }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="border-b border-slate-100 px-3 py-2 sm:border-r sm:px-4">
                    <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Supervisor</dt>
                    <dd class="mt-0.5 font-medium text-slate-900">
                        @if ($site->supervisor)
                            <a href="{{ route('supervisors.show', $site->supervisor) }}" class="text-brand-700 hover:text-brand-800">{{ $site->supervisor->name }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="border-b border-slate-100 px-3 py-2">
                    <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Code</dt>
                    <dd class="mt-0.5 font-medium text-slate-900">{{ $site->code }}</dd>
                </div>
                <div class="border-b border-slate-100 px-3 py-2 sm:border-r sm:px-4">
                    <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Contact</dt>
                    <dd class="mt-0.5 font-medium text-slate-900">{{ $site->site_contact_person ?: '—' }}</dd>
                    @if ($site->site_contact_phone)
                        <dd class="text-[11px] text-slate-500">{{ $site->site_contact_phone }}</dd>
                    @endif
                </div>
                <div class="border-b border-slate-100 px-3 py-2">
                    <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Posts</dt>
                    <dd class="mt-0.5 font-medium text-slate-900">{{ $site->number_of_posts }}</dd>
                </div>
                <div class="border-b border-slate-100 px-3 py-2 sm:border-r sm:px-4">
                    <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Day manpower</dt>
                    <dd class="mt-0.5 font-medium text-slate-900">{{ $manpower['required_day'] }} guards</dd>
                    <dd class="text-[11px] text-slate-500">{{ $manpower['required_day_armed'] }} armed · {{ $manpower['required_day_unarmed'] }} unarmed</dd>
                </div>
                <div class="border-b border-slate-100 px-3 py-2">
                    <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Night manpower</dt>
                    <dd class="mt-0.5 font-medium text-slate-900">{{ $manpower['required_night'] }} guards</dd>
                    <dd class="text-[11px] text-slate-500">{{ $manpower['required_night_armed'] }} armed · {{ $manpower['required_night_unarmed'] }} unarmed</dd>
                </div>
                <div class="border-b border-slate-100 px-3 py-2 sm:border-r sm:px-4">
                    <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Contract</dt>
                    <dd class="mt-0.5 font-medium text-slate-900">
                        {{ optional($site->contract_start_date)->format('d M Y') ?: '—' }}
                        @if ($site->contract_end_date)
                            – {{ optional($site->contract_end_date)->format('d M Y') }}
                        @endif
                    </dd>
                </div>
                <div class="border-b border-slate-100 px-3 py-2">
                    <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Coordinates</dt>
                    <dd class="mt-0.5 font-medium text-slate-900">
                        @if ($site->latitude && $site->longitude)
                            {{ $site->latitude }}, {{ $site->longitude }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="px-3 py-2 sm:col-span-2 sm:px-4">
                    <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Location / notes</dt>
                    <dd class="mt-0.5 text-slate-700">{{ $site->physical_location ?: '—' }}</dd>
                    @if ($site->notes)
                        <dd class="mt-1 text-slate-600">{{ $site->notes }}</dd>
                    @endif
                </div>
            </dl>
        </div>

        <div class="space-y-4">
            <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-900">Shift manpower</h2>
                <p class="mt-0.5 text-[11px] text-slate-500">Day vs night, armed vs unarmed.</p>
                <div class="mt-3 grid gap-2">
                    <div class="rounded-lg border border-sky-100 bg-sky-50 px-3 py-2">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-sky-700">Day</p>
                        <p class="mt-0.5 text-sm font-semibold tabular-nums text-sky-950">{{ $manpower['required_day'] }}</p>
                        <p class="mt-0.5 text-[11px] text-sky-800">{{ $manpower['required_day_armed'] }} armed · {{ $manpower['required_day_unarmed'] }} unarmed</p>
                        <p class="mt-0.5 text-[10px] text-sky-700/80">Deployed {{ $manpower['deployed_day'] }} · Short {{ $manpower['shortage_day'] }}</p>
                    </div>
                    <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-2">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-indigo-700">Night</p>
                        <p class="mt-0.5 text-sm font-semibold tabular-nums text-indigo-950">{{ $manpower['required_night'] }}</p>
                        <p class="mt-0.5 text-[11px] text-indigo-800">{{ $manpower['required_night_armed'] }} armed · {{ $manpower['required_night_unarmed'] }} unarmed</p>
                        <p class="mt-0.5 text-[10px] text-indigo-700/80">Deployed {{ $manpower['deployed_night'] }} · Short {{ $manpower['shortage_night'] }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Total</p>
                        <p class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900">{{ $manpower['required'] }}</p>
                        <p class="mt-0.5 text-[11px] text-slate-600">
                            {{ $manpower['required_day_armed'] + $manpower['required_night_armed'] }} armed
                            · {{ $manpower['required_day_unarmed'] + $manpower['required_night_unarmed'] }} unarmed
                        </p>
                        <div class="mt-1.5">
                            <x-status-badge :tone="$manpower['status']->tone()" :label="$manpower['status']->label()" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

        </div>

        <div class="space-y-3">
            @include('entity.partials.sidebar', ['lifecycle' => $lifecycle ?? null, 'relatedPanels' => $relatedPanels ?? []])
        </div>
    </div>

    <x-entity.activity-timeline :entries="$timeline" />
</div>
@endsection
