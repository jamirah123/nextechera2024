@extends('layouts.app')

@section('title', 'Shifts')
@section('page-title', 'Shifts')
@section('page-subtitle', 'Daily schedules, validation and status tracking')

@section('content')
<div class="space-y-6">
    <x-page-header
        title="Today's shifts"
        :subtitle="'Schedule for '. \Illuminate\Support\Carbon::parse($date)->format('d M Y')"
    >
        <x-slot:actions>
            <a href="{{ route('shifts.calendar') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                <x-icon name="calendar" class="h-4 w-4" />
                Calendar
            </a>
            @if ($canManage)
                <a href="{{ route('shifts.recurring.create') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Recurring
                </a>
                <a href="{{ route('shifts.create', ['date' => $date]) }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">
                    <x-icon name="plus" class="h-4 w-4" />
                    Create shift
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-6 gap-2 sm:gap-3">
        @foreach ([
            ['label' => 'Scheduled', 'key' => 'scheduled', 'tone' => 'text-slate-600'],
            ['label' => 'Confirmed', 'key' => 'confirmed', 'tone' => 'text-sky-700'],
            ['label' => 'In progress', 'key' => 'in_progress', 'tone' => 'text-brand-700'],
            ['label' => 'Completed', 'key' => 'completed', 'tone' => 'text-emerald-700'],
            ['label' => 'Missed', 'key' => 'missed', 'tone' => 'text-amber-800'],
            ['label' => 'Cancelled', 'key' => 'cancelled', 'tone' => 'text-rose-700'],
        ] as $card)
            <div class="min-w-0 rounded-2xl border border-slate-200 bg-white p-2.5 shadow-sm sm:p-4">
                <p class="truncate text-[9px] font-semibold uppercase tracking-wide {{ $card['tone'] }} sm:text-[11px]">{{ $card['label'] }}</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 sm:text-2xl">{{ $stats[$card['key']] }}</p>
            </div>
        @endforeach
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <form method="GET" action="{{ route('shifts.index') }}" x-data x-ref="filterForm" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-7 xl:items-end">
            <x-form-field label="Date" name="date" type="date" :value="$filters['date'] ?? $date" x-on:change="$refs.filterForm.requestSubmit()" />
            <x-form-field
                label="Search"
                name="q"
                type="search"
                :value="$filters['q'] ?? ''"
                placeholder="Reference, guard, site"
                class="sm:col-span-2"
                autocomplete="off"
                x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()"
            />
            <x-form-field label="Status" name="status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Period" name="period" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">Day & night</option>
                @foreach ($periods as $period)
                    <option value="{{ $period->value }}" @selected(($filters['period'] ?? '') === $period->value)>{{ $period->label() }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Region" name="region_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All regions</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" @selected((string) ($filters['region_id'] ?? '') === (string) $region->id)>{{ $region->name }}</option>
                @endforeach
            </x-form-field>
            <x-filter-reset :href="route('shifts.index')" />
        </form>
    </section>

    @if ($shifts->isEmpty())
        <x-empty-state title="No shifts for this date" description="Create a shift for a deployed guard to begin the daily schedule." icon="calendar">
            @if ($canManage)
                <x-slot:actions>
                    <a href="{{ route('shifts.create', ['date' => $date]) }}" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">Create shift</a>
                </x-slot:actions>
            @endif
        </x-empty-state>
    @else
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 lg:hidden">
            @foreach ($shifts as $shift)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('shifts.show', $shift) }}" class="font-semibold text-slate-900 hover:text-brand-700">{{ $shift->assignedGuard?->full_name }}</a>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $shift->reference }}</p>
                        </div>
                        <x-status-badge :tone="$shift->status->tone()" :label="$shift->status->label()" />
                    </div>
                    <p class="mt-3 text-sm text-slate-700">{{ $shift->site?->name }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $shift->timeLabel() }} · {{ $shift->period->label() }} · {{ $shift->shift_type->label() }}</p>
                    <div class="mt-4 flex flex-wrap items-center gap-2.5 border-t border-slate-100 pt-3">
                        <x-action-icon :href="route('shifts.show', $shift)" label="View" icon="eye" tone="brand" />
                        @if ($canManage && ! in_array($shift->status->value, ['cancelled', 'completed', 'replaced'], true))
                            <x-action-icon :href="route('shifts.edit', $shift)" label="Edit" icon="pencil" tone="slate" />
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:block">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-14 px-5 py-3">#</th>
                            <th class="px-5 py-3">Guard</th>
                            <th class="px-5 py-3">Site</th>
                            <th class="px-5 py-3">Time</th>
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($shifts as $shift)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3.5"><x-table-serial :paginator="$shifts" :index="$loop->index" /></td>
                                <td class="px-5 py-3.5">
                                    <a href="{{ route('shifts.show', $shift) }}" class="font-semibold text-slate-900 hover:text-brand-700">{{ $shift->assignedGuard?->full_name }}</a>
                                    <p class="text-xs text-slate-500">{{ $shift->assignedGuard?->employment_id }} · {{ $shift->reference }}</p>
                                </td>
                                <td class="px-5 py-3.5 text-slate-700">
                                    <p>{{ $shift->site?->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $shift->region?->name }}</p>
                                </td>
                                <td class="px-5 py-3.5 text-slate-700">
                                    <p>{{ $shift->timeLabel() }}</p>
                                    <p class="text-xs text-slate-500">{{ $shift->period->label() }}@if ($shift->is_overnight) · overnight @endif</p>
                                </td>
                                <td class="px-5 py-3.5"><x-status-badge :tone="$shift->shift_type->tone()" :label="$shift->shift_type->label()" /></td>
                                <td class="px-5 py-3.5"><x-status-badge :tone="$shift->status->tone()" :label="$shift->status->label()" /></td>
                                <td class="px-5 py-3.5">
                                    <div class="flex flex-wrap items-center justify-end gap-2.5">
                                        <x-action-icon :href="route('shifts.show', $shift)" label="View" icon="eye" tone="brand" />
                                        @if ($canManage && ! in_array($shift->status->value, ['cancelled', 'completed', 'replaced'], true))
                                            <x-action-icon :href="route('shifts.edit', $shift)" label="Edit" icon="pencil" tone="slate" />
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
            <p class="text-xs text-slate-500">Showing {{ $shifts->firstItem() ?? 0 }}–{{ $shifts->lastItem() ?? 0 }} of {{ $shifts->total() }}</p>
            <div>{{ $shifts->links() }}</div>
        </div>
    @endif
</div>
@endsection
