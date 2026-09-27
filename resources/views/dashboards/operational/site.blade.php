@extends('layouts.app')

@section('title', $site->name.' Dashboard')
@section('page-title', 'Site dashboard')
@section('page-subtitle', $site->name)

@section('content')
<div class="space-y-3">
    <x-page-header
        :title="$site->name"
        :subtitle="($site->region?->name ?? 'No region').' · '.($site->client?->name ?? 'No client')"
        :back="$site->region_id ? route('ops-dashboards.region', $site->region_id) : route('ops-dashboards.company')"
    >
        <x-slot:actions>
            <a href="{{ route('sites.show', $site) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Site profile</a>
            <a href="{{ route('shifts.create', ['site_id' => $site->id]) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">Create shift</a>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3">
        @foreach ([
            ['Required', number_format($manpower['required']), 'text-brand-800'],
            ['Deployed', number_format($manpower['deployed']), 'text-emerald-700'],
            ['Remaining', number_format($manpower['shifts']['remaining'] ?? $manpower['shortage']), 'text-rose-700'],
            ['Today', number_format($kpis['shifts_today']), 'text-slate-700'],
            ['Week OT', number_format($kpis['week_overtime']), 'text-amber-800'],
        ] as [$label, $value, $tone])
            <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }} sm:text-[11px]">{{ $label }}</p>
                <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-lg">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    @if (! empty($manpower['shifts']))
        <section class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <h2 class="text-sm font-semibold text-slate-900">Shift coverage</h2>
            <p class="mt-0.5 text-xs text-slate-500">Day and night requirements vs permanent deployments (overtime shown when used to close a gap).</p>
            <x-manpower-shift-coverage class="mt-3" :coverage="$manpower['shifts']" />
        </section>
    @endif

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-3 py-2.5"><h2 class="text-sm font-semibold text-slate-900">Deployed guards</h2></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-14 px-3 py-2">#</th>
                            <th class="px-3 py-2">Guard</th>
                            <th class="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($deployments as $deployment)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-3 py-2"><x-table-serial :iteration="$loop->iteration" /></td>
                                <td class="px-3 py-2">
                                    @if ($deployment->assignedGuard)
                                        <a href="{{ route('ops-dashboards.guard', $deployment->assignedGuard) }}" class="font-semibold text-brand-800 hover:underline">{{ $deployment->assignedGuard->full_name }}</a>
                                        <p class="text-xs text-slate-500">{{ $deployment->assignedGuard->employment_id }}</p>
                                    @else
                                        <span class="text-slate-500">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    @if ($deployment->assignedGuard)
                                        <x-status-badge :tone="$deployment->assignedGuard->operational_status->tone()" :label="$deployment->assignedGuard->operational_status->label()" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-8 text-center text-sm text-slate-500">No active deployments</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-3 py-2.5"><h2 class="text-sm font-semibold text-slate-900">Today's board</h2></div>
            <ul class="divide-y divide-slate-100">
                @forelse ($today_shifts as $shift)
                    <li class="flex gap-3 px-3 py-2">
                        <span class="w-6 shrink-0 tabular-nums text-sm text-slate-500">{{ $loop->iteration }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-slate-900">{{ $shift->assignedGuard?->full_name }}</p>
                            <p class="text-xs text-slate-500">{{ $shift->period->label() }} · {{ $shift->shift_type->label() }} · {{ $shift->starts_at->format('H:i') }}</p>
                        </div>
                        <x-status-badge :tone="$shift->status->tone()" :label="$shift->status->label()" />
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-slate-500">No shifts today</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
@endsection
