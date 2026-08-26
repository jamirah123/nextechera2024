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

    <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Total</p>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{{ $stats['total'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Active</p>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{{ $stats['active'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-sky-700">On leave</p>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{{ $stats['on_leave'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-800">Absent</p>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{{ $stats['absent'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-rose-700">Deserted</p>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{{ $stats['deserted'] }}</p>
        </div>
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
        <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:block">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-14 px-5 py-3">#</th>
                            <th class="px-5 py-3">Guard</th>
                            <th class="px-5 py-3">Region</th>
                            <th class="px-5 py-3">Contact</th>
                            <th class="px-5 py-3">Employment</th>
                            <th class="px-5 py-3">Operational</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($guards as $guard)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3.5">
                                    <x-table-serial :paginator="$guards" :index="$loop->index" />
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-950 text-[11px] font-bold text-white">
                                            {{ collect(explode(' ', $guard->full_name))->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') }}
                                        </div>
                                        <div class="min-w-0">
                                            <a href="{{ route('guards.show', $guard) }}" class="font-semibold text-slate-900 hover:text-brand-700">
                                                {{ $guard->full_name }}
                                            </a>
                                            <p class="text-xs text-slate-500">
                                                {{ $guard->employment_id }}
                                                @if ($guard->rank_designation) · {{ $guard->rank_designation }} @endif
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 text-slate-600">{{ $guard->region?->name ?? '—' }}</td>
                                <td class="px-5 py-3.5 text-slate-600">
                                    <p>{{ $guard->phone ?: '—' }}</p>
                                    @if ($guard->national_id)
                                        <p class="text-xs text-slate-500">ID {{ $guard->national_id }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5">
                                    <x-status-badge :tone="$guard->employment_status->tone()" :label="$guard->employment_status->label()" />
                                </td>
                                <td class="px-5 py-3.5">
                                    <x-status-badge :tone="$guard->operational_status->tone()" :label="$guard->operational_status->label()" />
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex flex-wrap items-center justify-end gap-2.5 sm:gap-3 lg:gap-3.5">
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
