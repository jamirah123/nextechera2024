@extends('layouts.app')

@section('title', 'Regions')
@section('page-title', 'Regions')
@section('page-subtitle', 'Operational regions and area managers')

@section('content')
<div class="space-y-3">
    <x-page-header
        title="Regions"
        subtitle="Organize supervisors and sites by geographic area."
        :back="route('organization.index')"
    >
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('regions.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">
                    <x-icon name="plus" class="h-4 w-4" />
                    New region
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <form
            method="GET"
            action="{{ route('regions.index') }}"
            x-data x-ref="filterForm"
            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end"
        >
            <x-form-field
                label="Search"
                name="q"
                type="search"
                :value="$filters['q'] ?? ''"
                placeholder="Name, code or manager"
                help="Results update as you type"
                class="lg:col-span-2"
                autocomplete="off"
                x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()"
            />

            <x-form-field
                label="Status"
                name="status"
                type="select"
                x-on:change="$refs.filterForm.requestSubmit()"
            >
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </x-form-field>

            <x-filter-reset :href="route('regions.index')" />
        </form>
    </section>

    @if ($regions->isEmpty())
        <x-empty-state
            title="No regions found"
            description="Create a region to assign supervisors and security sites."
            icon="map"
        >
            @if ($canManage)
                <x-slot:actions>
                    <a href="{{ route('regions.create') }}" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">
                        Create region
                    </a>
                </x-slot:actions>
            @endif
        </x-empty-state>
    @else
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 lg:hidden">
            @foreach ($regions as $region)
                <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <a href="{{ route('regions.show', $region) }}" class="min-w-0">
                            <p class="truncate font-semibold text-slate-900 hover:text-brand-700">{{ $region->name }}</p>
                            <p class="mt-0.5 text-xs font-medium text-slate-500">{{ $region->code }}</p>
                        </a>
                        <x-status-badge :tone="$region->status->tone()" :label="$region->status->label()" />
                    </div>
                    <p class="mt-3 text-sm text-slate-600">{{ $region->manager_name ?: 'No manager assigned' }}</p>
                    <div class="mt-4 flex gap-4 text-xs text-slate-500">
                        <span>{{ $region->supervisors_count }} supervisors</span>
                        <span>{{ $region->sites_count }} sites</span>
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-2.5 sm:gap-3 lg:gap-3.5 border-t border-slate-100 pt-3">
                        <x-action-icon :href="route('regions.show', $region)" label="View" icon="eye" tone="brand" />
                        @if ($canManage)
                            <x-action-icon :href="route('regions.edit', $region)" label="Edit" icon="pencil" tone="slate" />
                        @endif
                        @if ($canDelete)
                            <x-delete-button
                                :action="route('regions.destroy', $region)"
                                confirm="Delete this region? It will be archived if it has no linked supervisors or sites."
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
                            <th>Region</th>
                            <th>Manager</th>
                            <th class="text-right">Supervisors</th>
                            <th class="text-right">Sites</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($regions as $region)
                            <tr class="hover:bg-slate-50/80">
                                <td class="text-slate-500">
                                    <x-table-serial :paginator="$regions" :index="$loop->index" />
                                </td>
                                <td>
                                    <a href="{{ route('regions.show', $region) }}" class="font-semibold text-slate-900 hover:text-brand-700">
                                        {{ $region->name }}
                                    </a>
                                    <p class="text-[10px] text-slate-500">{{ $region->code }}</p>
                                </td>
                                <td class="text-slate-600">
                                    <p>{{ $region->manager_name ?: '—' }}</p>
                                    @if ($region->manager_phone)
                                        <p class="text-[10px] text-slate-500">{{ $region->manager_phone }}</p>
                                    @endif
                                </td>
                                <td class="text-right text-slate-700">{{ $region->supervisors_count }}</td>
                                <td class="text-right text-slate-700">{{ $region->sites_count }}</td>
                                <td>
                                    <x-status-badge :tone="$region->status->tone()" :label="$region->status->label()" />
                                </td>
                                <td>
                                    <div class="flex flex-wrap items-center justify-end gap-1.5">
                                        <x-action-icon :href="route('regions.show', $region)" label="View" icon="eye" tone="brand" />
                                        @if ($canManage)
                                            <x-action-icon :href="route('regions.edit', $region)" label="Edit" icon="pencil" tone="slate" />
                                        @endif
                                        @if ($canDelete)
                                            <x-delete-button
                                                :action="route('regions.destroy', $region)"
                                                confirm="Delete this region? It will be archived if it has no linked supervisors or sites."
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
                Showing {{ $regions->firstItem() ?? 0 }}–{{ $regions->lastItem() ?? 0 }} of {{ $regions->total() }}
            </p>
            <div>{{ $regions->links() }}</div>
        </div>
    @endif
</div>
@endsection
