@extends('layouts.app')

@section('title', 'Desertion details')
@section('page-title', 'Desertion details')

@section('content')
@php
    $guard = $desertion->assignedGuard;
    $statusHints = [
        'reported' => 'The guard stays Deserted and cannot be scheduled.',
        'investigating' => 'The guard stays Deserted while this case is under review.',
        'confirmed' => 'The guard stays Deserted. Scheduling still needs an authorized override.',
        'returned' => 'Ends the current deployment and returns the guard to the deployment board as awaiting deployment.',
        'closed' => 'Clears Deserted status. The guard becomes awaiting deployment, or off duty if they still have a site.',
    ];
@endphp

<div class="space-y-3">
    <x-page-header
        size="sm"
        :title="$guard?->full_name ?? 'Desertion'"
        :subtitle="trim(($guard?->employment_id ? $guard->employment_id.' · ' : '').'Reported '.$desertion->date_reported->format('d M Y'))"
        :back="route('desertions.index')"
    >
        <x-slot:actions>
            <x-status-badge :tone="$desertion->hr_status->tone()" :label="$desertion->hr_status->label()" />
            @if ($guard)
                <a href="{{ route('guards.show', $guard) }}" class="btn btn-secondary">Guard profile</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-100 bg-slate-50/80 px-3 py-2 dark:border-slate-800 dark:bg-slate-800/50">
            <h2 class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Case</h2>
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
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Operational status</dt>
                <dd class="mt-1">
                    @if ($guard)
                        <x-status-badge :tone="$guard->operational_status->tone()" :label="$guard->operational_status->label()" />
                    @else
                        <span class="text-xs text-slate-400">—</span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">HR status</dt>
                <dd class="mt-1"><x-status-badge :tone="$desertion->hr_status->tone()" :label="$desertion->hr_status->label()" /></dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Last known site</dt>
                <dd class="mt-1 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($desertion->lastKnownSite)
                        {{ $desertion->lastKnownSite->name }}
                        @if ($desertion->lastKnownSite->code)
                            <span class="mt-0.5 block text-[10px] font-normal text-slate-500">{{ $desertion->lastKnownSite->code }}</span>
                        @endif
                    @else
                        <span class="font-normal text-slate-400">Not recorded</span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Last duty date</dt>
                <dd class="mt-1 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($desertion->last_known_duty_date)
                        {{ $desertion->last_known_duty_date->format('d M Y') }}
                    @else
                        <span class="font-normal text-slate-400">Not recorded</span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Reported by</dt>
                <dd class="mt-1 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    {{ $desertion->reporter?->name ?? '—' }}
                    <span class="mt-0.5 block text-[10px] font-normal text-slate-500">{{ $desertion->date_reported->format('d M Y') }}</span>
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:col-span-2 lg:col-span-3 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Circumstances</dt>
                <dd class="mt-1 whitespace-pre-line text-xs leading-snug text-slate-700 dark:text-slate-200">{{ $desertion->circumstances ?: 'None recorded' }}</dd>
            </div>
            <div @class([
                'px-3 py-2.5 sm:col-span-2 lg:col-span-3',
                'border-b border-slate-100 dark:border-slate-800' => filled($desertion->notes) && ! $canManage,
            ])>
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Action taken</dt>
                <dd class="mt-1 whitespace-pre-line text-xs leading-snug text-slate-700 dark:text-slate-200">{{ $desertion->action_taken ?: 'None recorded' }}</dd>
            </div>
            @if (filled($desertion->notes) && ! $canManage)
                <div class="px-3 py-2.5 sm:col-span-2 lg:col-span-3">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">HR notes</dt>
                    <dd class="mt-1 whitespace-pre-line text-xs leading-snug text-slate-700 dark:text-slate-200">{{ $desertion->notes }}</dd>
                </div>
            @endif
        </dl>
    </section>

    @if ($canManage)
        <form
            method="POST"
            action="{{ route('desertions.status', $desertion) }}"
            class="form-section"
            x-data="{ status: @js($desertion->hr_status->value), hints: @js($statusHints) }"
        >
            @csrf
            <div class="form-section__header">
                <h2 class="text-xs font-semibold text-slate-900 dark:text-slate-100">Update HR status</h2>
                <p class="mt-0.5 text-[11px] leading-snug text-slate-500 dark:text-slate-400" x-text="hints[status] ?? ''"></p>
            </div>

            @error('desertion')
                <p class="form-alert form-alert--error mb-3">{{ $message }}</p>
            @enderror

            <div class="form-grid">
                <x-form-field label="Status" name="hr_status" type="select" :required="true" x-model="status">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($desertion->hr_status === $status)>{{ $status->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field
                    label="HR notes"
                    name="notes"
                    type="textarea"
                    :value="$desertion->notes"
                    placeholder="Follow-up, outcome, or who replaced this guard"
                />
            </div>

            <div class="form-actions">
                <div class="form-actions__inner">
                    <button type="submit" class="btn btn-primary">Save status</button>
                </div>
            </div>
        </form>
    @endif
</div>
@endsection
