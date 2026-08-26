@extends('layouts.app')

@section('title', $shift->reference)
@section('page-title', 'Shift details')
@section('page-subtitle', $shift->reference)

@section('content')
<div class="space-y-6">
    <x-page-header
        :title="$shift->assignedGuard?->full_name ?? 'Shift'"
        :subtitle="$shift->reference.' · '.$shift->timeLabel()"
        :back="route('shifts.index', ['date' => $shift->shift_date->toDateString()])"
    >
        <x-slot:actions>
            @if ($canManage && ! in_array($shift->status->value, ['cancelled', 'completed', 'replaced'], true))
                <a href="{{ route('shifts.edit', $shift) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Edit
                </a>
            @endif
            @if ($canManage && in_array($shift->status->value, ['scheduled', 'confirmed', 'in_progress', 'missed'], true) && ! $shift->replacementRecord)
                <a href="{{ route('replacements.create', ['shift_id' => $shift->id]) }}" class="inline-flex items-center gap-2 rounded-xl bg-indigo-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-800">
                    <x-icon name="swap" class="h-4 w-4" /> Replace
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-gradient-to-r from-steel-950 via-brand-950 to-brand-800 px-5 py-4 text-white sm:px-6">
            <x-status-badge :tone="$shift->status->tone()" :label="$shift->status->label()" />
            <x-status-badge :tone="$shift->shift_type->tone()" :label="$shift->shift_type->label()" />
            <x-status-badge :tone="$shift->guard_classification->tone()" :label="$shift->guard_classification->label()" />
            <x-status-badge :tone="$shift->period->tone()" :label="$shift->period->label()" />
        </div>
        <dl class="grid gap-0 sm:grid-cols-2 lg:grid-cols-3">
            <div class="border-b border-slate-100 px-5 py-4 sm:border-r sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Guard</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">
                    @if ($shift->assignedGuard)
                        <a href="{{ route('guards.show', $shift->assignedGuard) }}" class="text-brand-700 hover:text-brand-800">{{ $shift->assignedGuard->full_name }}</a>
                        <span class="block text-xs font-normal text-slate-500">{{ $shift->assignedGuard->employment_id }}</span>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-5 py-4 sm:border-r lg:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Site</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">
                    @if ($shift->site)
                        <a href="{{ route('sites.show', $shift->site) }}" class="text-brand-700 hover:text-brand-800">{{ $shift->site->name }}</a>
                        <span class="block text-xs font-normal text-slate-500">{{ $shift->site->code }}</span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Window</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">
                    {{ $shift->shift_date->format('d M Y') }}
                    <span class="block text-xs font-normal text-slate-500">
                        {{ $shift->starts_at->format('H:i') }} – {{ $shift->ends_at->format('d M H:i') }}
                        @if ($shift->is_overnight) · overnight @endif
                    </span>
                </dd>
            </div>
            <div class="border-b border-slate-100 px-5 py-4 sm:border-r sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Region</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $shift->region?->name ?? '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-5 py-4 sm:border-r lg:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Supervisor</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $shift->supervisor?->name ?? '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Created by</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">
                    {{ $shift->creator?->name ?? '—' }}
                    <span class="block text-xs font-normal text-slate-500">{{ optional($shift->created_at)->format('d M Y, H:i') }}</span>
                </dd>
            </div>
            @if ($shift->notes)
                <div class="px-5 py-4 sm:col-span-2 lg:col-span-3 sm:px-6">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Notes</dt>
                    <dd class="mt-1 text-sm text-slate-700">{{ $shift->notes }}</dd>
                </div>
            @endif
            @if ($shift->override_used)
                <div class="border-t border-amber-100 bg-amber-50 px-5 py-4 sm:col-span-2 lg:col-span-3 sm:px-6">
                    <dt class="text-xs font-medium uppercase tracking-wide text-amber-800">Authorized override</dt>
                    <dd class="mt-1 text-sm text-amber-950">
                        {{ $shift->override_reason ?: 'No reason recorded' }}
                        <span class="block text-xs text-amber-800/80">
                            By {{ $shift->overrideBy?->name ?? '—' }}
                            @if ($shift->override_at) · {{ $shift->override_at->format('d M Y, H:i') }} @endif
                        </span>
                    </dd>
                </div>
            @endif
        </dl>
    </section>

    @if ($canManage && ! in_array($shift->status->value, ['cancelled', 'replaced'], true))
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-semibold text-slate-900">Update status</h2>
            <p class="mt-1 text-sm text-slate-500">Move the shift through the operational lifecycle.</p>
            <form method="POST" action="{{ route('shifts.status', $shift) }}" class="mt-4 grid gap-4 sm:grid-cols-3 sm:items-end">
                @csrf
                <x-form-field label="Status" name="status" type="select" :required="true" class="sm:col-span-1">
                    @foreach (\App\Enums\ShiftStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($shift->status === $status)>{{ $status->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Notes" name="notes" :value="$shift->notes" class="sm:col-span-1" />
                <button type="submit" class="inline-flex justify-center rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">
                    Save status
                </button>
            </form>
        </section>
    @endif

    @if (! empty($shift->validation_snapshot))
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <h2 class="text-base font-semibold text-slate-900">Validation snapshot</h2>
                <p class="mt-0.5 text-sm text-slate-500">Issues recorded when this shift was last saved.</p>
            </div>
            <ul class="divide-y divide-slate-100 px-5 py-2 sm:px-6">
                @foreach ($shift->validation_snapshot as $issue)
                    <li class="py-3 text-sm">
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
