@extends('layouts.app')

@section('title', 'Guards')
@section('page-title', 'Guards')
@section('page-subtitle', 'Company-wide guard registry and employment records')

@section('content')
<div class="space-y-6">
    <x-page-header
        title="Guard registry"
        subtitle="Search, filter and manage employment profiles across every region."
    >
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('guards.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">
                    <x-icon name="plus" class="h-4 w-4" />
                    Register guard
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5 lg:gap-3">
        @foreach ([
            ['Total', number_format($stats['total']), 'text-slate-500'],
            ['Active', number_format($stats['active']), 'text-emerald-700'],
            ['On leave', number_format($stats['on_leave']), 'text-sky-700'],
            ['Absent', number_format($stats['absent']), 'text-amber-800'],
            ['Deserted', number_format($stats['deserted']), 'text-rose-700'],
        ] as [$label, $value, $tone])
            <div class="flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                <p class="mt-0.5 text-base font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <form
            method="GET"
            action="{{ route('guards.index') }}"
            x-data
            x-ref="filterForm"
            class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6 xl:items-end"
        >
            <x-form-field
                label="Search"
                name="q"
                type="search"
                :value="$filters['q'] ?? ''"
                placeholder="Employment ID, name, phone, national ID"
                help="Results update as you type"
                class="sm:col-span-2 xl:col-span-2"
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

            <x-form-field label="Employment" name="employment_status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All employment</option>
                @foreach ($employmentStatuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['employment_status'] ?? '') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </x-form-field>

            <x-form-field label="Operational" name="operational_status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All operational</option>
                @foreach ($operationalStatuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['operational_status'] ?? '') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </x-form-field>

            <x-filter-reset :href="route('guards.index')" />
        </form>
    </section>

    @if ($guards->isEmpty())
        <x-empty-state
            title="No guards found"
            description="Register guards with unique employment IDs to build the operational workforce."
            icon="shield"
        >
            @if ($canManage)
                <x-slot:actions>
                    <a href="{{ route('guards.create') }}" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">
                        Register first guard
                    </a>
                </x-slot:actions>
            @endif
        </x-empty-state>
    @else
        {{-- Mobile / tablet cards --}}
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 lg:hidden">
            @foreach ($guards as $guard)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-950 text-xs font-bold text-white">
                            {{ collect(explode(' ', $guard->full_name))->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('guards.show', $guard) }}" class="block truncate font-semibold text-slate-900 hover:text-brand-700">
                                {{ $guard->full_name }}
                            </a>
                            <p class="mt-0.5 text-xs font-medium tracking-wide text-slate-500">{{ $guard->employment_id }}</p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-1.5">
                        <x-status-badge :tone="$guard->employment_status->tone()" :label="$guard->employment_status->label()" />
                        <x-status-badge :tone="$guard->operational_status->tone()" :label="$guard->operational_status->label()" />
                    </div>

                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Region</dt>
                            <dd class="truncate font-medium text-slate-800">{{ $guard->region?->name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Rank</dt>
                            <dd class="truncate font-medium text-slate-800">{{ $guard->rank_designation ?: '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Phone</dt>
                            <dd class="truncate font-medium text-slate-800">{{ $guard->phone ?: '—' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex flex-wrap items-center gap-2.5 sm:gap-3 lg:gap-3.5 border-t border-slate-100 pt-3">
                        <x-action-icon :href="route('guards.show', $guard)" label="View" icon="eye" tone="brand" />
                        @if ($canManage)
                            <x-action-icon :href="route('guards.edit', $guard)" label="Edit" icon="pencil" tone="slate" />
                        @endif
                        @if ($canDelete)
                            <x-delete-button
                                :action="route('guards.destroy', $guard)"
                                confirm="Archive this guard? Status history is preserved."
                                :icon-only="true"
                            />
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        {{-- Desktop table --}}
        <div class="data-table-shell hidden lg:block">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-10">#</th>
                            <th>Guard</th>
                            <th>Region</th>
                            <th>Contact</th>
                            <th>Employment</th>
                            <th>Operational</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($guards as $guard)
                            <tr class="hover:bg-slate-50/80">
                                <td class="text-slate-500">
                                    <x-table-serial :paginator="$guards" :index="$loop->index" />
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-brand-950 text-[10px] font-bold text-white">
                                            {{ collect(explode(' ', $guard->full_name))->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') }}
                                        </div>
                                        <div class="min-w-0">
                                            <a href="{{ route('guards.show', $guard) }}" class="font-semibold text-slate-900 hover:text-brand-700">
                                                {{ $guard->full_name }}
                                            </a>
                                            <p class="text-[10px] text-slate-500">
                                                {{ $guard->employment_id }}
                                                @if ($guard->rank_designation) · {{ $guard->rank_designation }} @endif
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-slate-600">{{ $guard->region?->name ?? '—' }}</td>
                                <td class="text-slate-600">
                                    <p>{{ $guard->phone ?: '—' }}</p>
                                    @if ($guard->national_id)
                                        <p class="text-[10px] text-slate-500">ID {{ $guard->national_id }}</p>
                                    @endif
                                </td>
                                <td>
                                    <x-status-badge :tone="$guard->employment_status->tone()" :label="$guard->employment_status->label()" />
                                </td>
                                <td>
                                    <x-status-badge :tone="$guard->operational_status->tone()" :label="$guard->operational_status->label()" />
                                </td>
                                <td>
                                    <div class="flex flex-wrap items-center justify-end gap-1.5">
                                        <x-action-icon :href="route('guards.show', $guard)" label="View" icon="eye" tone="brand" />
                                        @if ($canManage)
                                            <x-action-icon :href="route('guards.edit', $guard)" label="Edit" icon="pencil" tone="slate" />
                                        @endif
                                        @if ($canDelete)
                                            <x-delete-button
                                                :action="route('guards.destroy', $guard)"
                                                confirm="Archive this guard? Status history is preserved."
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
                Showing {{ $guards->firstItem() ?? 0 }}–{{ $guards->lastItem() ?? 0 }} of {{ $guards->total() }}
            </p>
            <div>{{ $guards->links() }}</div>
        </div>
    @endif
</div>
@endsection
