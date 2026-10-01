@extends('layouts.app')

@section('title', 'Duty register')
@section('page-title', 'Duty register')
@section('page-subtitle', 'Daily duties for payroll — Shift recorded duties count on the monthly shift report')

@section('content')
<div class="space-y-3">
    <x-page-header
        title="Duty register"
        :subtitle="'Duties for '. \Illuminate\Support\Carbon::parse($date)->format('d M Y')"
    >
        <x-slot:actions>
            <a href="{{ route('deployments.board', ['start_date' => $date]) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">
                <x-icon name="map" class="h-3.5 w-3.5" />
                Posting board
            </a>
            <a href="{{ route('deployments.index', ['date' => $date]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                Site postings
            </a>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
        @foreach ([
            ['label' => \App\Enums\ShiftStatus::Recorded->label(), 'key' => 'recorded', 'tone' => 'text-sky-700'],
            ['label' => \App\Enums\ShiftStatus::Missed->label(), 'key' => 'missed', 'tone' => 'text-amber-800'],
            ['label' => \App\Enums\ShiftStatus::Incomplete->label(), 'key' => 'incomplete', 'tone' => 'text-violet-700'],
            ['label' => \App\Enums\ShiftStatus::Cancelled->label(), 'key' => 'cancelled', 'tone' => 'text-rose-700'],
        ] as $card)
            <div class="min-w-0 rounded-lg border border-slate-200 bg-white p-2 shadow-sm">
                <p class="truncate text-[9px] font-semibold uppercase tracking-wide {{ $card['tone'] }} sm:text-[11px]" title="{{ $card['label'] }}">{{ $card['label'] }}</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 sm:text-lg">{{ $stats[$card['key']] ?? 0 }}</p>
            </div>
        @endforeach
    </section>

    <section class="filter-bar rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <form method="GET" action="{{ route('shifts.index') }}" x-data x-ref="filterForm" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-7 xl:items-end">
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
        <x-empty-state title="No duties for this date" description="Post guards on the Site Posting Board for this duty date — posting records the shift taken." icon="calendar">
            @if ($canManage)
                <x-slot:actions>
                    <a href="{{ route('deployments.board', ['date' => $date]) }}" class="btn btn-primary">Site posting board</a>
                </x-slot:actions>
            @endif
        </x-empty-state>
    @else
        @if ($canManage)
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600 sm:px-5">
                Posting creates the duty as <strong class="font-semibold text-slate-800 dark:text-slate-200">Shift recorded</strong> (counts for payroll).
                If the guard did not finish, change the outcome — <strong class="font-semibold text-slate-800 dark:text-slate-200">Absent / No-show</strong>, <strong class="font-semibold text-slate-800 dark:text-slate-200">Incomplete</strong>, or <strong class="font-semibold text-slate-800 dark:text-slate-200">Cancelled</strong> — so it no longer pays. Do not delete historical records.
            </div>
        @endif

        @if (($overstaffedSites ?? []) !== [])
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-900 sm:px-5">
                <p class="font-semibold">Overstaffed for {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-4">
                    @foreach ($overstaffedSites as $row)
                        <li>{{ $row['site'] }} — {{ $row['period'] }}: {{ $row['deployed'] }}/{{ $row['required'] }} deployed</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-14 px-3 py-2">#</th>
                            <th class="px-3 py-2">Guard</th>
                            <th class="px-3 py-2">Site</th>
                            <th class="px-3 py-2">Time</th>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($shifts as $shift)
                            @php
                                $requiredForPeriod = $shift->period === \App\Enums\ShiftPeriod::Night
                                    ? (int) ($shift->site?->required_night_guards ?? 0)
                                    : (int) ($shift->site?->required_day_guards ?? 0);
                                if ($requiredForPeriod <= 0) {
                                    $requiredForPeriod = (int) ($shift->site?->required_guards ?? 0);
                                }
                                $deployedForPeriod = (int) (($deploymentPeriodCounts[$shift->site_id][$shift->period->value] ?? 0));
                                $isOverstaffed = $requiredForPeriod > 0 && $deployedForPeriod > $requiredForPeriod;
                            @endphp
                            <tr @class(['hover:bg-slate-50/80', 'bg-rose-50/60' => $isOverstaffed])>
                                <td class="px-3 py-2"><x-table-serial :paginator="$shifts" :index="$loop->index" /></td>
                                <td class="px-3 py-2">
                                    <a href="{{ route('shifts.show', $shift) }}" class="font-semibold text-slate-900 hover:text-brand-700">{{ $shift->assignedGuard?->full_name }}</a>
                                    <p class="text-xs text-slate-500">{{ $shift->assignedGuard?->employment_id }} · {{ $shift->reference }}</p>
                                </td>
                                <td class="px-3 py-2 text-slate-700">
                                    <p>{{ $shift->site?->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $shift->region?->name }}</p>
                                    @if ($isOverstaffed)
                                        <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-rose-700">
                                            Overstaffed {{ $deployedForPeriod }}/{{ $requiredForPeriod }} {{ $shift->period->label() }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-slate-700">
                                    <p>{{ $shift->timeLabel() }}</p>
                                    <p class="text-xs text-slate-500">{{ $shift->period->label() }}@if ($shift->is_overnight) · overnight @endif</p>
                                </td>
                                <td class="px-3 py-2"><x-status-badge :tone="$shift->shift_type->tone()" :label="$shift->shift_type->label()" /></td>
                                <td class="px-3 py-2">
                                    <x-status-badge :tone="$shift->status->tone()" :label="$shift->status->label()" />
                                    @if ($isOverstaffed)
                                        <div class="mt-1">
                                            <x-status-badge tone="rose" label="Overstaffed" />
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex flex-wrap items-center justify-end gap-2.5">
                                        <x-action-icon :href="route('shifts.show', $shift)" label="View" icon="eye" tone="brand" />
                                        @if ($canManage && $shift->status->value !== 'replaced')
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

        <x-table-pagination :paginator="$shifts" />
    @endif
</div>
@endsection
