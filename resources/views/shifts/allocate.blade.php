@extends('layouts.app')

@section('title', 'Duty roster')
@section('page-title', 'Duty roster')
@section('page-subtitle', 'Confirm Day / Night duties for posted guards — Ugandan PSC daily parade')

@section('content')
@php
    $defaultPeriod = old('default_period', \App\Enums\ShiftPeriod::Day->value);
    $defaultType = old('default_type', \App\Enums\ShiftType::Normal->value);
    $defaultClassification = old('default_classification', \App\Enums\GuardClassification::Unarmed->value);
@endphp
<div
    class="space-y-3"
    x-data="{
        selectAll: false,
        defaults: {
            period: @js($defaultPeriod),
            shift_type: @js($defaultType),
            guard_classification: @js($defaultClassification),
        },
        toggleAll(checked) {
            this.selectAll = checked;
            this.$root.querySelectorAll('[data-row-check]').forEach((el) => { el.checked = checked; });
        },
        applyDefaults() {
            this.$root.querySelectorAll('[data-row-period]').forEach((el) => { el.value = this.defaults.period; });
            this.$root.querySelectorAll('[data-row-type]').forEach((el) => { el.value = this.defaults.shift_type; });
            this.$root.querySelectorAll('[data-row-classification]').forEach((el) => { el.value = this.defaults.guard_classification; });
        },
        selectedCount() {
            return this.$root.querySelectorAll('[data-row-check]:checked').length;
        }
    }"
>
    <x-page-header
        title="Duty roster"
        :subtitle="'Posted guards needing a duty for '. \Illuminate\Support\Carbon::parse($date)->format('d M Y')"
        :back="route('shifts.index', ['date' => $date])"
    >
        <x-slot:actions>
            <a href="{{ route('shifts.index', ['date' => $date]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">Duty register</a>
            <a href="{{ route('deployments.board') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">Posting board</a>
            <a href="{{ route('shifts.create', ['date' => $date]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">Single duty</a>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3">
        @foreach ([
            ['Posted', number_format($stats['deployed']), 'text-emerald-700 dark:text-emerald-400'],
            ['Need roster', number_format($stats['needs_allocation']), 'text-amber-700 dark:text-amber-400'],
            ['On this date', number_format($stats['scheduled_today']), 'text-brand-700 dark:text-brand-300'],
        ] as [$label, $value, $tone])
            <div class="flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                <p class="mt-0.5 text-base font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <section class="filter-bar rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <form method="GET" action="{{ route('shifts.allocate') }}" x-data x-ref="filterForm" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-6 xl:items-end">
            <x-form-field label="Shift date" name="date" type="date" :value="$filters['date'] ?? $date" x-on:change="$refs.filterForm.requestSubmit()" />
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
            <label class="flex items-center gap-2.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs text-slate-700 dark:border-slate-600 dark:bg-slate-900/50 dark:text-slate-300">
                <input type="checkbox" name="show_all" value="1" @checked($showAll ?? false) x-on:change="$refs.filterForm.requestSubmit()" class="rounded border-slate-300 text-brand-700 focus:ring-brand-500/30 dark:border-slate-500 dark:bg-slate-900">
                <span>Show all posted</span>
            </label>
            <a href="{{ route('shifts.allocate', ['date' => $date]) }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">Reset filters</a>
        </form>
    </section>

    @if (session('allocation_errors'))
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100">
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
            title="{{ ($showAll ?? false) ? 'No posted guards match' : 'All posted guards are rostered' }}"
            :description="($showAll ?? false)
                ? 'Post guards first on the Site Posting Board, or clear filters. Only active site postings appear here.'
                : 'Every posted guard already has a duty for this date. Toggle Show all posted to review them.'"
            icon="calendar"
        />
    @else
        <form method="POST" action="{{ route('shifts.allocate.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="shift_date" value="{{ $date }}">

            <div class="sticky top-0 z-10 rounded-2xl border border-brand-200 bg-white/95 p-4 shadow-md backdrop-blur sm:p-5 dark:border-brand-800 dark:bg-slate-900/95">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                    <div class="grid flex-1 gap-3 sm:grid-cols-3">
                        <label class="block text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Default period
                            <x-board-select x-model="defaults.period" class="mt-1.5">
                                @foreach ($periods as $period)
                                    <option value="{{ $period->value }}">{{ $period->label() }}</option>
                                @endforeach
                            </x-board-select>
                        </label>
                        <label class="block text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Default duty type
                            <x-board-select x-model="defaults.shift_type" class="mt-1.5">
                                @foreach ($shiftTypes as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </x-board-select>
                        </label>
                        <label class="block text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Default classification
                            <x-board-select x-model="defaults.guard_classification" class="mt-1.5">
                                @foreach ($classifications as $classification)
                                    <option value="{{ $classification->value }}">{{ $classification->label() }}</option>
                                @endforeach
                            </x-board-select>
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="applyDefaults()" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">Apply to this page</button>
                        <button type="submit" class="rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">Confirm roster</button>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-xs dark:divide-slate-800">
                        <thead class="bg-slate-50/90 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th class="w-8 px-2 py-1.5">
                                    <input type="checkbox" class="h-3.5 w-3.5 rounded border-slate-300 text-brand-700 focus:ring-brand-500/30 dark:border-slate-500 dark:bg-slate-800" title="Select all on this page" @change="toggleAll($event.target.checked)">
                                </th>
                                <th class="px-2 py-1.5">Guard</th>
                                <th class="px-2 py-1.5">Posted site</th>
                                <th class="px-2 py-1.5">Region</th>
                                <th class="px-2 py-1.5">Status</th>
                                <th class="w-[5.5rem] px-2 py-1.5">Period</th>
                                <th class="w-[5.75rem] px-2 py-1.5">Duty</th>
                                <th class="w-[5.75rem] px-2 py-1.5">Class</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($deployments as $deployment)
                                @php
                                    $suggestedPeriod = $deployment->shift_type?->value === 'night'
                                        ? \App\Enums\ShiftPeriod::Night->value
                                        : \App\Enums\ShiftPeriod::Day->value;
                                    $suggestedDutyType = \App\Support\Shifts\ShiftDutyTypeResolver::resolve(
                                        $deployment->shift_type ?? \App\Enums\DeploymentShiftType::Day,
                                        \App\Enums\ShiftPeriod::from($suggestedPeriod),
                                    )->value;
                                    $suggestedClassification = $deployment->assignedGuard?->guard_classification?->value
                                        ?? \App\Enums\GuardClassification::Unarmed->value;
                                    $existing = $deployment->existing_shifts ?? collect();
                                @endphp
                                <tr class="transition hover:bg-brand-50/40 dark:hover:bg-brand-950/30">
                                    <td class="px-2 py-1 align-middle">
                                        <input
                                            type="checkbox"
                                            name="selected[]"
                                            value="{{ $deployment->id }}"
                                            data-row-check
                                            class="h-3.5 w-3.5 rounded border-slate-300 text-brand-700 focus:ring-brand-500/30 dark:border-slate-500 dark:bg-slate-800"
                                        >
                                    </td>
                                    <td class="px-2 py-1">
                                        <p class="truncate text-[11px] font-semibold leading-tight text-slate-900 dark:text-slate-100">{{ $deployment->assignedGuard?->full_name }}</p>
                                        <p class="text-[10px] leading-tight text-slate-500 dark:text-slate-400">{{ $deployment->assignedGuard?->employment_id }}</p>
                                    </td>
                                    <td class="max-w-[11rem] px-2 py-1 text-slate-700 dark:text-slate-300">
                                        <p class="truncate text-[11px] leading-tight">{{ $deployment->site?->name }}</p>
                                        <p class="text-[10px] leading-tight text-slate-400 dark:text-slate-500">{{ $deployment->site?->code }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-2 py-1 text-[11px] text-slate-600 dark:text-slate-400">{{ $deployment->region?->name }}</td>
                                    <td class="px-2 py-1">
                                        @forelse ($existing as $shift)
                                            <span class="inline-flex rounded bg-emerald-50 px-1.5 py-px text-[10px] font-medium leading-tight text-emerald-800 ring-1 ring-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-300 dark:ring-emerald-800">
                                                {{ $shift->period?->label() }} · {{ $shift->status?->label() }}
                                            </span>
                                        @empty
                                            <span class="inline-flex rounded bg-amber-50 px-1.5 py-px text-[10px] font-medium leading-tight text-amber-800 ring-1 ring-amber-100 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800">Needs shift</span>
                                        @endforelse
                                    </td>
                                    <td class="px-2 py-1">
                                        <x-board-select
                                            name="rows[{{ $deployment->id }}][period]"
                                            data-row-period
                                            :compact="true"
                                            class="min-w-0"
                                        >
                                            @foreach ($periods as $period)
                                                <option value="{{ $period->value }}" @selected($suggestedPeriod === $period->value)>{{ $period->label() }}</option>
                                            @endforeach
                                        </x-board-select>
                                    </td>
                                    <td class="px-2 py-1">
                                        <x-board-select
                                            name="rows[{{ $deployment->id }}][shift_type]"
                                            data-row-type
                                            :compact="true"
                                            class="min-w-0"
                                        >
                                            @foreach ($shiftTypes as $type)
                                                <option value="{{ $type->value }}" @selected($type->value === $suggestedDutyType)>{{ $type->label() }}</option>
                                            @endforeach
                                        </x-board-select>
                                    </td>
                                    <td class="px-2 py-1">
                                        <x-board-select
                                            name="rows[{{ $deployment->id }}][guard_classification]"
                                            data-row-classification
                                            :compact="true"
                                            class="min-w-0"
                                        >
                                            @foreach ($classifications as $classification)
                                                <option value="{{ $classification->value }}" @selected($classification->value === $suggestedClassification)>{{ $classification->label() }}</option>
                                            @endforeach
                                        </x-board-select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 px-3 py-2 dark:border-slate-800">
                    {{ $deployments->links() }}
                </div>
            </div>
        </form>
    @endif
</div>
@endsection
