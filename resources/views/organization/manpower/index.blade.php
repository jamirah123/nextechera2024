@extends('layouts.app')

@section('title', 'Manpower Coverage')
@section('page-title', 'Manpower Coverage')
@section('page-subtitle', $dateMode ? 'Required vs deployed for '.$coverageDate : 'Required vs deployed guards by site')

@section('content')
<div class="space-y-3">
    <x-page-header
        title="Manpower coverage"
        :subtitle="$dateMode ? 'Required vs deployed for '.$coverageDate : 'Required vs deployed guards by site.'"
        :back="route('organization.index')"
    >
        <x-slot:actions>
            <x-report-actions
                :csv="route('manpower.coverage.export', array_merge($exportQuery, ['format' => 'csv']))"
            >
                <a href="{{ route('sites.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Manage sites
                </a>
            </x-report-actions>
        </x-slot:actions>
    </x-page-header>

    <div class="report-print-area space-y-3">
        <x-print.report-header
            title="Manpower coverage"
            :subtitle="$dateMode ? 'Coverage for '.$coverageDate : 'Required vs deployed guards by site.'"
        />

        {{-- Summary cards (same pattern with/without date) --}}
        <section class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-6">
            <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Required</p>
                <p class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900">{{ number_format($company['required']) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700">Deployed</p>
                <p class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900">{{ number_format($company['deployed']) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-rose-700">Shortage</p>
                <p class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900">{{ number_format($company['shortage']) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-orange-700">Deficit</p>
                <p class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900">{{ number_format($totalDeficit ?? 0) }}</p>
            </div>
            @if ($dateMode)
                <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-sky-700">Allocated</p>
                    <p class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900">{{ number_format($company['allocated']) }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-indigo-700">Alloc. short</p>
                    <p class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900">{{ number_format($company['allocation_shortage']) }}</p>
                </div>
            @else
                <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700">Surplus</p>
                    <p class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900">{{ number_format($company['surplus']) }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-indigo-700">Coverage</p>
                    <p class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900">{{ $company['coverage_percent'] }}%</p>
                    <p class="text-[10px] text-slate-500">{{ $company['status']->label() }}</p>
                </div>
            @endif
        </section>

        <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
            <form
                method="GET"
                action="{{ route('manpower.coverage') }}"
                x-data
                x-ref="filterForm"
                class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5 lg:items-end"
            >
                <x-form-field label="Coverage date" name="date" type="date" :value="$filters['date'] ?? ''" x-on:change="$refs.filterForm.requestSubmit()" />
                <x-form-field label="Region" name="region_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                    <option value="">All regions</option>
                    @foreach ($regions as $region)
                        <option value="{{ $region->id }}" @selected((string) ($filters['region_id'] ?? '') === (string) $region->id)>
                            {{ $region->name }} ({{ $region->code }})
                        </option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Site status" name="status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? 'active') === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </x-form-field>
                <x-filter-reset :href="route('manpower.coverage')" />
            </form>
        </section>

        @if ($dateMode && $gapSummary && $gapRows->isNotEmpty())
            @php $openGaps = (int) ($gapSummary['open_gaps'] ?? 0); @endphp
            <section
                class="rounded-lg border border-slate-200 bg-white shadow-sm"
                x-data="{ open: {{ $openGaps > 0 ? 'true' : 'false' }} }"
            >
                <button type="button" class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left" @click="open = !open">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900">Overtime gaps</p>
                        <p class="mt-0.5 text-[11px] text-slate-500">
                            Open {{ number_format($openGaps) }}
                            · Remaining {{ number_format($gapSummary['remaining_shortage']) }}
                            · OT covered {{ number_format($gapSummary['overtime_covered']) }}
                            · Est. {{ number_format($gapSummary['estimated_ot_cost'], 0) }}
                        </p>
                    </div>
                    <span class="shrink-0 text-xs font-semibold text-brand-700" x-text="open ? 'Hide' : 'Show'"></span>
                </button>

                <div x-show="open" x-cloak class="border-t border-slate-100 px-3 py-2.5">
                    <x-flash-status />
                    @error('guard_id')
                        <p class="mb-2 rounded-md border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs text-rose-800">{{ $message }}</p>
                    @enderror

                    <div class="hidden overflow-x-auto lg:block">
                        <table class="min-w-full text-left text-[11px]">
                            <thead class="bg-slate-50 text-[9px] font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-2 py-1.5">Site / shift</th>
                                    <th class="px-2 py-1.5 text-right">Perm</th>
                                    <th class="px-2 py-1.5 text-right">Orig</th>
                                    <th class="px-2 py-1.5 text-right">OT</th>
                                    <th class="px-2 py-1.5 text-right">Left</th>
                                    <th class="px-2 py-1.5">Status</th>
                                    <th class="no-print px-2 py-1.5">Assign OT</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($gapRows as $gap)
                                    <tr class="align-middle hover:bg-slate-50/80">
                                        <td class="px-2 py-1.5">
                                            <a href="{{ route('sites.show', $gap->site) }}" class="font-semibold text-brand-800 hover:underline">{{ $gap->site?->name }}</a>
                                            <span class="text-slate-400"> · {{ $gap->period->label() }}</span>
                                        </td>
                                        <td class="px-2 py-1.5 text-right tabular-nums">{{ $gap->permanent_deployed }}/{{ $gap->required }}</td>
                                        <td class="px-2 py-1.5 text-right font-medium tabular-nums text-rose-700">{{ $gap->original_shortage }}</td>
                                        <td class="px-2 py-1.5 text-right tabular-nums text-orange-700">{{ $gap->overtime_covered }}</td>
                                        <td class="px-2 py-1.5 text-right font-semibold tabular-nums {{ $gap->remaining_shortage > 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                                            {{ $gap->remaining_shortage }}
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <x-status-badge :tone="$gap->status->tone()" :label="$gap->status->label()" />
                                        </td>
                                        <td class="no-print px-2 py-1.5">
                                            @if ($canResolveGaps && $gap->isOpen())
                                                <form method="POST" action="{{ route('manpower.gaps.overtime', $gap) }}" class="flex flex-wrap items-center gap-1">
                                                    @csrf
                                                    <select name="guard_id" required class="min-w-[10rem] flex-1 rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px]">
                                                        <option value="">Guard…</option>
                                                        @foreach (($gapGuardOptions[$gap->id] ?? collect()) as $guard)
                                                            <option value="{{ $guard->id }}">{{ $guard->employment_id }} — {{ $guard->full_name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="submit" class="rounded-md bg-amber-700 px-2 py-1 text-[10px] font-semibold text-white hover:bg-amber-800">Assign</button>
                                                </form>
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="grid gap-2 lg:hidden">
                        @foreach ($gapRows as $gap)
                            <div class="rounded-md border border-slate-100 bg-slate-50/70 p-2.5">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-xs font-semibold text-slate-900">{{ $gap->site?->name }}</p>
                                        <p class="text-[10px] text-slate-500">{{ $gap->period->label() }} · left {{ $gap->remaining_shortage }}</p>
                                    </div>
                                    <x-status-badge :tone="$gap->status->tone()" :label="$gap->status->label()" />
                                </div>
                                @if ($canResolveGaps && $gap->isOpen())
                                    <form method="POST" action="{{ route('manpower.gaps.overtime', $gap) }}" class="mt-2 flex gap-1">
                                        @csrf
                                        <select name="guard_id" required class="min-w-0 flex-1 rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px]">
                                            <option value="">Guard…</option>
                                            @foreach (($gapGuardOptions[$gap->id] ?? collect()) as $guard)
                                                <option value="{{ $guard->id }}">{{ $guard->employment_id }} — {{ $guard->full_name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="rounded-md bg-amber-700 px-2 py-1 text-[10px] font-semibold text-white">Assign</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($rows->isEmpty())
            <x-empty-state
                title="No sites match these filters"
                description="Try another region, status, or date to review manpower coverage."
                icon="chart"
            />
        @else
            <div class="no-print flex flex-wrap items-center justify-between gap-2 rounded-lg border border-brand-100 bg-brand-50 px-3 py-2 text-xs text-brand-950">
                <p>
                    <span class="font-semibold">Coverage report ready.</span>
                    Export the filtered site manpower summary.
                </p>
                <a
                    href="{{ route('manpower.coverage.export', array_merge($exportQuery, ['format' => 'csv'])) }}"
                    class="inline-flex items-center gap-1 rounded-lg bg-brand-700 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-brand-800"
                >
                    Export CSV
                </a>
            </div>

            <div class="data-table-shell hidden lg:block">
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="w-10">#</th>
                                <th>Site</th>
                                <th>Region</th>
                                <th class="text-right">Req</th>
                                <th>Day</th>
                                <th>Night</th>
                                <th class="text-right">Rem</th>
                                <th class="text-right">Deficit</th>
                                <th class="text-right">Cov</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                @php
                                    $site = $row['site'];
                                    $mp = $row['manpower'];
                                    $shifts = $mp['shifts'] ?? [];
                                    $day = $shifts['day'] ?? [];
                                    $night = $shifts['night'] ?? [];
                                    $dayTone = match ($day['status'] ?? '') {
                                        'covered' => 'text-emerald-700',
                                        'understaffed' => 'text-rose-700',
                                        default => 'text-slate-500',
                                    };
                                    $nightTone = match ($night['status'] ?? '') {
                                        'covered' => 'text-emerald-700',
                                        'understaffed' => 'text-rose-700',
                                        default => 'text-slate-500',
                                    };
                                    $deficit = (int) ($mp['deficit'] ?? $shifts['deficit'] ?? 0);
                                    $status = $dateMode ? ($mp['allocation_status'] ?? $mp['status']) : $mp['status'];
                                    $cov = $dateMode
                                        ? ($mp['allocation_coverage_percent'] ?? $mp['coverage_percent'])
                                        : $mp['coverage_percent'];
                                @endphp
                                <tr class="hover:bg-slate-50/80">
                                    <td class="text-slate-500">
                                        <x-table-serial :paginator="$rows" :index="$loop->index" />
                                    </td>
                                    <td>
                                        <a href="{{ route('sites.show', $site) }}" class="font-semibold text-slate-900 hover:text-brand-700">
                                            {{ $site->name }}
                                        </a>
                                        <p class="text-[10px] text-slate-500">{{ $site->code }}</p>
                                    </td>
                                    <td class="text-slate-600">{{ $site->region?->name ?? '—' }}</td>
                                    <td class="text-right font-medium tabular-nums text-slate-900">{{ $mp['required'] }}</td>
                                    <td class="font-medium tabular-nums {{ $dayTone }}">{{ $day['short'] ?? '—' }}</td>
                                    <td class="font-medium tabular-nums {{ $nightTone }}">{{ $night['short'] ?? '—' }}</td>
                                    <td class="text-right font-semibold tabular-nums {{ ($shifts['remaining'] ?? $mp['shortage']) > 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                                        {{ $shifts['remaining'] ?? $mp['shortage'] }}
                                    </td>
                                    <td class="text-right font-semibold tabular-nums {{ $deficit > 0 ? 'text-orange-700' : 'text-slate-400' }}" title="Overtime covering permanent posts">
                                        {{ $deficit }}
                                    </td>
                                    <td class="text-right font-semibold tabular-nums text-slate-900">{{ $cov }}%</td>
                                    <td>
                                        <x-status-badge :tone="$status->tone()" :label="$status->label()" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid gap-2 lg:hidden">
                @foreach ($rows as $row)
                    @php
                        $site = $row['site'];
                        $mp = $row['manpower'];
                        $shifts = $mp['shifts'] ?? [];
                        $day = $shifts['day'] ?? [];
                        $night = $shifts['night'] ?? [];
                        $dayTone = match ($day['status'] ?? '') {
                            'covered' => 'text-emerald-700',
                            'understaffed' => 'text-rose-700',
                            default => 'text-slate-500',
                        };
                        $nightTone = match ($night['status'] ?? '') {
                            'covered' => 'text-emerald-700',
                            'understaffed' => 'text-rose-700',
                            default => 'text-slate-500',
                        };
                        $status = $dateMode ? ($mp['allocation_status'] ?? $mp['status']) : $mp['status'];
                    @endphp
                    <a href="{{ route('sites.show', $site) }}" class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-xs font-semibold text-slate-900">{{ $site->name }}</p>
                                <p class="mt-0.5 text-[10px] text-slate-500">{{ $site->code }} · {{ $site->region?->name ?? 'No region' }}</p>
                            </div>
                            <x-status-badge :tone="$status->tone()" :label="$status->label()" />
                        </div>
                        <div class="mt-2 grid grid-cols-5 gap-1.5 text-center text-[11px]">
                            <div class="rounded-md bg-slate-50 px-1.5 py-1">
                                <p class="text-[9px] font-medium uppercase text-slate-500">Req</p>
                                <p class="mt-0.5 font-semibold tabular-nums text-slate-900">{{ $mp['required'] }}</p>
                            </div>
                            <div class="rounded-md bg-slate-50 px-1.5 py-1">
                                <p class="text-[9px] font-medium uppercase text-slate-500">Day</p>
                                <p class="mt-0.5 font-semibold tabular-nums {{ $dayTone }}">{{ $day['short'] ?? '—' }}</p>
                            </div>
                            <div class="rounded-md bg-slate-50 px-1.5 py-1">
                                <p class="text-[9px] font-medium uppercase text-slate-500">Night</p>
                                <p class="mt-0.5 font-semibold tabular-nums {{ $nightTone }}">{{ $night['short'] ?? '—' }}</p>
                            </div>
                            <div class="rounded-md bg-slate-50 px-1.5 py-1">
                                <p class="text-[9px] font-medium uppercase text-slate-500">Rem</p>
                                <p class="mt-0.5 font-semibold tabular-nums text-rose-700">{{ $shifts['remaining'] ?? $mp['shortage'] }}</p>
                            </div>
                            <div class="rounded-md bg-slate-50 px-1.5 py-1">
                                <p class="text-[9px] font-medium uppercase text-slate-500">Deficit</p>
                                <p class="mt-0.5 font-semibold tabular-nums text-orange-700">{{ (int) ($mp['deficit'] ?? $shifts['deficit'] ?? 0) }}</p>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            @if ($rows->hasPages() || $rows->total() > 0)
                <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-[10px] text-slate-500">
                        Showing {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} of {{ $rows->total() }}
                    </p>
                    <div>{{ $rows->links() }}</div>
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
