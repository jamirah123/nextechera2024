@extends('layouts.app')

@section('title', 'Backups & Recovery')
@section('page-title', 'Backups & recovery')
@section('page-subtitle', 'Critical infrastructure · data protection')

@section('content')
<div class="mx-auto w-full max-w-5xl space-y-3">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <h1 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Backups & recovery</h1>
            <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                Database dumps and private-file archives with checksums, GFS retention, optional off-site copy, restore drills, and controlled restore.
            </p>
        </div>
        <form method="POST" action="{{ route('backups.store') }}">
            @csrf
            <button type="submit" class="btn btn-primary">Create backup now</button>
        </form>
    </div>

    @error('backup')
        <p class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200">{{ $message }}</p>
    @enderror

    @if ($is_stale)
        <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
            Backup freshness warning: no successful backup within the last {{ $settings['stale_hours'] }} hours
            @if ($latest)
                (latest {{ $latest->reference }} · {{ optional($latest->completed_at)->diffForHumans() }}).
            @else
                (none catalogued yet).
            @endif
        </p>
    @endif

    <section class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Retention (GFS)</p>
            <p class="mt-0.5 text-sm font-semibold text-slate-900 dark:text-slate-100">
                {{ $settings['keep_daily'] }}d / {{ $settings['keep_weekly'] }}w / {{ $settings['keep_monthly'] }}m
            </p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Schedule</p>
            <p class="mt-0.5 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ str_replace('_', ' ', $settings['schedule']) }} + monthly</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Contents</p>
            <p class="mt-0.5 text-sm font-semibold text-slate-900 dark:text-slate-100">
                DB{{ $settings['include_files'] ? ' + private files' : ' only' }}
            </p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Off-site</p>
            <p class="mt-0.5 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $settings['offsite'] }}</p>
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-2.5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <form method="GET" action="{{ route('backups.index') }}" x-data x-ref="filterForm" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
            <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" placeholder="Reference or file" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" class="sm:col-span-2" />
            <x-form-field label="Status" name="status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Type" name="type" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All types</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-form-field>
        </form>
    </section>

    @if ($backups->isEmpty())
        <x-empty-state title="No backups yet" description="Create a manual backup or wait for the scheduled job (daily 01:30 / weekly Sunday 02:15 / monthly 1st 03:00)." icon="settings" />
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <table class="data-table text-xs">
                <thead>
                    <tr>
                        <th>Backup</th>
                        <th class="hidden sm:table-cell">Type</th>
                        <th>Status</th>
                        <th class="hidden md:table-cell">Size</th>
                        <th class="hidden lg:table-cell">Created</th>
                        <th class="text-right"> </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($backups as $backup)
                        <tr>
                            <td>
                                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ $backup->reference }}</p>
                                <p class="font-mono text-[10px] text-slate-500">
                                    {{ $backup->filename ?: '—' }}
                                    @if ($backup->includes_files)
                                        <span class="text-slate-400">+ files</span>
                                    @endif
                                </p>
                            </td>
                            <td class="hidden sm:table-cell">
                                <x-status-badge :tone="$backup->type->tone()" :label="$backup->type->label()" />
                            </td>
                            <td>
                                <x-status-badge :tone="$backup->status->tone()" :label="$backup->status->label()" />
                            </td>
                            <td class="hidden md:table-cell text-slate-600 dark:text-slate-300">{{ $backup->formattedSize() }}</td>
                            <td class="hidden lg:table-cell text-slate-600 dark:text-slate-300">
                                {{ ($backup->completed_at ?? $backup->created_at)?->timezone(config('app.timezone'))->format('d M Y H:i') ?? '—' }}
                            </td>
                            <td class="text-right">
                                <a href="{{ route('backups.show', $backup) }}" class="text-[11px] font-semibold text-brand-700 hover:underline dark:text-brand-400">Open</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="border-t border-slate-100 px-3 py-2 dark:border-slate-700">
                {{ $backups->links() }}
            </div>
        </div>
    @endif

    <p class="text-[11px] text-slate-500">
        Policy and schedule are configured under
        <a href="{{ route('settings.index') }}" class="font-semibold text-brand-700 hover:underline dark:text-brand-400">Platform Settings</a>.
        Full recovery procedure:
        <span class="font-mono text-[10px]">docs/disaster-recovery.md</span>.
        Completed backups cannot be deleted manually — retention prunes oldest files automatically.
    </p>
</div>
@endsection
