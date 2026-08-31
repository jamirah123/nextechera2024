@extends('layouts.app')

@section('title', 'Archived record')
@section('page-title', 'Archived record')
@section('page-subtitle', 'Deletion backup · restore when needed')

@section('content')
@php
    $displayFields = $record->displayAttributes();
    $relationLines = $record->relationSummaries();
@endphp

<div class="mx-auto w-full max-w-3xl space-y-2">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <a href="{{ route('archived.index') }}" class="inline-flex items-center gap-1 text-[10px] font-semibold text-brand-700 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300">
            <x-icon name="chevron" class="h-3 w-3 rotate-180" />
            Back to archive
        </a>
        @if ($record->isRestorable())
            <x-confirm-action
                :action="route('archived.restore', $record)"
                variant="primary"
                title="Restore archived record"
                :confirm="'This will bring back '.$record->label.' using the backed-up data captured at deletion.'"
                label="Restore record"
                confirm-label="Yes, restore"
                cancel-label="Cancel"
                button-class="inline-flex items-center rounded-lg bg-brand-700 px-2.5 py-1 text-[11px] font-semibold text-white shadow-sm hover:bg-brand-800"
            />
        @endif
    </div>

    @error('archive')
        <p class="form-alert form-alert--error text-xs">{{ $message }}</p>
    @enderror

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="border-b border-slate-100 bg-gradient-to-r from-steel-950 to-brand-800 px-3 py-2.5 text-white dark:border-slate-700 sm:px-4">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-300">{{ $record->typeLabel() }}</p>
                    <h1 class="mt-0.5 truncate text-sm font-semibold tracking-tight">{{ $record->label }}</h1>
                    <p class="mt-0.5 text-[11px] text-slate-300">Original ID {{ $record->record_id }}</p>
                </div>
                @if ($record->isRestored())
                    <x-status-badge tone="emerald" label="Restored" />
                @else
                    <x-status-badge tone="amber" label="Awaiting restore" />
                @endif
            </div>
        </div>

        <dl class="grid gap-0 sm:grid-cols-2 lg:grid-cols-3">
            <div class="border-b border-slate-100 px-3 py-1.5 sm:border-r dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Deleted</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $record->created_at->timezone(config('app.timezone'))->format('d M Y · H:i') }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-1.5 lg:border-r dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Deleted by</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $record->deleter?->name ?? 'System' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-1.5 dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Backup mode</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $record->backupModeLabel() }}</dd>
            </div>
            @if ($record->isRestored())
                <div class="border-b border-slate-100 px-3 py-1.5 sm:border-r dark:border-slate-700">
                    <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Restored</dt>
                    <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $record->restored_at?->timezone(config('app.timezone'))->format('d M Y · H:i') }}</dd>
                </div>
                <div class="border-b border-slate-100 px-3 py-1.5 sm:col-span-2 dark:border-slate-700">
                    <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Restored by</dt>
                    <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $record->restorer?->name ?? '—' }}</dd>
                </div>
            @elseif ($record->source_action)
                <div class="border-b border-slate-100 px-3 py-1.5 sm:col-span-2 lg:col-span-3 dark:border-slate-700">
                    <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Source action</dt>
                    <dd class="mt-0.5 font-mono text-[11px] text-slate-700 dark:text-slate-300">{{ $record->source_action }}</dd>
                </div>
            @endif
        </dl>
    </section>

    @if ($displayFields !== [])
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-700">
                <h2 class="text-xs font-semibold text-slate-900 dark:text-slate-100">Backed-up information</h2>
                <p class="mt-0.5 text-[10px] text-slate-500 dark:text-slate-400">Fields captured at the time of deletion.</p>
            </div>
            <dl class="grid gap-0 sm:grid-cols-2">
                @foreach ($displayFields as $label => $value)
                    <div @class([
                        'border-b border-slate-100 px-3 py-1.5 dark:border-slate-700',
                        'sm:border-r' => $loop->iteration % 2 === 1,
                    ])>
                        <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                        <dd class="mt-0.5 break-words text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endif

    @if ($relationLines !== [])
        <section class="rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-xs font-semibold text-slate-900 dark:text-slate-100">Related data</h2>
            <ul class="mt-1.5 space-y-0.5 text-xs text-slate-600 dark:text-slate-300">
                @foreach ($relationLines as $line)
                    <li class="flex items-center gap-1.5">
                        <span class="h-1 w-1 rounded-full bg-brand-500"></span>
                        {{ $line }}
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <details class="group rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <summary class="cursor-pointer list-none px-3 py-2 text-xs font-semibold text-slate-700 marker:content-none dark:text-slate-200">
            <span class="inline-flex items-center gap-1.5">
                <x-icon name="chevron" class="h-3 w-3 transition group-open:rotate-90" />
                Technical snapshot (JSON)
            </span>
        </summary>
        <div class="border-t border-slate-100 px-3 py-2 dark:border-slate-700">
            <pre class="max-h-64 overflow-auto rounded-md bg-slate-50 p-2.5 text-[10px] leading-relaxed text-slate-700 dark:bg-slate-900/50 dark:text-slate-300">{{ json_encode(['attributes' => $record->attributes, 'relations' => $record->relations], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    </details>
</div>
@endsection
