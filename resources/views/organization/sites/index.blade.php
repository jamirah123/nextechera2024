@extends('layouts.app')

@section('title', 'Sites')
@section('page-title', 'Sites')
@section('page-subtitle', 'Security sites and manpower coverage')

@section('content')
<div class="space-y-3">
    <x-page-header
        title="Security sites"
        subtitle="Manage sites, assignments and staffing requirements."
        :back="route('organization.index')"
    >
        <x-slot:actions>
            <a href="{{ route('manpower.coverage') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                Coverage
            </a>
            @if ($canManage)
                <a href="{{ route('sites.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">
                    <x-icon name="plus" class="h-4 w-4" />
                    New site
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <form
            method="GET"
            action="{{ route('sites.index') }}"
            x-data x-ref="filterForm"
            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6 lg:items-end"
        >
            <x-form-field
                label="Search"
                name="q"
                type="search"
                :value="$filters['q'] ?? ''"
                placeholder="Name, code or location"
                class="lg:col-span-2"
                autocomplete="off"
                x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()"
            />

            <x-form-field label="Region" name="region_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All regions</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" @selected((string) ($filters['region_id'] ?? '') === (string) $region->id)>
                        {{ $region->name }}
                    </option>
                @endforeach
            </x-form-field>

            <x-form-field label="Client" name="client_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All clients</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" @selected((string) ($filters['client_id'] ?? '') === (string) $client->id)>
                        {{ $client->name }}
                    </option>
                @endforeach
            </x-form-field>

            <x-form-field label="Status" name="status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </x-form-field>

            <x-filter-reset :href="route('sites.index')" />
        </form>
    </section>

    @if ($sites->isEmpty())
        <x-empty-state
            title="No sites found"
            description="Create a security site with manpower requirements to get started."
            icon="shield"
        >
            @if ($canManage)
                <x-slot:actions>
                    <a href="{{ route('sites.create') }}" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">
                        Create site
                    </a>
                </x-slot:actions>
            @endif
        </x-empty-state>
    @else
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 lg:hidden">
            @foreach ($sites as $site)
                @php $mp = $site->manpower; @endphp
                <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <a href="{{ route('sites.show', $site) }}" class="min-w-0">
                            <p class="truncate font-semibold text-slate-900 hover:text-brand-700">{{ $site->name }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $site->code }}</p>
                        </a>
                        <x-status-badge :tone="$site->status->tone()" :label="$site->status->label()" />
                    </div>
                    <p class="mt-3 text-sm text-slate-600">{{ $site->client?->name ?? 'No client' }} · {{ $site->region?->name ?? 'No region' }}</p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <x-status-badge :tone="$mp['status']->tone()" :label="$mp['status']->label()" />
                        <span class="text-xs text-slate-500">{{ $mp['coverage_percent'] }}% · short {{ $mp['shortage'] }}</span>
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-2.5 sm:gap-3 lg:gap-3.5 border-t border-slate-100 pt-3">
                        <x-action-icon :href="route('sites.show', $site)" label="View" icon="eye" tone="brand" />
                        @if ($canManage)
                            <x-action-icon :href="route('sites.edit', $site)" label="Edit" icon="pencil" tone="slate" />
                        @endif
                        @if ($canDelete)
                            <x-delete-button
                                :action="route('sites.destroy', $site)"
                                confirm="Delete this site? It will be archived from active operations."
                                :icon-only="true"
                            />
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="data-table-shell hidden lg:block">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-10">#</th>
                            <th>Site</th>
                            <th>Client / Region</th>
                            <th>Supervisor</th>
                            <th class="text-right">Required</th>
                            <th>Coverage</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sites as $site)
                            @php $mp = $site->manpower; @endphp
                            <tr class="hover:bg-slate-50/80">
                                <td class="text-slate-500">
                                    <x-table-serial :paginator="$sites" :index="$loop->index" />
                                </td>
                                <td>
                                    <a href="{{ route('sites.show', $site) }}" class="font-semibold text-slate-900 hover:text-brand-700">
                                        {{ $site->name }}
                                    </a>
                                    <p class="text-[10px] text-slate-500">{{ $site->code }}</p>
                                </td>
                                <td class="text-slate-600">
                                    <p>{{ $site->client?->name ?? '—' }}</p>
                                    <p class="text-[10px] text-slate-500">{{ $site->region?->name ?? '—' }}</p>
                                </td>
                                <td class="text-slate-600">{{ $site->supervisor?->name ?? '—' }}</td>
                                <td class="text-right font-medium text-slate-900">{{ $mp['required'] }}</td>
                                <td>
                                    <div class="flex flex-col gap-0.5">
                                        <x-status-badge :tone="$mp['status']->tone()" :label="$mp['status']->label()" />
                                        <span class="text-[10px] text-slate-500">{{ $mp['coverage_percent'] }}%</span>
                                    </div>
                                </td>
                                <td>
                                    <x-status-badge :tone="$site->status->tone()" :label="$site->status->label()" />
                                </td>
                                <td>
                                    <div class="flex flex-wrap items-center justify-end gap-1.5">
                                        <x-action-icon :href="route('sites.show', $site)" label="View" icon="eye" tone="brand" />
                                        @if ($canManage)
                                            <x-action-icon :href="route('sites.edit', $site)" label="Edit" icon="pencil" tone="slate" />
                                        @endif
                                        @if ($canDelete)
                                            <x-delete-button
                                                :action="route('sites.destroy', $site)"
                                                confirm="Delete this site? It will be archived from active operations."
                                                :icon-only="true"
                                            />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-slate-500">
                Showing {{ $sites->firstItem() ?? 0 }}–{{ $sites->lastItem() ?? 0 }} of {{ $sites->total() }}
            </p>
            <div>{{ $sites->links() }}</div>
        </div>
    @endif
</div>
@endsection
