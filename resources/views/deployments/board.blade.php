@extends('layouts.app')

@section('title', 'Site posting board')
@section('page-title', 'Site posting board')
@section('page-subtitle', 'Post company guards to client sites — Day/Night cover opens a duty; Rotating stays until transfer or end')

@section('content')
<div
    class="space-y-3"
    x-data="{
        selected: 0,
        defaults: {
            site_id: '',
            shift_type: @js(\App\Enums\DeploymentShiftType::Day->value),
            duty_type: @js(\App\Enums\ShiftType::Normal->value),
        },
        activeChecks() {
            const isDesktop = window.matchMedia('(min-width: 1024px)').matches;
            const selector = isDesktop ? '[data-board-viewport=desktop]' : '[data-board-viewport=mobile]';
            const viewport = this.$root.querySelector(selector);
            return viewport ? viewport.querySelectorAll('[data-row-check]') : this.$root.querySelectorAll('[data-row-check]');
        },
        sync() {
            this.selected = Array.from(this.activeChecks()).filter((el) => el.checked).length;
        },
        toggleAll(checked) {
            this.activeChecks().forEach((el) => { el.checked = checked; });
            this.sync();
        },
        applyDefaultSite() {
            if (! this.defaults.site_id) return;
            const siteId = String(this.defaults.site_id);
            this.$root.querySelectorAll('[data-row-site]').forEach((el) => {
                const option = Array.from(el.options).find((o) => String(o.value) === siteId);
                if (! option || option.disabled) return;
                const siteRegion = option.getAttribute('data-region-id');
                const guardRegion = el.getAttribute('data-region-id');
                if (siteRegion && guardRegion && String(siteRegion) !== String(guardRegion)) return;
                el.value = siteId;
            });
        },
        applyDefaultType() {
            this.$root.querySelectorAll('[data-row-type]').forEach((el) => { el.value = this.defaults.shift_type; });
            this.$root.querySelectorAll('[data-row-duty]').forEach((el) => { el.value = this.defaults.duty_type; });
        }
    }"
    x-init="sync(); window.addEventListener('resize', () => sync())"
>
    <x-page-header
        title="Site posting board"
        subtitle="Post awaiting guards to client sites."
        :back="route('deployments.index')"
    />

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
        @foreach ([
            ['Awaiting', number_format($stats['awaiting']), 'text-amber-700'],
            ['Active', number_format($stats['active']), 'text-emerald-700'],
            ['Day', number_format($stats['day']), 'text-amber-800'],
            ['Night', number_format($stats['night']), 'text-indigo-700'],
        ] as [$label, $value, $tone])
            <div class="flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                <p class="mt-0.5 text-base font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    @if ($regions->count() > 1)
        <section class="form-card">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Available by region</p>
            <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                <a
                    href="{{ route('deployments.board', array_filter(['q' => $filters['q'] ?? null, 'start_date' => $filters['start_date'] ?? null])) }}"
                    class="shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold {{ empty($filters['region_id']) ? 'bg-brand-700 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600' }}"
                >
                    All · {{ number_format($stats['awaiting']) }}
                </a>
                @foreach ($regions as $region)
                    @php $count = (int) ($regionCounts[$region->id] ?? 0); @endphp
                    <a
                        href="{{ route('deployments.board', array_filter(['region_id' => $region->id, 'q' => $filters['q'] ?? null, 'start_date' => $filters['start_date'] ?? null])) }}"
                        class="shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold {{ (string) ($filters['region_id'] ?? '') === (string) $region->id ? 'bg-brand-700 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600' }}"
                    >
                        {{ $region->name }} · {{ number_format($count) }}
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="filter-bar rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <form method="GET" action="{{ route('deployments.board') }}" x-data x-ref="filterForm" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 xl:items-end">
            <x-form-field label="Duty date" name="start_date" type="date" :value="$filters['start_date'] ?? now()->toDateString()" x-on:change="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Search guard" name="q" type="search" :value="$filters['q'] ?? ''" placeholder="Name or ID" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Region" name="region_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All regions</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" @selected((string) ($filters['region_id'] ?? '') === (string) $region->id)>{{ $region->name }} ({{ number_format($regionCounts[$region->id] ?? 0) }})</option>
                @endforeach
            </x-form-field>
            <a href="{{ route('deployments.board') }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">Reset filters</a>
        </form>
    </section>

    @if ($isHistorical)
        <p class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-950 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-100">
            Historical duty date <strong>{{ \Illuminate\Support\Carbon::parse($dutyDate)->format('d M Y') }}</strong>:
            showing guards with no site posting covering that day. Duty-day status is <strong>Awaiting deployment</strong>
            (their current “Today” status may still be On Duty). Recording a past posting will not change today’s operational status.
        </p>
    @endif

    @if (session('deployment_errors'))
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100">
            <p class="font-semibold">Some rows were skipped</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-4">
                @foreach (session('deployment_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($guards->isEmpty())
        <x-empty-state
            title="{{ $isHistorical ? 'No guards free on this duty date' : 'No undeployed guards' }}"
            :description="$isHistorical
                ? 'Every eligible guard already had a posting covering '.$dutyDate.'. Pick another date or review overlapping historical postings.'
                : 'Every eligible guard already has an active site posting. Check Deployments or end a posting to return someone here.'"
            icon="map"
        />
    @else
        <form method="POST" action="{{ route('deployments.board.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="start_date" value="{{ old('start_date', $dutyDate) }}">

            <div class="rounded-lg border border-emerald-200 bg-white p-3 shadow-sm dark:border-emerald-800 dark:bg-slate-800">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                    <div class="grid min-w-0 flex-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        <label class="block min-w-0 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Duty date
                            <input type="date" value="{{ $dutyDate }}" disabled class="mt-0.5 block h-[1.875rem] w-full rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-medium leading-tight text-slate-600 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300">
                        </label>
                        <label class="block min-w-0 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Default site
                            <x-board-select x-model="defaults.site_id" class="mt-0.5">
                                <option value="">Choose a site…</option>
                                @foreach ($regions as $region)
                                    @php $regionSites = $sitesByRegion->get((int) $region->id, collect()); @endphp
                                    @if ($regionSites->isNotEmpty())
                                        <optgroup label="{{ $region->name }} ({{ $regionSites->count() }})">
                                            @foreach ($regionSites as $site)
                                                <option value="{{ $site->id }}" data-region-id="{{ $site->region_id }}">
                                                    {{ $site->name }} · {{ $site->code }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                @endforeach
                            </x-board-select>
                        </label>
                        <label class="block min-w-0 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Default posting type
                            <x-board-select x-model="defaults.shift_type" class="mt-0.5">
                                @foreach ($shiftTypes as $type)
                                    <option value="{{ $type->value }}">
                                        @if ($type === \App\Enums\DeploymentShiftType::Day)
                                            Day posting
                                        @elseif ($type === \App\Enums\DeploymentShiftType::Night)
                                            Night posting
                                        @else
                                            Rotating posting
                                        @endif
                                    </option>
                                @endforeach
                            </x-board-select>
                        </label>
                        <label class="block min-w-0 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Default duty (Normal / OT)
                            <x-board-select x-model="defaults.duty_type" class="mt-0.5">
                                <option value="{{ \App\Enums\ShiftType::Normal->value }}">Normal</option>
                                <option value="{{ \App\Enums\ShiftType::Overtime->value }}">Overtime</option>
                            </x-board-select>
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="applyDefaultSite(); applyDefaultType()" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-700">Apply to page</button>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="selected === 0"
                        >
                            Deploy selected
                            <span class="rounded-md bg-white/20 px-1.5 py-0.5 text-xs" x-text="selected"></span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Mobile cards --}}
            <div class="grid gap-2 lg:hidden" data-board-viewport="mobile">
                <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                    <input type="checkbox" class="h-3.5 w-3.5 rounded border-slate-300 text-brand-700 focus:ring-brand-500/30 dark:border-slate-600" @change="toggleAll($event.target.checked)">
                    Select all on this page
                </label>

                @foreach ($guards as $guard)
                    <article class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                        <div class="flex items-start gap-2">
                            <input
                                type="checkbox"
                                name="selected[]"
                                value="{{ $guard->id }}"
                                data-row-check
                                class="mt-0.5 h-3.5 w-3.5 rounded border-slate-300 text-brand-700 focus:ring-brand-500/30 dark:border-slate-600"
                                @change="sync()"
                            >
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-medium text-slate-900 dark:text-slate-100">{{ $guard->full_name }}</p>
                                <p class="mt-0.5 text-[10px] text-slate-500">{{ $guard->employment_id }}</p>
                                <p class="mt-1.5 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-200">{{ $guard->region?->name }}</p>
                                @if ($isHistorical)
                                    <p class="mt-0.5 text-[10px] font-medium text-amber-700 dark:text-amber-300">Awaiting deployment</p>
                                    <p class="text-[9px] text-slate-400">Today: {{ $guard->operational_status->label() }}</p>
                                @else
                                    <p class="mt-0.5 text-[10px] text-slate-500">{{ $guard->operational_status->label() }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="mt-2.5 space-y-2 border-t border-slate-100 pt-2.5 dark:border-slate-700">
                            <label class="block text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Assign to site
                                <x-board-select
                                    name="rows[{{ $guard->id }}][site_id]"
                                    data-row-site
                                    data-region-id="{{ $guard->region_id }}"
                                    class="mt-1 w-full"
                                >
                                    @include('deployments.partials.board-site-options', ['guard' => $guard])
                                </x-board-select>
                            </label>
                            <label class="block text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Posting type
                                <x-board-select
                                    name="rows[{{ $guard->id }}][shift_type]"
                                    data-row-type
                                    class="mt-1 w-full"
                                >
                                    @foreach ($shiftTypes as $type)
                                        <option value="{{ $type->value }}" @selected($type === \App\Enums\DeploymentShiftType::Day)>
                                            @if ($type === \App\Enums\DeploymentShiftType::Day)
                                                Day posting
                                            @elseif ($type === \App\Enums\DeploymentShiftType::Night)
                                                Night posting
                                            @else
                                                Rotating
                                            @endif
                                        </option>
                                    @endforeach
                                </x-board-select>
                            </label>
                            <label class="block text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Duty type
                                <x-board-select
                                    name="rows[{{ $guard->id }}][duty_type]"
                                    data-row-duty
                                    class="mt-1 w-full"
                                >
                                    <option value="{{ \App\Enums\ShiftType::Normal->value }}" selected>Normal</option>
                                    <option value="{{ \App\Enums\ShiftType::Overtime->value }}">Overtime</option>
                                </x-board-select>
                            </label>
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Desktop table --}}
            <div class="hidden overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800 lg:block" data-board-viewport="desktop">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-xs dark:divide-slate-700">
                        <thead class="bg-slate-50/90 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th class="w-10 px-2.5 py-2">
                                    <input type="checkbox" class="h-3.5 w-3.5 rounded border-slate-300 text-brand-700 focus:ring-brand-500/30 dark:border-slate-600" title="Select all on this page" @change="toggleAll($event.target.checked)">
                                </th>
                                <th class="px-2.5 py-2">Guard</th>
                                <th class="px-2.5 py-2">Region</th>
                                <th class="px-2.5 py-2">{{ $isHistorical ? 'Duty-day status' : 'Status' }}</th>
                                <th class="px-2.5 py-2">Assign to site</th>
                                <th class="px-2.5 py-2">Posting type</th>
                                <th class="px-2.5 py-2">Duty type</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach ($guards as $guard)
                                <tr class="transition hover:bg-emerald-50/40 dark:hover:bg-emerald-950/30">
                                    <td class="px-2.5 py-1.5 align-middle">
                                        <input
                                            type="checkbox"
                                            value="{{ $guard->id }}"
                                            data-row-check
                                            data-desktop-check
                                            class="h-3.5 w-3.5 rounded border-slate-300 text-brand-700 focus:ring-brand-500/30 dark:border-slate-600"
                                            @change="sync()"
                                        >
                                    </td>
                                    <td class="px-2.5 py-1.5">
                                        <p class="text-xs font-medium text-slate-900 dark:text-slate-100">{{ $guard->full_name }}</p>
                                        <p class="text-[10px] text-slate-500">{{ $guard->employment_id }}</p>
                                    </td>
                                    <td class="px-2.5 py-1.5 text-slate-600 dark:text-slate-300">{{ $guard->region?->name }}</td>
                                    <td class="px-2.5 py-1.5 text-[10px] text-slate-500">
                                        @if ($isHistorical)
                                            <p class="font-medium text-amber-700 dark:text-amber-300">Awaiting deployment</p>
                                            <p class="text-[9px] text-slate-400">Today: {{ $guard->operational_status->label() }}</p>
                                        @else
                                            {{ $guard->operational_status->label() }}
                                        @endif
                                    </td>
                                    <td class="px-2.5 py-1.5">
                                        <x-board-select
                                            name="rows[{{ $guard->id }}][site_id]"
                                            data-row-site
                                            data-region-id="{{ $guard->region_id }}"
                                            :compact="true"
                                            class="min-w-[14rem]"
                                        >
                                            @include('deployments.partials.board-site-options', ['guard' => $guard])
                                        </x-board-select>
                                    </td>
                                    <td class="px-2.5 py-1.5">
                                        <x-board-select
                                            name="rows[{{ $guard->id }}][shift_type]"
                                            data-row-type
                                            :compact="true"
                                        >
                                            @foreach ($shiftTypes as $type)
                                                <option value="{{ $type->value }}" @selected($type === \App\Enums\DeploymentShiftType::Day)>
                                                    @if ($type === \App\Enums\DeploymentShiftType::Day)
                                                        Day posting
                                                    @elseif ($type === \App\Enums\DeploymentShiftType::Night)
                                                        Night posting
                                                    @else
                                                        Rotating
                                                    @endif
                                                </option>
                                            @endforeach
                                        </x-board-select>
                                    </td>
                                    <td class="px-2.5 py-1.5">
                                        <x-board-select
                                            name="rows[{{ $guard->id }}][duty_type]"
                                            data-row-duty
                                            :compact="true"
                                        >
                                            <option value="{{ \App\Enums\ShiftType::Normal->value }}" selected>Normal</option>
                                            <option value="{{ \App\Enums\ShiftType::Overtime->value }}">Overtime</option>
                                        </x-board-select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500">
                    Showing {{ $guards->firstItem() ?? 0 }}–{{ $guards->lastItem() ?? 0 }} of {{ number_format($guards->total()) }} undeployed guards
                    @if (empty($filters['region_id']))
                        across all regions
                    @endif
                </p>
                <div>{{ $guards->links() }}</div>
            </div>

            <script>
                document.currentScript.closest('form').addEventListener('submit', function (event) {
                    const form = event.currentTarget;
                    const isDesktop = window.matchMedia('(min-width: 1024px)').matches;

                    form.querySelectorAll('input[data-synced-selected]').forEach((el) => el.remove());

                    const selectedIds = new Set();
                    const mobileViewport = form.querySelector('[data-board-viewport="mobile"]');
                    const desktopViewport = form.querySelector('[data-board-viewport="desktop"]');
                    const inactiveViewport = isDesktop ? mobileViewport : desktopViewport;

                    // Prevent duplicate mobile/desktop fields from colliding on submit.
                    inactiveViewport?.querySelectorAll('input, select, textarea').forEach((el) => {
                        el.disabled = true;
                    });

                    if (isDesktop) {
                        form.querySelectorAll('input[name="selected[]"]').forEach((el) => { el.disabled = true; });
                        form.querySelectorAll('[data-desktop-check]:checked').forEach((el) => {
                            selectedIds.add(String(el.value));
                            const hidden = document.createElement('input');
                            hidden.type = 'hidden';
                            hidden.name = 'selected[]';
                            hidden.value = el.value;
                            hidden.setAttribute('data-synced-selected', '1');
                            form.appendChild(hidden);
                        });
                    } else {
                        form.querySelectorAll('input[name="selected[]"]:checked').forEach((el) => {
                            selectedIds.add(String(el.value));
                        });
                    }

                    form.querySelectorAll('[data-row-site], [data-row-type], [data-row-duty]').forEach((el) => {
                        if (el.disabled) {
                            return;
                        }

                        const match = el.name.match(/^rows\[(\d+)\]/);
                        if (! match || ! selectedIds.has(match[1])) {
                            el.disabled = true;
                        }
                    });
                });
            </script>
        </form>
    @endif
</div>
@endsection
