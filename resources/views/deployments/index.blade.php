@extends('layouts.app')

@section('title', 'Deployments')
@section('page-title', 'Deployments')
@section('page-subtitle', 'Guard-to-site assignments and transfers')

@section('content')
<div class="space-y-3">
    <x-page-header
        title="Deployments"
        subtitle="Assign guards to sites, transfer them with full history, and track coverage."
    >
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('deployments.board') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">
                    <x-icon name="plus" class="h-3.5 w-3.5" />
                    Deploy board
                </a>
                <a href="{{ route('deployments.create') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Single form
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-4 gap-2 sm:gap-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700 sm:text-[11px]">Active</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-lg">{{ $stats['active'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-800 sm:text-[11px]">Day</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-lg">{{ $stats['day'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-indigo-700 sm:text-[11px]">Night</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-lg">{{ $stats['night'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-sky-700 sm:text-[11px]">Transfers</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-lg">{{ $stats['transferred'] }}</p>
        </div>
    </section>

    <section class="filter-bar rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <form method="GET" action="{{ route('deployments.index') }}" x-data x-ref="filterForm" class="grid gap-2 lg:grid-cols-6 lg:items-end">
            <x-form-field
                label="Search"
                name="q"
                type="search"
                :value="$filters['q'] ?? ''"
                placeholder="Employment ID, guard, site"
                class="lg:col-span-2"
                autocomplete="off"
                x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()"
            />
            <x-form-field label="Region" name="region_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All regions</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" @selected((string) ($filters['region_id'] ?? '') === (string) $region->id)>{{ $region->name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Status" name="status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">Active only</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Shift" name="shift_type" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All shifts</option>
                @foreach ($shiftTypes as $type)
                    <option value="{{ $type->value }}" @selected(($filters['shift_type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-form-field>
            <x-filter-reset :href="route('deployments.index')" />
        </form>
    </section>

    @if ($deployments->isEmpty())
        <x-empty-state title="No deployments found" description="Deploy an available guard to a site to begin coverage tracking." icon="map">
            @if ($canManage)
                <x-slot:actions>
                    <a href="{{ route('deployments.create') }}" class="btn btn-primary">Deploy guard</a>
                </x-slot:actions>
            @endif
        </x-empty-state>
    @else
        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3 lg:hidden">
            @foreach ($deployments as $deployment)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('deployments.show', $deployment) }}" class="font-semibold text-slate-900 hover:text-brand-700">{{ $deployment->assignedGuard?->full_name }}</a>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $deployment->assignedGuard?->employment_id }}</p>
                        </div>
                        <x-status-badge :tone="$deployment->status->tone()" :label="$deployment->status->label()" />
                    </div>
                    <p class="mt-3 text-sm text-slate-700">{{ $deployment->site?->name }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $deployment->region?->name }} · {{ $deployment->shift_type->label() }}</p>
                    <div class="mt-4 flex flex-wrap items-center gap-2.5 sm:gap-3 lg:gap-3.5 border-t border-slate-100 pt-3">
                        <x-action-icon :href="route('deployments.show', $deployment)" label="View" icon="eye" tone="brand" />
                        @if ($canManage && $deployment->isActive())
                            <x-action-icon :href="route('deployments.transfer', $deployment)" label="Transfer" icon="swap" tone="slate" />
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        <div class="hidden overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm lg:block">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-14 px-3 py-2">#</th>
                            <th class="px-3 py-2">Guard</th>
                            <th class="px-3 py-2">Site</th>
                            <th class="px-3 py-2">Region</th>
                            <th class="px-3 py-2">Shift</th>
                            <th class="px-3 py-2">Started</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($deployments as $deployment)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-3 py-2"><x-table-serial :paginator="$deployments" :index="$loop->index" /></td>
                                <td class="px-3 py-2">
                                    <a href="{{ route('deployments.show', $deployment) }}" class="font-semibold text-slate-900 hover:text-brand-700">{{ $deployment->assignedGuard?->full_name }}</a>
                                    <p class="text-xs text-slate-500">{{ $deployment->assignedGuard?->employment_id }}</p>
                                </td>
                                <td class="px-3 py-2 text-slate-700">
                                    <p>{{ $deployment->site?->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $deployment->site?->code }}</p>
                                </td>
                                <td class="px-3 py-2 text-slate-600">{{ $deployment->region?->name ?? '—' }}</td>
                                <td class="px-3 py-2"><x-status-badge :tone="$deployment->shift_type->tone()" :label="$deployment->shift_type->label()" /></td>
                                <td class="px-3 py-2 text-slate-600">{{ optional($deployment->start_date)->format('d M Y') }}</td>
                                <td class="px-3 py-2"><x-status-badge :tone="$deployment->status->tone()" :label="$deployment->status->label()" /></td>
                                <td class="px-3 py-2">
                                    <div class="flex flex-wrap items-center justify-end gap-2.5 sm:gap-3 lg:gap-3.5">
                                        <x-action-icon :href="route('deployments.show', $deployment)" label="View" icon="eye" tone="brand" />
                                        @if ($canManage && $deployment->isActive())
                                            <x-action-icon :href="route('deployments.transfer', $deployment)" label="Transfer" icon="swap" tone="slate" />
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
            <p class="text-xs text-slate-500">Showing {{ $deployments->firstItem() ?? 0 }}–{{ $deployments->lastItem() ?? 0 }} of {{ $deployments->total() }}</p>
            <div>{{ $deployments->links() }}</div>
        </div>
    @endif
</div>
@endsection
