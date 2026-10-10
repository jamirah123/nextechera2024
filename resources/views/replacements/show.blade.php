@extends('layouts.app')

@section('title', 'Replacement details')
@section('page-title', 'Replacement details')

@section('content')
@php
    $original = $replacement->originalGuard;
    $cover = $replacement->replacementGuard;
    $site = $replacement->site;
@endphp

<div class="space-y-3">
    <x-page-header
        size="sm"
        title="Replacement recorded"
        :subtitle="trim(($site?->name ? $site->name.' · ' : '').'Authorized '.optional($replacement->replaced_at)->format('d M Y, H:i'))"
        :back="route('replacements.index')"
    >
        <x-slot:actions>
            <x-status-badge :tone="$replacement->reason->tone()" :label="$replacement->reason->label()" />
        </x-slot:actions>
    </x-page-header>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-100 bg-slate-50/80 px-3 py-2 dark:border-slate-800 dark:bg-slate-800/50">
            <h2 class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Record</h2>
        </div>
        <dl class="grid gap-0 sm:grid-cols-2 lg:grid-cols-3">
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Original guard</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($original)
                        <a href="{{ route('guards.show', $original) }}" class="text-brand-700 hover:text-brand-800 dark:text-brand-300">{{ $original->full_name }}</a>
                        <span class="mt-0.5 block text-[10px] font-normal tabular-nums text-slate-500">{{ $original->employment_id }}</span>
                    @else
                        <span class="font-normal text-slate-400">Unknown</span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Replacement guard</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($cover)
                        <a href="{{ route('guards.show', $cover) }}" class="text-brand-700 hover:text-brand-800 dark:text-brand-300">{{ $cover->full_name }}</a>
                        <span class="mt-0.5 block text-[10px] font-normal tabular-nums text-slate-500">{{ $cover->employment_id }}</span>
                    @else
                        <span class="font-normal text-slate-400">Unknown</span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Reason</dt>
                <dd class="mt-0.5"><x-status-badge :tone="$replacement->reason->tone()" :label="$replacement->reason->label()" /></dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Original shift</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($replacement->originalShift)
                        <a href="{{ route('shifts.show', $replacement->originalShift) }}" class="text-brand-700 hover:text-brand-800 dark:text-brand-300">{{ $replacement->originalShift->reference }}</a>
                        <span class="mt-0.5 block text-[10px] font-normal text-slate-500">
                            {{ $replacement->originalShift->shift_date->format('d M Y') }}
                            · {{ $replacement->originalShift->timeLabel() }}
                            · {{ $replacement->originalShift->status->label() }}
                        </span>
                    @else
                        <span class="font-normal text-slate-400">Not recorded</span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Replacement shift</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($replacement->replacementShift)
                        <a href="{{ route('shifts.show', $replacement->replacementShift) }}" class="text-brand-700 hover:text-brand-800 dark:text-brand-300">{{ $replacement->replacementShift->reference }}</a>
                        <span class="mt-0.5 block text-[10px] font-normal text-slate-500">
                            {{ $replacement->replacementShift->shift_type->label() }} · {{ $replacement->replacementShift->status->label() }}
                        </span>
                    @else
                        <span class="font-normal text-slate-400">Not recorded</span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Authorized by</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    {{ $replacement->authorizer?->name ?? '—' }}
                    @if ($replacement->replaced_at)
                        <span class="mt-0.5 block text-[10px] font-normal text-slate-500">{{ $replacement->replaced_at->format('d M Y · H:i') }}</span>
                    @endif
                </dd>
            </div>
            <div @class([
                'px-3 py-2 sm:col-span-2 lg:col-span-3',
                'border-b border-slate-100 dark:border-slate-800' => filled($replacement->notes),
            ])>
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Site</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($site)
                        {{ $site->name }}
                        @if ($site->code)
                            <span class="mt-0.5 block text-[10px] font-normal text-slate-500">{{ $site->code }}</span>
                        @endif
                    @else
                        <span class="font-normal text-slate-400">Not recorded</span>
                    @endif
                </dd>
            </div>
            @if (filled($replacement->notes))
                <div class="px-3 py-2 sm:col-span-2 lg:col-span-3">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Notes</dt>
                    <dd class="mt-0.5 whitespace-pre-line text-xs leading-snug text-slate-700 dark:text-slate-200">{{ $replacement->notes }}</dd>
                </div>
            @endif
        </dl>
    </section>
</div>
@endsection
