@extends('layouts.app')

@section('title', 'Absence details')
@section('page-title', 'Absence details')

@section('content')
@php
    $guard = $absence->assignedGuard;
    $isStillAbsent = $guard?->operational_status === \App\Enums\OperationalStatus::Absent;
    $standing = $guard?->operational_status;
@endphp

<div class="space-y-3">
    <x-page-header
        size="sm"
        :title="$guard?->full_name ?? 'Absence'"
        :subtitle="trim(($guard?->employment_id ? $guard->employment_id.' · ' : '').'Missed duty '.$absence->absence_date->format('d M Y'))"
        :back="route('absences.index')"
    >
        <x-slot:actions>
            <x-status-badge :tone="$absence->reason->tone()" :label="$absence->reason->label()" />
            @if ($standing)
                <x-status-badge :tone="$standing->tone()" :label="$standing->label()" />
            @endif
            @if ($guard)
                <a href="{{ route('guards.show', $guard) }}" class="btn btn-secondary">Guard profile</a>
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
                <dd class="mt-1 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($guard)
                        <a href="{{ route('guards.show', $guard) }}" class="text-brand-700 hover:text-brand-800 dark:text-brand-300">{{ $guard->full_name }}</a>
                        <span class="mt-0.5 block text-[10px] font-normal tabular-nums text-slate-500">{{ $guard->employment_id }}</span>
                    @else
                        <span class="font-normal text-slate-400">Unknown</span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Absence date</dt>
                <dd class="mt-1 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $absence->absence_date->format('d M Y') }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Reason</dt>
                <dd class="mt-1"><x-status-badge :tone="$absence->reason->tone()" :label="$absence->reason->label()" /></dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Site</dt>
                <dd class="mt-1 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($absence->site)
                        {{ $absence->site->name }}
                        @if ($absence->site->code)
                            <span class="mt-0.5 block text-[10px] font-normal text-slate-500">{{ $absence->site->code }}</span>
                        @endif
                    @else
                        <span class="font-normal text-slate-400">Not linked</span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Linked shift</dt>
                <dd class="mt-1 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($absence->shift)
                        <a href="{{ route('shifts.show', $absence->shift) }}" class="text-brand-700 hover:text-brand-800 dark:text-brand-300">{{ $absence->shift->reference ?? 'Shift #'.$absence->shift->id }}</a>
                        <span class="mt-0.5 block text-[10px] font-normal text-slate-500">
                            {{ collect([$absence->shift->period?->label(), $absence->shift->status?->label()])->filter()->implode(' · ') }}
                        </span>
                    @else
                        <span class="font-normal text-slate-400">None</span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Reported</dt>
                <dd class="mt-1 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    {{ $absence->reporter?->name ?? '—' }}
                    @if ($absence->reported_at)
                        <span class="mt-0.5 block text-[10px] font-normal text-slate-500">{{ $absence->reported_at->format('d M Y · H:i') }}</span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:col-span-2 lg:col-span-3 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Replacement</dt>
                <dd class="mt-1 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($absence->replacement_required)
                        @if ($absence->replacementGuard)
                            <a href="{{ route('guards.show', $absence->replacementGuard) }}" class="text-brand-700 hover:text-brand-800 dark:text-brand-300">{{ $absence->replacementGuard->full_name }}</a>
                            <span class="mt-0.5 block text-[10px] font-normal tabular-nums text-slate-500">{{ $absence->replacementGuard->employment_id }}</span>
                        @else
                            <span class="text-amber-700 dark:text-amber-300">Required, and nobody is assigned yet.</span>
                        @endif
                    @else
                        <span class="font-normal text-slate-500">Not required for this missed duty.</span>
                    @endif
                </dd>
            </div>
            <div @class([
                'px-3 py-2.5 sm:col-span-2 lg:col-span-3',
                'border-b border-slate-100 dark:border-slate-800' => filled($absence->notes),
            ])>
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Action taken</dt>
                <dd class="mt-1 whitespace-pre-line text-xs leading-snug text-slate-700 dark:text-slate-200">{{ $absence->action_taken ?: 'None recorded' }}</dd>
            </div>
            @if (filled($absence->notes))
                <div class="px-3 py-2.5 sm:col-span-2 lg:col-span-3">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Notes</dt>
                    <dd class="mt-1 whitespace-pre-line text-xs leading-snug text-slate-700 dark:text-slate-200">{{ $absence->notes }}</dd>
                </div>
            @endif
        </dl>
    </section>

    @if ($canManage && $isStillAbsent)
        <form method="POST" action="{{ route('absences.clear', $absence) }}" class="form-section">
            @csrf
            <div class="form-section__header">
                <h2 class="text-xs font-semibold text-slate-900 dark:text-slate-100">Restore to the deployment pool</h2>
                <p class="mt-0.5 text-[11px] leading-snug text-slate-500 dark:text-slate-400">Clears Absent. With no current site the guard becomes Available. With a site they become Off Duty.</p>
            </div>
            <x-form-field
                label="Clear note"
                name="notes"
                type="textarea"
                placeholder="Returned to site, or cleared by the supervisor"
            />
            <div class="form-actions">
                <div class="form-actions__inner">
                    <button type="submit" class="btn btn-primary">Clear absence</button>
                </div>
            </div>
        </form>
    @elseif ($guard && ! $isStillAbsent)
        <section class="rounded-lg border border-emerald-200 bg-emerald-50/70 px-3 py-3 shadow-sm dark:border-emerald-900 dark:bg-emerald-950/30">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-xs font-semibold text-emerald-950 dark:text-emerald-100">Absence closed</h2>
                <x-status-badge :tone="$standing->tone()" :label="$standing->label()" />
            </div>
            <p class="mt-1 text-[11px] leading-snug text-emerald-900 dark:text-emerald-100">
                {{ $guard->full_name }} is {{ $standing->label() }}.
                @if ($standing->isAvailableForDuty())
                    This missed duty stays on record, and they can go back on the deployment board when they meet pool rules.
                @else
                    This missed duty stays on record.
                @endif
            </p>
        </section>
    @endif
</div>
@endsection
