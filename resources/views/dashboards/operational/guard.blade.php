@extends('layouts.app')

@section('title', $guard->full_name.' Dashboard')
@section('page-title', 'Guard dashboard')
@section('page-subtitle', $guard->employment_id)

@section('content')
<div class="space-y-3">
    <x-page-header
        :title="$guard->full_name"
        :subtitle="$guard->employment_id.($guard->currentSite ? ' · '.$guard->currentSite->name : '')"
        :back="route('ops-dashboards.company')"
    >
        <x-slot:actions>
            <a href="{{ route('guards.show', $guard) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Full profile</a>
            @if ($guard->current_site_id)
                <a href="{{ route('ops-dashboards.site', $guard->current_site_id) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">Site dashboard</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 bg-gradient-to-r from-steel-950 via-brand-950 to-brand-800 px-5 py-5 text-white sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-lg font-semibold">{{ $guard->full_name }}</p>
                <p class="mt-0.5 text-sm text-slate-300">{{ $guard->region?->name ?? 'No region' }} · {{ $guard->currentSite?->name ?? 'No site' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-status-badge :tone="$guard->employment_status->tone()" :label="$guard->employment_status->label()" />
                <x-status-badge :tone="$guard->operational_status->tone()" :label="$guard->operational_status->label()" />
            </div>
        </div>
    </section>

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3">
        @foreach ([
            ['Normal (mo)', number_format($kpis['month_normal']), 'text-brand-800'],
            ['OT (mo)', number_format($kpis['month_overtime']), 'text-amber-800'],
            ['Worked (mo)', number_format($kpis['month_total']), 'text-emerald-700'],
            ['Missed (mo)', number_format($kpis['month_missed']), 'text-rose-700'],
            ['Upcoming', number_format($kpis['upcoming']), 'text-sky-700'],
        ] as [$label, $value, $tone])
            <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }} sm:text-[11px]">{{ $label }}</p>
                <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-lg">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-3 py-2.5"><h2 class="text-sm font-semibold text-slate-900">Upcoming shifts</h2></div>
            <ul class="divide-y divide-slate-100">
                @forelse ($upcoming as $shift)
                    <li class="flex gap-3 px-3 py-2">
                        <span class="w-6 shrink-0 tabular-nums text-sm text-slate-500">{{ $loop->iteration }}</span>
                        <div>
                            <p class="font-semibold text-slate-900">{{ $shift->shift_date->format('D d M') }}</p>
                            <p class="text-xs text-slate-500">{{ $shift->site?->name }} · {{ $shift->shift_type->label() }}</p>
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-slate-500">No upcoming shifts</li>
                @endforelse
            </ul>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-3 py-2.5"><h2 class="text-sm font-semibold text-slate-900">Recent shifts</h2></div>
            <ul class="divide-y divide-slate-100">
                @forelse ($recent_shifts as $shift)
                    <li class="flex gap-3 px-3 py-2">
                        <span class="w-6 shrink-0 tabular-nums text-sm text-slate-500">{{ $loop->iteration }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-slate-900">{{ $shift->shift_date->format('d M Y') }}</p>
                            <p class="text-xs text-slate-500">{{ $shift->site?->name }}</p>
                        </div>
                        <x-status-badge :tone="$shift->status->tone()" :label="$shift->status->label()" />
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-slate-500">No shift history</li>
                @endforelse
            </ul>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-3 py-2.5"><h2 class="text-sm font-semibold text-slate-900">Deployments & leave</h2></div>
            <div class="space-y-4 px-3 py-2.5">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Deployment history</p>
                    <ul class="mt-2 space-y-2">
                        @forelse ($deployments as $deployment)
                            <li class="text-sm text-slate-700">
                                <span class="font-medium">{{ $deployment->site?->name }}</span>
                                <span class="text-xs text-slate-500"> · {{ optional($deployment->start_date)->format('d M Y') }}</span>
                            </li>
                        @empty
                            <li class="text-sm text-slate-500">No deployments</li>
                        @endforelse
                    </ul>
                </div>
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Active / pending leave</p>
                    <ul class="mt-2 space-y-2">
                        @forelse ($active_leave as $leave)
                            <li class="text-sm text-slate-700">
                                {{ $leave->leave_type->label() }}
                                <span class="text-xs text-slate-500"> · {{ $leave->start_date->format('d M') }}–{{ $leave->end_date->format('d M') }} · {{ $leave->status->label() }}</span>
                            </li>
                        @empty
                            <li class="text-sm text-slate-500">None</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
