@extends('layouts.app')

@section('title', $shift->reference)
@section('page-title', 'Shift details')
@section('page-subtitle', $shift->reference)

@section('content')
<div class="space-y-3">
    <x-page-header
        :title="$shift->assignedGuard?->full_name ?? 'Shift'"
        :subtitle="$shift->reference.' · '.$shift->timeLabel()"
        :back="route('shifts.index', ['date' => $shift->shift_date->toDateString()])"
    >
        <x-slot:actions>
            @if ($canManage && ! in_array($shift->status->value, ['replaced'], true))
                <a href="{{ route('shifts.edit', $shift) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Edit / correct
                </a>
            @endif
            @if ($canManage && in_array($shift->status->value, ['scheduled', 'confirmed', 'in_progress', 'missed'], true) && ! $shift->replacementRecord)
                <a href="{{ route('replacements.create', ['shift_id' => $shift->id]) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-800">
                    <x-icon name="swap" class="h-3.5 w-3.5" /> Replace
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="flex flex-wrap items-center gap-1.5 border-b border-slate-100 bg-gradient-to-r from-steel-950 via-brand-950 to-brand-800 px-3 py-1.5 text-white dark:border-slate-700">
            <x-status-badge :tone="$shift->status->tone()" :label="$shift->status->label()" />
            <x-status-badge :tone="$shift->shift_type->tone()" :label="$shift->shift_type->label()" />
            <x-status-badge :tone="$shift->period->tone()" :label="$shift->period->label()" />
        </div>
        <dl class="grid sm:grid-cols-2 lg:grid-cols-3">
            <div class="border-b border-slate-100 px-3 py-1.5 sm:border-r dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Guard</dt>
                <dd class="mt-0.5 text-[11px] font-semibold leading-tight text-slate-900 dark:text-slate-100">
                    @if ($shift->assignedGuard)
                        <a href="{{ route('guards.show', $shift->assignedGuard) }}" class="text-brand-700 hover:text-brand-800">{{ $shift->assignedGuard->full_name }}</a>
                        <span class="block text-[10px] font-normal text-slate-500">{{ $shift->assignedGuard->employment_id }}</span>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-1.5 sm:border-r dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Site</dt>
                <dd class="mt-0.5 text-[11px] font-semibold leading-tight text-slate-900 dark:text-slate-100">
                    @if ($shift->site)
                        <a href="{{ route('sites.show', $shift->site) }}" class="text-brand-700 hover:text-brand-800">{{ $shift->site->name }}</a>
                        <span class="block text-[10px] font-normal text-slate-500">{{ $shift->site->code }}</span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-1.5 dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Shift date</dt>
                <dd class="mt-0.5 text-[11px] font-semibold leading-tight text-slate-900 dark:text-slate-100">
                    {{ $shift->shift_date->format('d M Y') }}
                    <span class="block text-[10px] font-normal text-slate-500">
                        {{ $shift->starts_at->format('H:i') }} – {{ $shift->ends_at->format('d M H:i') }}
                        @if ($shift->is_overnight) · overnight @endif
                    </span>
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-1.5 sm:border-r dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Region</dt>
                <dd class="mt-0.5 text-[11px] font-semibold leading-tight text-slate-900 dark:text-slate-100">{{ $shift->region?->name ?? '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-1.5 sm:border-r dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Supervisor</dt>
                <dd class="mt-0.5 text-[11px] font-semibold leading-tight text-slate-900 dark:text-slate-100">{{ $shift->supervisor?->name ?? '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-1.5 dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Entered by</dt>
                <dd class="mt-0.5 text-[11px] font-semibold leading-tight text-slate-900 dark:text-slate-100">
                    {{ $shift->creator?->name ?? '—' }}
                    <span class="block text-[10px] font-normal text-slate-500">{{ optional($shift->created_at)->format('d M Y, H:i') }}</span>
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-1.5 sm:border-r dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Last updated by</dt>
                <dd class="mt-0.5 text-[11px] font-semibold leading-tight text-slate-900 dark:text-slate-100">
                    {{ $shift->updater?->name ?? '—' }}
                    <span class="block text-[10px] font-normal text-slate-500">{{ optional($shift->updated_at)->format('d M Y, H:i') }}</span>
                </dd>
            </div>
            @if ($shift->notes)
                <div class="px-3 py-1.5 sm:col-span-2 lg:col-span-3">
                    <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Notes</dt>
                    <dd class="mt-0.5 text-[11px] leading-snug text-slate-700 dark:text-slate-300">{{ $shift->notes }}</dd>
                </div>
            @endif
            @if ($shift->override_used)
                <div class="border-t border-amber-100 bg-amber-50 px-3 py-1.5 sm:col-span-2 lg:col-span-3 dark:border-amber-900 dark:bg-amber-950/40">
                    <dt class="text-[10px] font-medium uppercase tracking-wide text-amber-800 dark:text-amber-200">Authorized override</dt>
                    <dd class="mt-0.5 text-[11px] leading-snug text-amber-950 dark:text-amber-100">
                        {{ $shift->override_reason ?: 'No reason recorded' }}
                        <span class="block text-[10px] text-amber-800/80 dark:text-amber-200/80">
                            By {{ $shift->overrideBy?->name ?? '—' }}
                            @if ($shift->override_at) · {{ $shift->override_at->format('d M Y, H:i') }} @endif
                        </span>
                    </dd>
                </div>
            @endif
        </dl>
    </section>

    @if ($canManage && $shift->status->value !== 'replaced')
        <section class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-xs font-semibold text-slate-900 dark:text-slate-100">Update status</h2>
            <p class="mt-0.5 text-[11px] leading-snug text-slate-500 dark:text-slate-400">
                A posting creates <strong class="font-semibold text-slate-700 dark:text-slate-200">Shift recorded</strong> (payable). If the guard did not finish, change to Absent/No-show, Incomplete, or Cancelled so payroll does not count it. Do not delete the record.
            </p>
            <form method="POST" action="{{ route('shifts.status', $shift) }}" class="filter-bar mt-2 grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_auto] sm:items-end">
                @csrf
                <x-form-field label="Status" name="status" type="select" :required="true">
                    @foreach (\App\Enums\ShiftStatus::manuallySettable() as $status)
                        <option value="{{ $status->value }}" @selected($shift->status === $status)>{{ $status->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Notes" name="notes" :value="$shift->notes" />
                <button type="submit" class="inline-flex h-[1.875rem] items-center justify-center rounded-md bg-brand-700 px-3 text-[11px] font-semibold text-white hover:bg-brand-800">
                    Save status
                </button>
            </form>
        </section>
    @endif

    @if (! empty($shift->validation_snapshot))
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="border-b border-slate-100 px-3 py-1.5 dark:border-slate-700">
                <h2 class="text-xs font-semibold text-slate-900 dark:text-slate-100">Validation snapshot</h2>
                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">Issues recorded when this shift was last saved.</p>
            </div>
            <ul class="divide-y divide-slate-100 px-3 dark:divide-slate-700">
                @foreach ($shift->validation_snapshot as $issue)
                    <li class="py-1.5 text-[11px]">
                        <span @class([
                            'rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                            'bg-rose-50 text-rose-700' => ($issue['level'] ?? '') === 'critical',
                            'bg-amber-50 text-amber-800' => ($issue['level'] ?? '') === 'warning',
                        ])>{{ $issue['level'] ?? 'info' }}</span>
                        <span class="ml-2 text-slate-700">{{ $issue['message'] ?? '' }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
@endsection
