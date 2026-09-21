@extends('layouts.app')

@section('title', 'Manpower Coverage')
@section('page-title', 'Manpower Coverage')
@section('page-subtitle', $dateMode ? 'Required vs deployed vs allocated for '.$coverageDate : 'Required vs deployed guards by site')

@section('content')
<div class="space-y-3">
    <x-page-header
        title="Manpower coverage"
        :subtitle="$dateMode ? 'Date coverage: required, standing deployments, and shifts allocated for '.$coverageDate : 'Required vs deployed guards by site.'"
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
            :subtitle="$dateMode ? 'Date coverage for '.$coverageDate : 'Required vs deployed guards by site.'"
        />
    <section @class([
        'grid grid-cols-2 gap-2 sm:grid-cols-3',
        'xl:grid-cols-6' => $dateMode,
        'xl:grid-cols-5' => ! $dateMode,
    ])>
        <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Required</p>
            <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ number_format($company['required']) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700">Deployed</p>
            <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ number_format($company['deployed']) }}</p>
        </div>
        @if ($dateMode)
            <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-sky-700">Allocated</p>
                <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ number_format($company['allocated']) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-rose-700">Alloc. shortage</p>
                <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ number_format($company['allocation_shortage']) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700">Deploy shortage</p>
                <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ number_format($company['shortage']) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-indigo-700">Alloc. coverage</p>
                <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ $company['allocation_coverage_percent'] }}%</p>
                <p class="text-[10px] text-slate-500">{{ $company['allocation_status']->label() }}</p>
            </div>
        @else
            <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-rose-700">Shortage</p>
                <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ number_format($company['shortage']) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700">Surplus</p>
                <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ number_format($company['surplus']) }}</p>
            </div>
            <div class="col-span-2 rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm sm:col-span-1">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-indigo-700">Coverage</p>
                <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ $company['coverage_percent'] }}%</p>
                <p class="text-[10px] text-slate-500">{{ $company['status']->label() }}</p>
            </div>
        @endif
    </section>

    @if ($dateMode && $gapSummary)
        <section class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-6">
            <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-rose-700">Original shortage</p>
                <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ number_format($gapSummary['original_shortage']) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700">OT covered</p>
                <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ number_format($gapSummary['overtime_covered']) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-rose-800">Remaining gap</p>
                <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ number_format($gapSummary['remaining_shortage']) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Open gaps</p>
                <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ number_format($gapSummary['open_gaps']) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700">OT resolved</p>
                <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ number_format($gapSummary['resolved_gaps']) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-indigo-700">Est. OT cost</p>
                <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ number_format($gapSummary['estimated_ot_cost'], 2) }}</p>
            </div>
        </section>

        @if ($gapRows->isNotEmpty())
            <section class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                <div class="mb-2 flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Manpower gaps & overtime</h2>
                        <p class="text-[11px] text-slate-500">
                            Original shortages stay on record. Overtime is temporary coverage and does not change permanent manpower.
                        </p>
                    </div>
                </div>

                <x-flash-status />
                @error('guard_id')
                    <p class="mb-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800">{{ $message }}</p>
                @enderror

                <div class="hidden overflow-x-auto lg:block">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Site</th>
                                <th>Period</th>
                                <th class="text-right">Required</th>
                                <th class="text-right">Permanent</th>
                                <th class="text-right">Original</th>
                                <th class="text-right">OT covered</th>
                                <th class="text-right">Remaining</th>
                                <th>Status</th>
                                <th class="no-print">Resolve with OT</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($gapRows as $gap)
                                <tr class="hover:bg-slate-50/80 align-top">
                                    <td>
                                        <a href="{{ route('sites.show', $gap->site) }}" class="font-semibold text-slate-900 hover:text-brand-700">
                                            {{ $gap->site?->name }}
                                        </a>
                                        <p class="text-[10px] text-slate-500">{{ $gap->reference() }}</p>
                                    </td>
                                    <td class="text-slate-700">{{ $gap->period->label() }}</td>
                                    <td class="text-right">{{ $gap->required }}</td>
                                    <td class="text-right">{{ $gap->permanent_deployed }}</td>
                                    <td class="text-right font-medium text-rose-700">{{ $gap->original_shortage }}</td>
                                    <td class="text-right text-amber-800">{{ $gap->overtime_covered }}</td>
                                    <td class="text-right font-semibold {{ $gap->remaining_shortage > 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                                        {{ $gap->remaining_shortage }}
                                    </td>
                                    <td>
                                        <x-status-badge :tone="$gap->status->tone()" :label="$gap->status->label()" />
                                        @if ($gap->overtimeDeployments->isNotEmpty())
                                            <ul class="mt-1 space-y-0.5 text-[10px] text-slate-500">
                                                @foreach ($gap->overtimeDeployments as $ot)
                                                    <li>
                                                        OT: {{ $ot->assignedGuard?->employment_id }}
                                                        {{ $ot->assignedGuard?->full_name }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </td>
                                    <td class="no-print min-w-[14rem]">
                                        @if ($canResolveGaps && $gap->isOpen())
                                            <form method="POST" action="{{ route('manpower.gaps.overtime', $gap) }}" class="flex flex-col gap-1.5">
                                                @csrf
                                                <select name="guard_id" required class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-800">
                                                    <option value="">Select guard…</option>
                                                    @foreach (($gapGuardOptions[$gap->id] ?? collect()) as $guard)
                                                        <option value="{{ $guard->id }}">
                                                            {{ $guard->employment_id }} — {{ $guard->full_name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <input
                                                    type="text"
                                                    name="notes"
                                                    maxlength="1000"
                                                    placeholder="Optional note"
                                                    class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-800"
                                                />
                                                <button type="submit" class="rounded-lg bg-amber-700 px-2.5 py-1.5 text-[11px] font-semibold text-white hover:bg-amber-800">
                                                    Assign overtime
                                                </button>
                                            </form>
                                        @elseif (! $gap->isOpen())
                                            <span class="text-[11px] text-slate-400">—</span>
                                        @else
                                            <span class="text-[11px] text-slate-400">No deploy permission</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-2 lg:hidden">
                    @foreach ($gapRows as $gap)
                        <div class="rounded-lg border border-slate-100 bg-slate-50/70 p-3">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-xs font-semibold text-slate-900">{{ $gap->site?->name }}</p>
                                    <p class="text-[10px] text-slate-500">{{ $gap->period->label() }} · {{ $gap->reference() }}</p>
                                </div>
                                <x-status-badge :tone="$gap->status->tone()" :label="$gap->status->label()" />
                            </div>
                            <dl class="mt-2 grid grid-cols-4 gap-1.5 text-center text-[10px]">
                                <div class="rounded-md bg-white px-1 py-1"><dt class="text-slate-500">Orig</dt><dd class="font-semibold text-rose-700">{{ $gap->original_shortage }}</dd></div>
                                <div class="rounded-md bg-white px-1 py-1"><dt class="text-slate-500">OT</dt><dd class="font-semibold">{{ $gap->overtime_covered }}</dd></div>
                                <div class="rounded-md bg-white px-1 py-1"><dt class="text-slate-500">Left</dt><dd class="font-semibold">{{ $gap->remaining_shortage }}</dd></div>
                                <div class="rounded-md bg-white px-1 py-1"><dt class="text-slate-500">Perm</dt><dd class="font-semibold">{{ $gap->permanent_deployed }}</dd></div>
                            </dl>
                            @if ($canResolveGaps && $gap->isOpen())
                                <form method="POST" action="{{ route('manpower.gaps.overtime', $gap) }}" class="mt-2 space-y-1.5">
                                    @csrf
                                    <select name="guard_id" required class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs">
                                        <option value="">Select guard…</option>
                                        @foreach (($gapGuardOptions[$gap->id] ?? collect()) as $guard)
                                            <option value="{{ $guard->id }}">{{ $guard->employment_id }} — {{ $guard->full_name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="w-full rounded-lg bg-amber-700 px-2.5 py-1.5 text-[11px] font-semibold text-white">Assign overtime</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endif

    <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <form
            method="GET"
            action="{{ route('manpower.coverage') }}"
            x-data
            x-ref="filterForm"
            class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5 lg:items-end"
        >
            <x-form-field label="Coverage date" name="date" type="date" :value="$filters['date'] ?? ''" help="Leave blank for standing deployment coverage." x-on:change="$refs.filterForm.requestSubmit()" />

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

    @if ($rows->isEmpty())
        <x-empty-state
            title="No sites match these filters"
            description="Try another region, status, or date to review manpower coverage."
            icon="chart"
        >
            <x-slot:actions>
                <a
                    href="{{ route('manpower.coverage.export', array_merge($exportQuery, ['format' => 'csv'])) }}"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Export empty CSV
                </a>
            </x-slot:actions>
        </x-empty-state>
    @else
        <div class="no-print flex flex-wrap items-center justify-between gap-2 rounded-lg border border-brand-100 bg-brand-50 px-3 py-2 text-xs text-brand-950">
            <p>
                <span class="font-semibold">{{ $dateMode ? 'Date coverage ready.' : 'Coverage report ready.' }}</span>
                Export the filtered site manpower summary.
            </p>
            <a
                href="{{ route('manpower.coverage.export', array_merge($exportQuery, ['format' => 'csv'])) }}"
                class="inline-flex items-center gap-1 rounded-lg bg-brand-700 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-brand-800"
            >
                Export CSV
            </a>
        </div>
        {{-- Desktop table --}}
        <div class="data-table-shell hidden lg:block">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-10">#</th>
                            <th>Site</th>
                            <th>Region</th>
                            <th class="text-right">Required</th>
                            @if ($dateMode)
                                <th class="text-right">Deployed</th>
                                <th class="text-right">Allocated</th>
                                <th class="text-right">Alloc. short</th>
                                <th class="text-right">Day alloc</th>
                                <th class="text-right">Night alloc</th>
                                <th>Alloc. status</th>
                            @else
                                <th class="text-right">Contracted</th>
                                <th class="text-right">Deployed</th>
                                <th class="text-right">Shortage</th>
                                <th class="text-right">SLA gap</th>
                                <th class="text-right">Coverage</th>
                                <th>Status</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $site = $row['site'];
                                $mp = $row['manpower'];
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
                                <td class="text-slate-600">
                                    {{ $site->region?->name ?? '—' }}
                                </td>
                                <td class="text-right font-medium text-slate-900">{{ $mp['required'] }}</td>
                                @if ($dateMode)
                                    <td class="text-right text-slate-700">{{ $mp['deployed'] }}</td>
                                    <td class="text-right text-slate-700">{{ $mp['allocated'] }}</td>
                                    <td class="text-right font-medium text-rose-700">{{ $mp['allocation_shortage'] }}</td>
                                    <td class="text-right text-slate-600">{{ $mp['allocated_day'] }}/{{ $mp['required_day'] }}</td>
                                    <td class="text-right text-slate-600">{{ $mp['allocated_night'] }}/{{ $mp['required_night'] }}</td>
                                    <td>
                                        <x-status-badge :tone="$mp['allocation_status']->tone()" :label="$mp['allocation_status']->label()" />
                                    </td>
                                @else
                                    <td class="text-right text-slate-700">{{ $mp['contracted'] > 0 ? $mp['contracted'] : '—' }}</td>
                                    <td class="text-right text-slate-700">{{ $mp['deployed'] }}</td>
                                    <td class="text-right font-medium text-rose-700">{{ $mp['shortage'] }}</td>
                                    <td class="text-right font-medium {{ ($mp['sla_shortage'] ?? 0) > 0 ? 'text-rose-700' : 'text-slate-500' }}">
                                        {{ ($mp['contracted'] ?? 0) > 0 ? $mp['sla_shortage'] : '—' }}
                                    </td>
                                    <td class="text-right font-semibold text-slate-900">{{ $mp['coverage_percent'] }}%</td>
                                    <td>
                                        <x-status-badge :tone="$mp['status']->tone()" :label="$mp['status']->label()" />
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Mobile / tablet cards --}}
        <div class="grid gap-2 lg:hidden">
            @foreach ($rows as $row)
                @php
                    $site = $row['site'];
                    $mp = $row['manpower'];
                @endphp
                <a href="{{ route('sites.show', $site) }}" class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate text-xs font-semibold text-slate-900">{{ $site->name }}</p>
                            <p class="mt-0.5 text-[10px] text-slate-500">{{ $site->code }} · {{ $site->region?->name ?? 'No region' }}</p>
                        </div>
                        <x-status-badge
                            :tone="$dateMode ? $mp['allocation_status']->tone() : $mp['status']->tone()"
                            :label="$dateMode ? $mp['allocation_status']->label() : $mp['status']->label()"
                        />
                    </div>
                    <dl class="mt-2 grid grid-cols-4 gap-1.5 text-center">
                        <div class="rounded-md bg-slate-50 px-1.5 py-1">
                            <dt class="text-[9px] font-medium uppercase text-slate-500">Req</dt>
                            <dd class="mt-0.5 text-xs font-semibold text-slate-900">{{ $mp['required'] }}</dd>
                        </div>
                        <div class="rounded-md bg-slate-50 px-1.5 py-1">
                            <dt class="text-[9px] font-medium uppercase text-slate-500">Dep</dt>
                            <dd class="mt-0.5 text-xs font-semibold text-slate-900">{{ $mp['deployed'] }}</dd>
                        </div>
                        @if ($dateMode)
                            <div class="rounded-md bg-slate-50 px-1.5 py-1">
                                <dt class="text-[9px] font-medium uppercase text-slate-500">Alloc</dt>
                                <dd class="mt-0.5 text-xs font-semibold text-slate-900">{{ $mp['allocated'] }}</dd>
                            </div>
                            <div class="rounded-md bg-slate-50 px-1.5 py-1">
                                <dt class="text-[9px] font-medium uppercase text-slate-500">Short</dt>
                                <dd class="mt-0.5 text-xs font-semibold text-rose-700">{{ $mp['allocation_shortage'] }}</dd>
                            </div>
                        @else
                            <div class="rounded-md bg-slate-50 px-1.5 py-1">
                                <dt class="text-[9px] font-medium uppercase text-slate-500">Short</dt>
                                <dd class="mt-0.5 text-xs font-semibold text-rose-700">{{ $mp['shortage'] }}</dd>
                            </div>
                            <div class="rounded-md bg-slate-50 px-1.5 py-1">
                                <dt class="text-[9px] font-medium uppercase text-slate-500">Cov</dt>
                                <dd class="mt-0.5 text-xs font-semibold text-slate-900">{{ $mp['coverage_percent'] }}%</dd>
                            </div>
                        @endif
                    </dl>
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
