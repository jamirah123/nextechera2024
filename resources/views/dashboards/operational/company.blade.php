@extends('layouts.app')

@section('title', 'Company Operations Dashboard')
@section('page-title', 'Company dashboard')
@section('page-subtitle', 'Live operational overview')

@section('content')
<div class="space-y-3">
    <x-page-header
        title="Company operations"
        subtitle="Manpower, today’s shifts and regional coverage across Platinum Security Group."
    >
        <x-slot:actions>
            <a href="{{ route('manpower.coverage') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Manpower</a>
            <a href="{{ route('reports.index') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">Reports</a>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5 lg:gap-3">
        @foreach ([
            ['Coverage', $manpower['coverage_percent'].'%', 'text-brand-800'],
            ['Shortage', number_format($manpower['shortage']), 'text-rose-700'],
            ['Shifts today', number_format($kpis['shifts_today']), 'text-slate-700'],
            ['Missed today', number_format($kpis['missed_today']), 'text-amber-800'],
            ['Active guards', number_format($kpis['active_guards']), 'text-emerald-700'],
        ] as [$label, $value, $tone])
            <div class="flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                <p class="mt-0.5 text-base font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <section class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4 sm:gap-4">
        <x-kpi-card label="On duty" :value="number_format($kpis['on_duty'])" tone="emerald" />
        <x-kpi-card label="On leave" :value="number_format($kpis['on_leave'])" tone="sky" />
        <x-kpi-card label="Absent" :value="number_format($kpis['absent'])" tone="amber" />
        <x-kpi-card label="Pending leave" :value="number_format($kpis['pending_leave'])" tone="rose" />
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2.5">
                <h2 class="text-sm font-semibold text-slate-900">Regions</h2>
                <a href="{{ route('regions.index') }}" class="text-xs font-semibold text-brand-700 hover:text-brand-800">View all</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-14 px-3 py-2">#</th>
                            <th class="px-3 py-2">Region</th>
                            <th class="px-3 py-2 text-right">Sites</th>
                            <th class="px-3 py-2 text-right">Coverage</th>
                            <th class="px-3 py-2 text-right">Shortage</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($regions as $row)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-3 py-2"><x-table-serial :iteration="$loop->iteration" /></td>
                                <td class="px-3 py-2">
                                    <a href="{{ $row['href'] }}" class="font-semibold text-brand-800 hover:underline">{{ $row['region']->name }}</a>
                                    <p class="text-xs text-slate-500">{{ $row['region']->code }}</p>
                                </td>
                                <td class="px-3 py-2 text-right text-slate-700">{{ $row['sites'] }}</td>
                                <td class="px-3 py-2 text-right font-medium text-slate-900">{{ $row['manpower']['coverage_percent'] }}%</td>
                                <td class="px-3 py-2 text-right font-medium text-rose-700">{{ $row['manpower']['shortage'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">No regions configured</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2.5">
                <h2 class="text-sm font-semibold text-slate-900">Understaffed sites</h2>
                <a href="{{ route('manpower.coverage') }}" class="text-xs font-semibold text-brand-700 hover:text-brand-800">Coverage report</a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($understaffed_sites as $row)
                    <li class="flex items-center justify-between gap-3 px-3 py-2">
                        <div class="min-w-0">
                            <a href="{{ route('ops-dashboards.site', $row['site']) }}" class="font-semibold text-brand-800 hover:underline">{{ $row['site']->name }}</a>
                            <p class="text-xs text-slate-500">{{ $row['site']->region?->name }} · {{ $row['site']->client?->name }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-rose-700">-{{ $row['manpower']['shortage'] }}</p>
                            <p class="text-xs text-slate-500">{{ $row['manpower']['coverage_percent'] }}%</p>
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-slate-500">No shortages right now</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
@endsection
