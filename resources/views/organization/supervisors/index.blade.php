@extends('layouts.app')

@section('title', 'Supervisors')
@section('page-title', 'Supervisors')
@section('page-subtitle', 'Field supervisors and region assignments')

@section('content')
<div class="space-y-3">
    <x-page-header
        title="Supervisors"
        subtitle="Manage field supervisors assigned to operational regions."
        :back="route('organization.index')"
    >
        <x-slot:actions>
            @if ($canRegister ?? false)
                <a href="{{ route('staff.create', ['employee_type' => 'supervisor']) }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">
                    <x-icon name="plus" class="h-4 w-4" />
                    New supervisor
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <form
            method="GET"
            action="{{ route('supervisors.index') }}"
            x-data x-ref="filterForm"
            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5 lg:items-end"
        >
            <x-form-field
                label="Search"
                name="q"
                type="search"
                :value="$filters['q'] ?? ''"
                placeholder="Name, employment ID, phone"
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

            <x-form-field label="Status" name="status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </x-form-field>

            <x-filter-reset :href="route('supervisors.index')" />
        </form>
    </section>

    @if ($supervisors->isEmpty())
        <x-empty-state
            title="No supervisors found"
            description="Ask HR to register supervisors, then assign them to sites and regions."
            icon="users"
        >
            @if ($canRegister ?? false)
                <x-slot:actions>
                    <a href="{{ route('staff.create', ['employee_type' => 'supervisor']) }}" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">
                        Register supervisor
                    </a>
                </x-slot:actions>
            @endif
        </x-empty-state>
    @else
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 lg:hidden">
            @foreach ($supervisors as $supervisor)
                <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <a href="{{ route('supervisors.show', $supervisor) }}" class="min-w-0">
                            <p class="truncate font-semibold text-slate-900 hover:text-brand-700">{{ $supervisor->name }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $supervisor->guardProfile?->employment_id ?? $supervisor->supervisor_code }}</p>
                        </a>
                        <x-status-badge :tone="$supervisor->status->tone()" :label="$supervisor->status->label()" />
                    </div>
                    <p class="mt-3 text-sm text-slate-600">{{ $supervisor->region?->name ?? 'Unassigned' }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $supervisor->sites_count }} assigned sites</p>
                    <div class="mt-4 flex flex-wrap items-center gap-2.5 sm:gap-3 lg:gap-3.5 border-t border-slate-100 pt-3">
                        <x-action-icon :href="route('supervisors.show', $supervisor)" label="View" icon="eye" tone="brand" />
                        @if ($canManage)
                            <x-action-icon :href="route('supervisors.edit', $supervisor)" label="Edit" icon="pencil" tone="slate" />
                        @endif
                        @if ($canDelete)
                            <x-delete-button
                                :action="route('supervisors.destroy', $supervisor)"
                                confirm="Delete this supervisor? Allowed only when no sites are assigned."
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
                            <th>Supervisor</th>
                            <th>Region</th>
                            <th>Contact</th>
                            <th class="text-right">Sites</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($supervisors as $supervisor)
                            <tr class="hover:bg-slate-50/80">
                                <td class="text-slate-500">
                                    <x-table-serial :paginator="$supervisors" :index="$loop->index" />
                                </td>
                                <td>
                                    <a href="{{ route('supervisors.show', $supervisor) }}" class="font-semibold text-slate-900 hover:text-brand-700">
                                        {{ $supervisor->name }}
                                    </a>
                                    <p class="text-[10px] text-slate-500">{{ $supervisor->guardProfile?->employment_id ?? $supervisor->supervisor_code }}</p>
                                </td>
                                <td class="text-slate-600">{{ $supervisor->region?->name ?? '—' }}</td>
                                <td class="text-slate-600">
                                    <p>{{ $supervisor->phone ?: '—' }}</p>
                                    @if ($supervisor->email)
                                        <p class="text-[10px] text-slate-500">{{ $supervisor->email }}</p>
                                    @endif
                                </td>
                                <td class="text-right text-slate-700">{{ $supervisor->sites_count }}</td>
                                <td>
                                    <x-status-badge :tone="$supervisor->status->tone()" :label="$supervisor->status->label()" />
                                </td>
                                <td>
                                    <div class="flex flex-wrap items-center justify-end gap-1.5">
                                        <x-action-icon :href="route('supervisors.show', $supervisor)" label="View" icon="eye" tone="brand" />
                                        @if ($canManage)
                                            <x-action-icon :href="route('supervisors.edit', $supervisor)" label="Edit" icon="pencil" tone="slate" />
                                        @endif
                                        @if ($canDelete)
                                            <x-delete-button
                                                :action="route('supervisors.destroy', $supervisor)"
                                                confirm="Delete this supervisor? Allowed only when no sites are assigned."
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

        <x-table-pagination :paginator="$supervisors" />
    @endif
</div>
@endsection
