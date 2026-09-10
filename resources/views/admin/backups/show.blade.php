@extends('layouts.app')

@section('title', 'Backup '.$backup->reference)
@section('page-title', 'Backup detail')
@section('page-subtitle', $backup->reference)

@section('content')
<div class="mx-auto w-full max-w-3xl space-y-3">
    <x-page-header
        :title="$backup->reference"
        :subtitle="$backup->filename ?: 'Catalogued backup'"
        :back="route('backups.index')"
    >
        <x-slot:actions>
            <x-status-badge :tone="$backup->status->tone()" :label="$backup->status->label()" />
        </x-slot:actions>
    </x-page-header>

    @error('backup')
        <p class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200">{{ $message }}</p>
    @enderror

    <div class="grid gap-3 sm:grid-cols-2">
        <section class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Details</p>
            <dl class="mt-2 space-y-1.5 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Type</dt><dd><x-status-badge :tone="$backup->type->tone()" :label="$backup->type->label()" /></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Driver</dt><dd class="font-medium">{{ strtoupper($backup->driver) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Size</dt><dd class="font-medium">{{ $backup->formattedSize() }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">File present</dt><dd class="font-medium">{{ $fileExists ? 'Yes' : 'Missing' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Created</dt><dd class="font-medium">{{ optional($backup->completed_at ?? $backup->created_at)->timezone(config('app.timezone'))->format('d M Y H:i') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">By</dt><dd class="font-medium">{{ $backup->creator?->name ?? 'System / scheduler' }}</dd></div>
            </dl>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Integrity & off-site</p>
            <dl class="mt-2 space-y-1.5 text-sm">
                <div>
                    <dt class="text-[10px] uppercase tracking-wide text-slate-500">SHA-256</dt>
                    <dd class="mt-0.5 break-all font-mono text-[11px] text-slate-800 dark:text-slate-200">{{ $backup->checksum_sha256 ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Verified</dt><dd class="font-medium">{{ optional($backup->verified_at)->timezone(config('app.timezone'))->format('d M Y H:i') ?: 'Not yet' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Off-site disk</dt><dd class="font-medium">{{ $backup->offsite_disk ?: 'Local only' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Off-site synced</dt><dd class="font-medium">{{ optional($backup->offsite_synced_at)->timezone(config('app.timezone'))->format('d M Y H:i') ?: '—' }}</dd></div>
            </dl>
        </section>
    </div>

    @if ($backup->error_message)
        <section class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200">
            <p class="text-[10px] font-semibold uppercase tracking-wide">Error detail</p>
            <p class="mt-1 whitespace-pre-wrap">{{ $backup->error_message }}</p>
        </section>
    @endif

    @if ($backup->notes)
        <section class="rounded-lg border border-slate-200 bg-white p-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Notes</p>
            <p class="mt-1 whitespace-pre-wrap text-slate-700 dark:text-slate-200">{{ $backup->notes }}</p>
        </section>
    @endif

    <section class="flex flex-wrap gap-2 rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        @can('download', $backup)
            <a href="{{ route('backups.download', $backup) }}" class="btn btn-secondary">Download</a>
        @endcan

        @can('verify', $backup)
            <form method="POST" action="{{ route('backups.verify', $backup) }}">
                @csrf
                <button type="submit" class="btn btn-secondary">Verify integrity</button>
            </form>
        @endcan

        @can('delete', $backup)
            <form method="POST" action="{{ route('backups.destroy', $backup) }}" onsubmit="return confirm('Dismiss this failed backup record?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">Dismiss failed record</button>
            </form>
        @endcan
    </section>

    @can('restore', $backup)
        <section class="rounded-lg border border-amber-200 bg-amber-50 p-3 shadow-sm dark:border-amber-800 dark:bg-amber-950/30">
            <h2 class="text-sm font-semibold text-amber-950 dark:text-amber-100">Controlled restore</h2>
            <p class="mt-1 text-xs text-amber-900/90 dark:text-amber-200/90">
                Restoring replaces the live database. The system will first create a <strong>safety backup</strong> of the current database and verify this file’s checksum.
            </p>
            <form method="POST" action="{{ route('backups.restore', $backup) }}" class="mt-3 space-y-2" onsubmit="return confirm('This will restore the LIVE database. Continue?')">
                @csrf
                <x-form-field
                    label="Type RESTORE to confirm"
                    name="confirmation"
                    :value="old('confirmation')"
                    :required="true"
                    help="Confirmation is case-sensitive."
                />
                <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
                <button type="submit" class="btn btn-danger">Restore database from this backup</button>
            </form>
        </section>
    @endcan
</div>
@endsection
