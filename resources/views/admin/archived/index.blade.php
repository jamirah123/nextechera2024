@extends('layouts.app')

@section('title', 'Archived Records')
@section('page-title', 'Archived records')
@section('page-subtitle', 'Deletion backups · admin restore')

@section('content')
<div class="mx-auto w-full max-w-4xl space-y-2">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <h1 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Archived records</h1>
            <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">Deleted guards, staff, users, sites and other records kept for recovery.</p>
        </div>
    </div>

    <section class="grid grid-cols-2 gap-2 sm:max-w-md">
        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400">Awaiting restore</p>
            <p class="mt-0.5 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ number_format($stats['active']) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-400">Restored</p>
            <p class="mt-0.5 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ number_format($stats['restored']) }}</p>
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-2.5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <form method="GET" action="{{ route('archived.index') }}" x-data x-ref="filterForm" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
            <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" placeholder="Name or ID" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" class="sm:col-span-2" />
            <x-form-field label="Type" name="type" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All types</option>
                @foreach ($types as $type)
                    <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ \App\Support\Archive\DeletedRecordRegistry::typeLabel($type) }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Status" name="status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="active" @selected(($filters['status'] ?? 'active') === 'active')>Awaiting restore</option>
                <option value="restored" @selected(($filters['status'] ?? '') === 'restored')>Restored</option>
                <option value="all" @selected(($filters['status'] ?? '') === 'all')>All</option>
            </x-form-field>
        </form>
    </section>

    @if ($records->isEmpty())
        <x-empty-state title="No archived records" description="When records are deleted, a backup appears here for administrator review." icon="audit" />
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <table class="data-table text-xs">
                <thead>
                    <tr>
                        <th>Record</th>
                        <th class="hidden sm:table-cell">Type</th>
                        <th class="hidden md:table-cell">Deleted</th>
                        <th>Status</th>
                        <th class="text-right"> </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($records as $record)
                        <tr>
                            <td>
                                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ $record->label }}</p>
                                <p class="text-[10px] text-slate-500 dark:text-slate-400">
                                    ID {{ $record->record_id }}
                                    @if ($record->deleter)
                                        · {{ $record->deleter->name }}
                                    @endif
                                </p>
                            </td>
                            <td class="hidden sm:table-cell text-slate-600 dark:text-slate-300">{{ $record->typeLabel() }}</td>
                            <td class="hidden md:table-cell text-slate-600 dark:text-slate-300">{{ $record->created_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                            <td>
                                @if ($record->isRestored())
                                    <x-status-badge tone="emerald" label="Restored" />
                                @else
                                    <x-status-badge tone="amber" label="Archived" />
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('archived.show', $record) }}" class="text-[11px] font-semibold text-brand-700 hover:underline dark:text-brand-400">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($records->hasPages())
            <div class="text-xs">{{ $records->links() }}</div>
        @endif
    @endif
</div>
@endsection
