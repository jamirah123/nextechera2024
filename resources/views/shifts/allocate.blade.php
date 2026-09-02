@extends('layouts.app')

@section('title', 'Allocate shifts')
@section('page-title', 'Allocate shifts')
@section('page-subtitle', 'Only deployed guards — schedule day or night shifts by date')

@section('content')
@php
    $defaultPeriod = old('default_period', \App\Enums\ShiftPeriod::Day->value);
    $defaultType = old('default_type', \App\Enums\ShiftType::Normal->value);
    $dayStart = config('psg.shift_defaults.day.start', '06:00');
    $dayEnd = config('psg.shift_defaults.day.end', '18:00');
    $nightStart = config('psg.shift_defaults.night.start', '18:00');
    $nightEnd = config('psg.shift_defaults.night.end', '06:00');
@endphp
<div
    class="space-y-3"
    x-data="{
        selectAll: false,
        defaults: {
            period: @js($defaultPeriod),
            shift_type: @js($defaultType),
        },
        toggleAll(checked) {
            this.selectAll = checked;
            this.$root.querySelectorAll('[data-row-check]').forEach((el) => { el.checked = checked; });
        },
        applyDefaults() {
            this.$root.querySelectorAll('[data-row-period]').forEach((el) => { el.value = this.defaults.period; });
            this.$root.querySelectorAll('[data-row-type]').forEach((el) => { el.value = this.defaults.shift_type; });
        },
        selectedCount() {
            return this.$root.querySelectorAll('[data-row-check]:checked').length;
        }
    }"
>
    <x-page-header
        title="Shift allocation board"
        :subtitle="'Deployed guards still needing a shift for '. \Illuminate\Support\Carbon::parse($date)->format('d M Y')"
        :back="route('shifts.index', ['date' => $date])"
    >
        <x-slot:actions>
            <a href="{{ route('shifts.index', ['date' => $date]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Today's list</a>
            <a href="{{ route('shifts.create', ['date' => $date]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Single form</a>
        </x-slot:actions>
    </x-page-header>

    <section class="rounded-2xl border border-slate-200 bg-gradient-to-br from-white via-white to-brand-50/40 p-5 shadow-sm sm:p-6">
        <h2 class="text-sm font-semibold text-slate-900">How to give shifts (quick guide)</h2>
        <ol class="mt-3 grid gap-3 text-sm leading-relaxed text-slate-600 sm:grid-cols-2 lg:grid-cols-4">
            <li class="rounded-xl border border-slate-100 bg-white/80 p-3">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-brand-700">1. Pick the date</span>
                Choose the duty date above the table. Only <strong class="font-semibold text-slate-800">deployed guards without a shift</strong> for that date appear here (completed shifts are excluded).
            </li>
            <li class="rounded-xl border border-slate-100 bg-white/80 p-3">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-brand-700">2. Narrow the list</span>
                Filter by region or site. Use <strong class="font-semibold text-slate-800">Show all deployed</strong> if you need to review guards who already have a shift that day.
            </li>
            <li class="rounded-xl border border-slate-100 bg-white/80 p-3">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-brand-700">3. Set Day / Night</span>
                Use the row dropdowns, or set defaults and click <strong class="font-semibold text-slate-800">Apply to this page</strong>. Tick the guards you want.
            </li>
            <li class="rounded-xl border border-slate-100 bg-white/80 p-3">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-brand-700">4. Allocate</span>
                Click <strong class="font-semibold text-slate-800">Allocate selected</strong>. The system creates scheduled shifts at the guard’s current site.
            </li>
        </ol>
        <p class="mt-3 text-xs text-slate-500">
            Day = {{ $dayStart }}–{{ $dayEnd }} · Night = {{ $nightStart }}–{{ $nightEnd }} (overnight).
            If a row fails (overlap, leave, etc.), others still save and you will see a short skip list.
        </p>
    </section>

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3">
        @foreach ([
            ['Active', number_format($stats['deployed']), 'text-emerald-700'],
            ['Need allocation', number_format($stats['needs_allocation']), 'text-amber-700'],
            ['On this date', number_format($stats['scheduled_today']), 'text-brand-700'],
        ] as [$label, $value, $tone])
            <div class="flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                <p class="mt-0.5 text-base font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <section class="filter-bar rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <form method="GET" action="{{ route('shifts.allocate') }}" x-data x-ref="filterForm" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-6 xl:items-end">
            <x-form-field label="Duty date" name="date" type="date" :value="$filters['date'] ?? $date" x-on:change="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Search guard" name="q" type="search" :value="$filters['q'] ?? ''" placeholder="Name or ID" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Region" name="region_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All regions</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" @selected((string) ($filters['region_id'] ?? '') === (string) $region->id)>{{ $region->name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Site" name="site_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All sites</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}" @selected((string) ($filters['site_id'] ?? '') === (string) $site->id)>{{ $site->name }}</option>
                @endforeach
            </x-form-field>
            <label class="flex items-center gap-2.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs text-slate-700">
                <input type="checkbox" name="show_all" value="1" @checked($showAll ?? false) x-on:change="$refs.filterForm.requestSubmit()" class="rounded border-slate-300 text-brand-700 focus:ring-brand-500/30">
                <span>Show all deployed</span>
            </label>
            <a href="{{ route('shifts.allocate', ['date' => $date]) }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset filters</a>
        </form>
    </section>

    @if (session('allocation_errors'))
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-semibold">Some rows were skipped</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-4">
                @foreach (session('allocation_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($deployments->isEmpty())
        <x-empty-state
            title="{{ ($showAll ?? false) ? 'No deployed guards match' : 'All deployed guards are scheduled' }}"
            :description="($showAll ?? false)
                ? 'Deploy guards first (Deploy Board), or clear filters. Only active deployments appear here.'
                : 'Every deployed guard already has a scheduled, in-progress, or completed shift for this date. Toggle Show all deployed to review them.'"
            icon="calendar"
        />
    @else
        <form method="POST" action="{{ route('shifts.allocate.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="shift_date" value="{{ $date }}">

            <div class="sticky top-0 z-10 rounded-2xl border border-brand-200 bg-white/95 p-4 shadow-md backdrop-blur sm:p-5">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                    <div class="grid flex-1 gap-3 sm:grid-cols-2">
                        <label class="block text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            Default period
                            <x-board-select x-model="defaults.period" class="mt-1.5">
                                @foreach ($periods as $period)
                                    <option value="{{ $period->value }}">{{ $period->label() }}</option>
                                @endforeach
                            </x-board-select>
                        </label>
                        <label class="block text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            Default duty type
                            <x-board-select x-model="defaults.shift_type" class="mt-1.5">
                                @foreach ($shiftTypes as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </x-board-select>
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="applyDefaults()" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Apply to this page</button>
                        <button type="submit" class="rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">Allocate selected</button>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50/90 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-3.5">
                                    <input type="checkbox" class="rounded border-slate-300 text-brand-700 focus:ring-brand-500/30" title="Select all on this page" @change="toggleAll($event.target.checked)">
                                </th>
                                <th class="px-3 py-3.5">Guard</th>
                                <th class="px-3 py-3.5">Posted site</th>
                                <th class="px-3 py-3.5">Region</th>
                                <th class="px-3 py-3.5">Status on date</th>
                                <th class="px-3 py-3.5">Period</th>
                                <th class="px-3 py-3.5">Duty type</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($deployments as $deployment)
                                @php
                                    $suggestedPeriod = $deployment->shift_type?->value === 'night'
                                        ? \App\Enums\ShiftPeriod::Night->value
                                        : \App\Enums\ShiftPeriod::Day->value;
                                    $suggestedDutyType = \App\Support\Shifts\ShiftDutyTypeResolver::resolve(
                                        $deployment->shift_type ?? \App\Enums\DeploymentShiftType::Day,
                                        \App\Enums\ShiftPeriod::from($suggestedPeriod),
                                    )->value;
                                    $existing = $deployment->existing_shifts ?? collect();
                                @endphp
                                <tr class="transition hover:bg-brand-50/40">
                                    <td class="px-3 py-3 align-middle">
                                        <input
                                            type="checkbox"
                                            name="selected[]"
                                            value="{{ $deployment->id }}"
                                            data-row-check
                                            class="rounded border-slate-300 text-brand-700 focus:ring-brand-500/30"
                                        >
                                    </td>
                                    <td class="px-3 py-3">
                                        <p class="font-semibold text-slate-900">{{ $deployment->assignedGuard?->full_name }}</p>
                                        <p class="text-xs text-slate-500">{{ $deployment->assignedGuard?->employment_id }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-slate-700">
                                        {{ $deployment->site?->name }}
                                        <span class="block text-xs text-slate-400">{{ $deployment->site?->code }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-slate-600">{{ $deployment->region?->name }}</td>
                                    <td class="px-3 py-3">
                                        @forelse ($existing as $shift)
                                            <span class="mb-1 inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-medium text-emerald-800 ring-1 ring-emerald-100">
                                                {{ $shift->period?->label() }} · {{ $shift->status?->label() }}
                                            </span>
                                        @empty
                                            <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-[11px] font-medium text-amber-800 ring-1 ring-amber-100">Needs shift</span>
                                        @endforelse
                                    </td>
                                    <td class="px-3 py-3">
                                        <x-board-select
                                            name="rows[{{ $deployment->id }}][period]"
                                            data-row-period
                                            :compact="true"
                                            class="min-w-[7.5rem]"
                                        >
                                            @foreach ($periods as $period)
                                                <option value="{{ $period->value }}" @selected($suggestedPeriod === $period->value)>{{ $period->label() }}</option>
                                            @endforeach
                                        </x-board-select>
                                    </td>
                                    <td class="px-3 py-3">
                                        <x-board-select
                                            name="rows[{{ $deployment->id }}][shift_type]"
                                            data-row-type
                                            :compact="true"
                                            class="min-w-[8rem]"
                                        >
                                            @foreach ($shiftTypes as $type)
                                                <option value="{{ $type->value }}" @selected($type->value === $suggestedDutyType)>{{ $type->label() }}</option>
                                            @endforeach
                                        </x-board-select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 px-4 py-3">
                    {{ $deployments->links() }}
                </div>
            </div>
        </form>
    @endif
</div>
@endsection
