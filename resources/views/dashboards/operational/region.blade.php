@extends('layouts.app')

@section('title', $region->name.' Dashboard')
@section('page-title', 'Regional dashboard')
@section('page-subtitle', $region->name)

@section('content')
<div class="space-y-3">
    <x-page-header
        :title="$region->name"
        :subtitle="'Regional operations for '.$today"
        :back="route('ops-dashboards.company')"
    >
        <x-slot:actions>
            <a href="{{ route('regions.show', $region) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Region profile</a>
            <a href="{{ route('reports.daily-shifts', ['region_id' => $region->id, 'date' => $today]) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">Today's shifts</a>
        </x-slot:actions>
    </x-page-header>

    <section class="flex flex-row gap-2 sm:gap-3">
        @foreach ([
            ['Coverage', $manpower['coverage_percent'].'%', 'text-brand-800'],
            ['Shortage', number_format($manpower['shortage']), 'text-rose-700'],
            ['Sites', number_format($kpis['sites']), 'text-slate-700'],
            ['Guards', number_format($kpis['active_guards']), 'text-emerald-700'],
            ['Shifts today', number_format($kpis['shifts_today']), 'text-sky-700'],
        ] as [$label, $value, $tone])
            <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }} sm:text-[11px]">{{ $label }}</p>
                <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-lg">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-3 py-2.5"><h2 class="text-sm font-semibold text-slate-900">Sites</h2></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-14 px-3 py-2">#</th>
                            <th class="px-3 py-2">Site</th>
                            <th class="px-3 py-2 text-right">Req</th>
                            <th class="px-3 py-2 text-right">Dep</th>
                            <th class="px-3 py-2 text-right">Gap</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($sites as $row)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-3 py-2"><x-table-serial :iteration="$loop->iteration" /></td>
                                <td class="px-3 py-2">
                                    <a href="{{ $row['href'] }}" class="font-semibold text-brand-800 hover:underline">{{ $row['site']->name }}</a>
                                    <p class="text-xs text-slate-500">{{ $row['site']->code }}</p>
                                </td>
                                <td class="px-3 py-2 text-right">{{ $row['manpower']['required'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $row['manpower']['deployed'] }}</td>
                                <td class="px-3 py-2 text-right font-medium {{ $row['manpower']['shortage'] > 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                                    {{ $row['manpower']['shortage'] > 0 ? '-'.$row['manpower']['shortage'] : $row['manpower']['surplus'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-3 py-2.5"><h2 class="text-sm font-semibold text-slate-900">Today's shifts</h2></div>
            <ul class="divide-y divide-slate-100">
                @forelse ($today_shifts as $shift)
                    <li class="flex gap-3 px-3 py-2">
                        <span class="w-6 shrink-0 tabular-nums text-sm text-slate-500">{{ $loop->iteration }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-slate-900">{{ $shift->assignedGuard?->full_name }}</p>
                            <p class="text-xs text-slate-500">{{ $shift->site?->name }} · {{ $shift->starts_at->format('H:i') }}–{{ $shift->ends_at->format('H:i') }}</p>
                        </div>
                        <x-status-badge :tone="$shift->status->tone()" :label="$shift->status->label()" />
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-slate-500">No shifts scheduled today</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
@endsection
