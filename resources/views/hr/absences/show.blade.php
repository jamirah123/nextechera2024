@extends('layouts.app')

@section('title', 'Absence details')
@section('page-title', 'Absence details')
@section('page-subtitle', $absence->assignedGuard?->employment_id)

@section('content')
@php
    $guard = $absence->assignedGuard;
    $isStillAbsent = $guard?->operational_status === \App\Enums\OperationalStatus::Absent;
@endphp

<div class="space-y-3">
    <x-page-header
        :title="$guard?->full_name ?? 'Absence'"
        :subtitle="'Missed duty · '.$absence->absence_date->format('d M Y')"
        :back="route('absences.index')"
    >
        <x-slot:actions>
            <x-status-badge :tone="$absence->reason->tone()" :label="$absence->reason->label()" />
            @if ($guard)
                <x-status-badge :tone="$guard->operational_status->tone()" :label="$guard->operational_status->label()" />
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-100 bg-slate-50/80 px-3 py-2 dark:border-slate-800 dark:bg-slate-800/50">
            <h2 class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Record</h2>
        </div>
        <dl class="grid gap-0 sm:grid-cols-2 lg:grid-cols-3">
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Guard</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">
                    @if ($guard)
                        <a href="{{ route('guards.show', $guard) }}" class="text-brand-700 hover:text-brand-800 dark:text-brand-300">{{ $guard->full_name }}</a>
                        <span class="mt-0.5 block text-[11px] font-normal tabular-nums text-slate-500">{{ $guard->employment_id }}</span>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Absence date</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $absence->absence_date->format('d M Y') }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Reason</dt>
                <dd class="mt-1"><x-status-badge :tone="$absence->reason->tone()" :label="$absence->reason->label()" /></dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Site</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">
                    @if ($absence->site)
                        {{ $absence->site->name }}
                        @if ($absence->site->code)
                            <span class="mt-0.5 block text-[11px] font-normal text-slate-500">{{ $absence->site->code }}</span>
                        @endif
                    @else
                        <span class="font-normal text-slate-400">Not linked</span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Linked shift</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">
                    @if ($absence->shift)
                        {{ $absence->shift->reference ?? 'Shift #'.$absence->shift->id }}
                        <span class="mt-0.5 block text-[11px] font-normal text-slate-500">{{ $absence->shift->status?->label() ?? '' }}</span>
                    @else
                        <span class="font-normal text-slate-400">None</span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Reported</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">
                    {{ $absence->reporter?->name ?? '—' }}
                    @if ($absence->reported_at)
                        <span class="mt-0.5 block text-[11px] font-normal text-slate-500">{{ $absence->reported_at->format('d M Y · H:i') }}</span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r dark:border-slate-800 sm:col-span-1 lg:col-span-1">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Replacement</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">
                    @if ($absence->replacement_required)
                        @if ($absence->replacementGuard)
                            {{ $absence->replacementGuard->full_name }}
                            <span class="mt-0.5 block text-[11px] font-normal tabular-nums text-slate-500">{{ $absence->replacementGuard->employment_id }}</span>
                        @else
                            <span class="text-amber-700 dark:text-amber-300">Required — not assigned</span>
                        @endif
                    @else
                        <span class="font-normal text-slate-400">Not required</span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:col-span-1 lg:col-span-2 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Action taken</dt>
                <dd class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ $absence->action_taken ?: 'None recorded' }}</dd>
            </div>
            @if (filled($absence->notes))
                <div class="px-3 py-2.5 sm:col-span-2 lg:col-span-3">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Notes</dt>
                    <dd class="mt-1 whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ $absence->notes }}</dd>
                </div>
            @endif
        </dl>
    </section>

    @if ($canManage && $isStillAbsent)
        <section class="rounded-lg border border-amber-200 bg-amber-50/70 p-3 shadow-sm dark:border-amber-900/60 dark:bg-amber-950/30">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold text-amber-950 dark:text-amber-100">Restore to deployment pool</h2>
                    <p class="mt-0.5 text-[11px] text-amber-900/80 dark:text-amber-200/80">
                        Clears Absent status. Without a current site they return as Available; otherwise Off Duty.
                    </p>
                </div>
                <form method="POST" action="{{ route('absences.clear', $absence) }}" class="flex w-full flex-col gap-2 sm:max-w-sm sm:items-stretch">
                    @csrf
                    <label class="block text-[10px] font-semibold uppercase tracking-wide text-amber-900/70 dark:text-amber-200/70">
                        Clear note <span class="font-normal normal-case">(optional)</span>
                        <textarea
                            name="notes"
                            rows="2"
                            placeholder="e.g. Returned to site / cleared by supervisor"
                            class="mt-0.5 block w-full rounded-md border border-amber-200 bg-white px-2 py-1.5 text-[11px] text-slate-800 shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-amber-800 dark:bg-slate-900 dark:text-slate-100"
                        ></textarea>
                    </label>
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">
                        Clear absence
                    </button>
                </form>
            </div>
        </section>
    @elseif ($canManage)
        <section class="rounded-lg border border-emerald-200 bg-emerald-50/60 px-3 py-2.5 text-[11px] text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-100">
            Guard is no longer Absent
            @if ($guard)
                <span class="font-semibold">({{ $guard->operational_status->label() }})</span>
            @endif
            — available for the board once they meet pool rules.
        </section>
    @endif
</div>
@endsection
