@extends('layouts.app')

@section('title', 'Deploy guards')
@section('page-title', 'Deploy guards')
@section('page-subtitle', 'Assign undeployed company guards to sites — deployed guards move to the active deployments list')

@section('content')
<div
    class="space-y-5"
    x-data="{
        selected: 0,
        defaults: {
            site_id: '',
            shift_type: @js(\App\Enums\DeploymentShiftType::Day->value),
        },
        sync() {
            this.selected = this.$root.querySelectorAll('[data-row-check]:checked').length;
        },
        toggleAll(checked) {
            this.$root.querySelectorAll('[data-row-check]').forEach((el) => { el.checked = checked; });
            this.sync();
        },
        applyDefaultSite() {
            if (! this.defaults.site_id) return;
            const siteId = String(this.defaults.site_id);
            this.$root.querySelectorAll('[data-row-site]').forEach((el) => {
                const options = [...el.options].map((o) => o.value);
                if (options.includes(siteId)) {
                    el.value = siteId;
                }
            });
        },
        applyDefaultType() {
            this.$root.querySelectorAll('[data-row-type]').forEach((el) => { el.value = this.defaults.shift_type; });
        }
    }"
>
    <x-page-header
        title="Deployment board"
        subtitle="Guards without a current site posting. Once deployed they leave this board and appear under Deployments, then on Allocate Shifts for daily scheduling."
        :back="route('deployments.index')"
    >
        <x-slot:actions>
            <a href="{{ route('deployments.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Active list</a>
            <a href="{{ route('shifts.allocate') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Allocate shifts</a>
            <a href="{{ route('deployments.create') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Single form</a>
        </x-slot:actions>
    </x-page-header>

    <section class="rounded-2xl border border-slate-200 bg-gradient-to-br from-white via-white to-emerald-50/50 p-4 shadow-sm sm:p-6">
        <h2 class="text-sm font-semibold text-slate-900">How to deploy</h2>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">
            This board lists <strong class="font-semibold text-slate-800">all company guards</strong> who do not yet have an active site posting.
            Select guards, assign each to a site in their region, choose a <strong class="font-semibold text-slate-800">Day</strong> or <strong class="font-semibold text-slate-800">Night</strong> posting type, then deploy.
            After deployment they <strong class="font-semibold text-slate-800">disappear from this board</strong> and appear under <strong class="font-semibold text-slate-800">Deployments</strong>.
            Use <strong class="font-semibold text-slate-800">Allocate Shifts</strong> to schedule them by date.
            To move a deployed guard to another site, use transfer from the deployment record.
        </p>
    </section>

    <section class="grid grid-cols-2 gap-2 sm:gap-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700 sm:text-[11px]">Awaiting deployment</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ number_format($stats['awaiting']) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700 sm:text-[11px]">Active deployments</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ number_format($stats['active']) }}</p>
        </div>
    </section>

    @if ($regions->count() > 1)
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Available by region</p>
            <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                <a
                    href="{{ route('deployments.board', array_filter(['q' => $filters['q'] ?? null])) }}"
                    class="shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold {{ empty($filters['region_id']) ? 'bg-brand-700 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}"
                >
                    All · {{ number_format($stats['awaiting']) }}
                </a>
                @foreach ($regions as $region)
                    @php $count = (int) ($regionCounts[$region->id] ?? 0); @endphp
                    <a
                        href="{{ route('deployments.board', array_filter(['region_id' => $region->id, 'q' => $filters['q'] ?? null])) }}"
                        class="shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold {{ (string) ($filters['region_id'] ?? '') === (string) $region->id ? 'bg-brand-700 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}"
                    >
                        {{ $region->name }} · {{ number_format($count) }}
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <form method="GET" action="{{ route('deployments.board') }}" x-data x-ref="filterForm" class="grid gap-3 sm:grid-cols-3 xl:items-end">
            <x-form-field label="Search guard" name="q" type="search" :value="$filters['q'] ?? ''" placeholder="Name or ID" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Region" name="region_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All regions</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" @selected((string) ($filters['region_id'] ?? '') === (string) $region->id)>{{ $region->name }} ({{ number_format($regionCounts[$region->id] ?? 0) }})</option>
                @endforeach
            </x-form-field>
            <a href="{{ route('deployments.board') }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset filters</a>
        </form>
    </section>

    @if (session('deployment_errors'))
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-semibold">Some rows were skipped</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-4">
                @foreach (session('deployment_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($guards->isEmpty())
        <x-empty-state title="No undeployed guards" description="Every eligible guard already has an active site posting. Check Deployments or end a posting to return someone here." icon="map" />
    @else
        <form method="POST" action="{{ route('deployments.board.store') }}" class="space-y-4">
            @csrf

            <div class="sticky top-0 z-10 rounded-2xl border border-emerald-200 bg-white/95 p-4 shadow-md backdrop-blur sm:p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Start date
                            <input type="date" name="start_date" value="{{ old('start_date', now()->toDateString()) }}" class="mt-1.5 block w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-800 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                        </label>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 sm:col-span-2 lg:col-span-1">
                            Default site
                            <x-board-select x-model="defaults.site_id" class="mt-1.5">
                                <option value="">Choose a site…</option>
                                @foreach ($regions as $region)
                                    @php $regionSites = $sitesByRegion->get($region->id, collect()); @endphp
                                    @if ($regionSites->isNotEmpty())
                                        <optgroup label="{{ $region->name }}">
                                            @foreach ($regionSites as $site)
                                                <option value="{{ $site->id }}">{{ $site->name }} · {{ $site->code }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                @endforeach
                            </x-board-select>
                        </label>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Default posting type
                            <x-board-select x-model="defaults.shift_type" class="mt-1.5">
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
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="applyDefaultSite(); applyDefaultType()" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Apply to page</button>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="selected === 0"
                        >
                            Deploy selected
                            <span class="rounded-md bg-white/20 px-1.5 py-0.5 text-xs" x-text="selected"></span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Mobile cards --}}
            <div class="grid gap-3 lg:hidden">
                <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm">
                    <input type="checkbox" class="rounded border-slate-300 text-brand-700 focus:ring-brand-500/30" @change="toggleAll($event.target.checked)">
                    Select all on this page
                </label>

                @foreach ($guards as $guard)
                    @php $guardSites = $sitesByRegion->get($guard->region_id, collect()); @endphp
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start gap-3">
                            <input
                                type="checkbox"
                                name="selected[]"
                                value="{{ $guard->id }}"
                                data-row-check
                                class="mt-1 rounded border-slate-300 text-brand-700 focus:ring-brand-500/30"
                                @change="sync()"
                            >
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-slate-900">{{ $guard->full_name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $guard->employment_id }}</p>
                                <p class="mt-2 inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-700">{{ $guard->region?->name }}</p>
                                <p class="mt-1 text-[11px] text-slate-500">{{ $guard->operational_status->label() }}</p>
                            </div>
                        </div>

                        <div class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Assign to site
                                <x-board-select
                                    name="rows[{{ $guard->id }}][site_id]"
                                    data-row-site
                                    data-region-id="{{ $guard->region_id }}"
                                    class="mt-1.5 w-full"
                                >
                                    <option value="">Choose site in {{ $guard->region?->name }}…</option>
                                    @foreach ($guardSites as $site)
                                        <option value="{{ $site->id }}">{{ $site->name }} · {{ $site->code }}</option>
                                    @endforeach
                                </x-board-select>
                            </label>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Posting type
                                <x-board-select
                                    name="rows[{{ $guard->id }}][shift_type]"
                                    data-row-type
                                    class="mt-1.5 w-full"
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
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Desktop table --}}
            <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:block">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50/90 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-3.5">
                                    <input type="checkbox" class="rounded border-slate-300 text-brand-700 focus:ring-brand-500/30" title="Select all on this page" @change="toggleAll($event.target.checked)">
                                </th>
                                <th class="px-3 py-3.5">Guard</th>
                                <th class="px-3 py-3.5">Region</th>
                                <th class="px-3 py-3.5">Status</th>
                                <th class="px-3 py-3.5">Assign to site</th>
                                <th class="px-3 py-3.5">Posting type</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($guards as $guard)
                                @php $guardSites = $sitesByRegion->get($guard->region_id, collect()); @endphp
                                <tr class="transition hover:bg-emerald-50/40">
                                    <td class="px-3 py-3 align-middle">
                                        <input
                                            type="checkbox"
                                            value="{{ $guard->id }}"
                                            data-row-check
                                            data-desktop-check
                                            class="rounded border-slate-300 text-brand-700 focus:ring-brand-500/30"
                                            @change="sync()"
                                        >
                                    </td>
                                    <td class="px-3 py-3">
                                        <p class="font-semibold text-slate-900">{{ $guard->full_name }}</p>
                                        <p class="text-xs text-slate-500">{{ $guard->employment_id }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-slate-600">{{ $guard->region?->name }}</td>
                                    <td class="px-3 py-3 text-xs text-slate-500">{{ $guard->operational_status->label() }}</td>
                                    <td class="px-3 py-3">
                                        <x-board-select
                                            name="rows[{{ $guard->id }}][site_id]"
                                            data-row-site
                                            data-region-id="{{ $guard->region_id }}"
                                            :compact="true"
                                            class="min-w-[14rem]"
                                        >
                                            <option value="">Choose site…</option>
                                            @foreach ($guardSites as $site)
                                                <option value="{{ $site->id }}">{{ $site->name }} · {{ $site->code }}</option>
                                            @endforeach
                                        </x-board-select>
                                    </td>
                                    <td class="px-3 py-3">
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

                    form.querySelectorAll('[data-row-site], [data-row-type]').forEach((el) => {
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
