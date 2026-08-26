@extends('layouts.app')

@section('title', $site->name)
@section('page-title', 'Site details')
@section('page-subtitle', $site->code)

@section('content')
<div class="space-y-6">
    <x-page-header
        :title="$site->name"
        :subtitle="$site->physical_location ?: 'Security site details and manpower.'"
        :back="route('sites.index')"
    >
        <x-slot:actions>
            @if ($canDeploy ?? false)
                <a href="{{ route('deployments.create', ['site_id' => $site->id]) }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">
                    Deploy guard
                </a>
            @endif
            <a href="{{ route('deployments.index', ['site_id' => $site->id, 'current_only' => 1]) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                View deployments
            </a>
            @if ($canManage)
                <a href="{{ route('sites.edit', $site) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
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

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 sm:gap-4">
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
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-4 sm:px-6">
                <h2 class="text-base font-semibold text-slate-900">Site details</h2>
                <x-status-badge :tone="$site->status->tone()" :label="$site->status->label()" />
            </div>
            <dl class="grid gap-0 sm:grid-cols-2">
                <div class="border-b border-slate-100 px-5 py-4 sm:border-r sm:px-6">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Client</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">
                        @if ($site->client)
                            <a href="{{ route('clients.show', $site->client) }}" class="text-brand-700 hover:text-brand-800">{{ $site->client->name }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Region</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">
                        @if ($site->region)
                            <a href="{{ route('regions.show', $site->region) }}" class="text-brand-700 hover:text-brand-800">{{ $site->region->name }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="border-b border-slate-100 px-5 py-4 sm:border-r sm:px-6">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Supervisor</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">
                        @if ($site->supervisor)
                            <a href="{{ route('supervisors.show', $site->supervisor) }}" class="text-brand-700 hover:text-brand-800">{{ $site->supervisor->name }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Code</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $site->code }}</dd>
                </div>
                <div class="border-b border-slate-100 px-5 py-4 sm:border-r sm:px-6">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Contact</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $site->site_contact_person ?: '—' }}</dd>
                    @if ($site->site_contact_phone)
                        <dd class="text-xs text-slate-500">{{ $site->site_contact_phone }}</dd>
                    @endif
                </div>
                <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Posts</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $site->number_of_posts }}</dd>
                </div>
                <div class="border-b border-slate-100 px-5 py-4 sm:border-r sm:px-6">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Contract</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">
                        {{ optional($site->contract_start_date)->format('d M Y') ?: '—' }}
                        @if ($site->contract_end_date)
                            – {{ optional($site->contract_end_date)->format('d M Y') }}
                        @endif
                    </dd>
                </div>
                <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Coordinates</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">
                        @if ($site->latitude && $site->longitude)
                            {{ $site->latitude }}, {{ $site->longitude }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="px-5 py-4 sm:col-span-2 sm:px-6">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Location / notes</dt>
                    <dd class="mt-1 text-sm text-slate-700">{{ $site->physical_location ?: '—' }}</dd>
                    @if ($site->notes)
                        <dd class="mt-2 text-sm text-slate-600">{{ $site->notes }}</dd>
                    @endif
                </div>
            </dl>
        </div>

        <div class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Shift manpower</h2>
                <p class="mt-1 text-sm text-slate-500">Day vs night requirements.</p>
                <div class="mt-4 grid gap-3">
                    <div class="rounded-xl border border-sky-100 bg-sky-50 px-4 py-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-sky-700">Day</p>
                        <p class="mt-1 text-2xl font-semibold text-sky-950">{{ $manpower['required_day'] }}</p>
                        <p class="mt-0.5 text-xs text-sky-700/80">Deployed {{ $manpower['deployed_day'] }} · Short {{ $manpower['shortage_day'] }}</p>
                    </div>
                    <div class="rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-indigo-700">Night</p>
                        <p class="mt-1 text-2xl font-semibold text-indigo-950">{{ $manpower['required_night'] }}</p>
                        <p class="mt-0.5 text-xs text-indigo-700/80">Deployed {{ $manpower['deployed_night'] }} · Short {{ $manpower['shortage_night'] }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $manpower['required'] }}</p>
                        <div class="mt-2">
                            <x-status-badge :tone="$manpower['status']->tone()" :label="$manpower['status']->label()" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
            <h2 class="text-base font-semibold text-slate-900">Requirement history</h2>
            <p class="mt-0.5 text-sm text-slate-500">Previous manpower requirement snapshots.</p>
        </div>

        @if ($site->manpowerRequirements->isEmpty())
            <div class="p-5 sm:p-6">
                <x-empty-state
                    title="No requirement history"
                    description="Manpower changes will appear here after updates."
                    icon="chart"
                />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-14 px-5 py-3">#</th>
                            <th class="px-5 py-3">Effective</th>
                            <th class="px-5 py-3 text-right">Day</th>
                            <th class="px-5 py-3 text-right">Night</th>
                            <th class="px-5 py-3 text-right">Total</th>
                            <th class="px-5 py-3">Notes</th>
                            <th class="px-5 py-3">Current</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($site->manpowerRequirements as $requirement)
                            <tr>
                                <td class="px-5 py-3.5">
                                    <x-table-serial :iteration="$loop->iteration" />
                                </td>
                                <td class="px-5 py-3.5 text-slate-700">
                                    {{ optional($requirement->effective_from)->format('d M Y') ?: '—' }}
                                    @if ($requirement->effective_to)
                                        <span class="text-slate-400">– {{ optional($requirement->effective_to)->format('d M Y') }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right text-slate-700">{{ $requirement->required_day }}</td>
                                <td class="px-5 py-3.5 text-right text-slate-700">{{ $requirement->required_night }}</td>
                                <td class="px-5 py-3.5 text-right font-semibold text-slate-900">{{ $requirement->required_total }}</td>
                                <td class="px-5 py-3.5 text-slate-600">{{ $requirement->notes ?: '—' }}</td>
                                <td class="px-5 py-3.5">
                                    @if ($requirement->is_current)
                                        <x-status-badge tone="emerald" label="Current" />
                                    @else
                                        <x-status-badge tone="slate" label="Past" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
