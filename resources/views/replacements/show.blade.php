@extends('layouts.app')

@section('title', 'Replacement details')
@section('page-title', 'Replacement details')

@section('content')
<div class="space-y-3">
    <x-page-header
        title="Replacement recorded"
        :subtitle="'Authorized '.optional($replacement->replaced_at)->format('d M Y, H:i')"
        :back="route('replacements.index')"
    />

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-gradient-to-r from-steel-950 via-brand-950 to-brand-800 px-3 py-2.5 text-white sm:px-6">
            <x-status-badge :tone="$replacement->reason->tone()" :label="$replacement->reason->label()" />
            <span class="text-sm text-white/80">{{ $replacement->site?->name }}</span>
        </div>
        <dl class="grid gap-0 sm:grid-cols-2">
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Original guard</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">
                    {{ $replacement->originalGuard?->full_name }}
                    <span class="block text-xs font-normal text-slate-500">{{ $replacement->originalGuard?->employment_id }}</span>
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Replacement guard</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">
                    {{ $replacement->replacementGuard?->full_name }}
                    <span class="block text-xs font-normal text-slate-500">{{ $replacement->replacementGuard?->employment_id }}</span>
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Original shift</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">
                    @if ($replacement->originalShift)
                        <a href="{{ route('shifts.show', $replacement->originalShift) }}" class="text-brand-700 hover:text-brand-800">{{ $replacement->originalShift->reference }}</a>
                        <span class="block text-xs font-normal text-slate-500">
                            {{ $replacement->originalShift->shift_date->format('d M Y') }} · {{ $replacement->originalShift->timeLabel() }}
                            · {{ $replacement->originalShift->status->label() }}
                        </span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Replacement shift</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">
                    @if ($replacement->replacementShift)
                        <a href="{{ route('shifts.show', $replacement->replacementShift) }}" class="text-brand-700 hover:text-brand-800">{{ $replacement->replacementShift->reference }}</a>
                        <span class="block text-xs font-normal text-slate-500">
                            {{ $replacement->replacementShift->shift_type->label() }} · {{ $replacement->replacementShift->status->label() }}
                        </span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Authorized by</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $replacement->authorizer?->name ?? '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Site</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $replacement->site?->name ?? '—' }}</dd>
            </div>
            @if ($replacement->notes)
                <div class="px-3 py-2.5 sm:col-span-2 sm:px-6">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Notes</dt>
                    <dd class="mt-1 text-sm text-slate-700">{{ $replacement->notes }}</dd>
                </div>
            @endif
        </dl>
    </section>
</div>
@endsection
