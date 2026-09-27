@extends('layouts.app')

@section('title', 'Clients')
@section('page-title', 'Clients')
@section('page-subtitle', 'Client contracts and security accounts')

@section('content')
<div class="space-y-3">
    <x-page-header
        title="Clients"
        subtitle="Manage contracted clients and linked security sites."
        :back="route('organization.index')"
    >
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('clients.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">
                    <x-icon name="plus" class="h-4 w-4" />
                    New client
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="filter-bar rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <form
            method="GET"
            action="{{ route('clients.index') }}"
            x-data x-ref="filterForm"
            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end"
        >
            <x-form-field
                label="Search"
                name="q"
                type="search"
                :value="$filters['q'] ?? ''"
                placeholder="Name or contact"
                class="lg:col-span-2"
                autocomplete="off"
                x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()"
            />

            <x-form-field
                label="Contract status"
                name="contract_status"
                type="select"
                x-on:change="$refs.filterForm.requestSubmit()"
            >
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['contract_status'] ?? '') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </x-form-field>

            <x-filter-reset :href="route('clients.index')" />
        </form>
    </section>

    @if ($clients->isEmpty())
        <x-empty-state
            title="No clients found"
            description="Register a client to attach security sites and contracts."
            icon="building"
        >
            @if ($canManage)
                <x-slot:actions>
                    <a href="{{ route('clients.create') }}" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">
                        Create client
                    </a>
                </x-slot:actions>
            @endif
        </x-empty-state>
    @else
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 lg:hidden">
            @foreach ($clients as $client)
                <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <a href="{{ route('clients.show', $client) }}" class="min-w-0">
                            <p class="truncate font-semibold text-slate-900 hover:text-brand-700">{{ $client->name }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $client->contact_person ?: 'No contact person' }}</p>
                        </a>
                        <x-status-badge :tone="$client->contract_status->tone()" :label="$client->contract_status->label()" />
                    </div>
                    <p class="mt-3 text-sm text-slate-600">{{ $client->phone ?: 'No phone' }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $client->sites_count }} sites</p>
                    <div class="mt-4 flex flex-wrap items-center gap-2.5 sm:gap-3 lg:gap-3.5 border-t border-slate-100 pt-3">
                        <x-action-icon :href="route('clients.show', $client)" label="View" icon="eye" tone="brand" />
                        @if ($canManage)
                            <x-action-icon :href="route('clients.edit', $client)" label="Edit" icon="pencil" tone="slate" />
                        @endif
                        @if ($canDelete)
                            <x-delete-button
                                :action="route('clients.destroy', $client)"
                                confirm="Delete this client? Allowed only when no sites are linked."
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
                            <th>Client</th>
                            <th>Contact</th>
                            <th>Contract</th>
                            <th class="text-right">Sites</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clients as $client)
                            <tr class="hover:bg-slate-50/80">
                                <td class="text-slate-500">
                                    <x-table-serial :paginator="$clients" :index="$loop->index" />
                                </td>
                                <td>
                                    <a href="{{ route('clients.show', $client) }}" class="font-semibold text-slate-900 hover:text-brand-700">
                                        {{ $client->name }}
                                    </a>
                                </td>
                                <td class="text-slate-600">
                                    <p>{{ $client->contact_person ?: '—' }}</p>
                                    <p class="text-[10px] text-slate-500">{{ $client->phone ?: '' }}</p>
                                </td>
                                <td class="text-slate-600">
                                    {{ optional($client->contract_start_date)->format('d M Y') ?: '—' }}
                                    @if ($client->contract_end_date)
                                        – {{ optional($client->contract_end_date)->format('d M Y') }}
                                    @endif
                                </td>
                                <td class="text-right text-slate-700">{{ $client->sites_count }}</td>
                                <td>
                                    <x-status-badge :tone="$client->contract_status->tone()" :label="$client->contract_status->label()" />
                                </td>
                                <td>
                                    <div class="flex flex-wrap items-center justify-end gap-1.5">
                                        <x-action-icon :href="route('clients.show', $client)" label="View" icon="eye" tone="brand" />
                                        @if ($canManage)
                                            <x-action-icon :href="route('clients.edit', $client)" label="Edit" icon="pencil" tone="slate" />
                                        @endif
                                        @if ($canDelete)
                                            <x-delete-button
                                                :action="route('clients.destroy', $client)"
                                                confirm="Delete this client? Allowed only when no sites are linked."
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
                Showing {{ $clients->firstItem() ?? 0 }}–{{ $clients->lastItem() ?? 0 }} of {{ $clients->total() }}
            </p>
            <div>{{ $clients->links() }}</div>
        </div>
    @endif
</div>
@endsection
