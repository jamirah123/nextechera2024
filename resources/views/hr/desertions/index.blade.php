@extends('layouts.app')

@section('title', 'Desertions')
@section('page-title', 'Desertions')
@section('page-subtitle', 'HR follow-up for deserted guards')

@section('content')
<div class="space-y-3">
    <x-page-header title="Desertions" subtitle="Deserted guards cannot be scheduled without an authorized override.">
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('desertions.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-800"><x-icon name="plus" class="h-3.5 w-3.5" /> Report desertion</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="flex flex-row gap-2 sm:gap-3">
        <div class="min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-rose-700">Open</p>
            <p class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $stats['open'] }}</p>
        </div>
        <div class="min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-emerald-700">Returned</p>
            <p class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $stats['returned'] }}</p>
        </div>
        <div class="min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-slate-600">Closed</p>
            <p class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $stats['closed'] }}</p>
        </div>
    </section>

    <section class="filter-bar rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <form method="GET" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4 xl:items-end" x-data x-ref="filterForm">
            <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Date reported" name="date" type="date" :value="$filters['date'] ?? ''" x-on:change="$refs.filterForm.requestSubmit()" />
            <x-form-field label="HR status" name="hr_status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['hr_status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-form-field>
            <x-filter-reset :href="route('desertions.index')" />
        </form>
    </section>

    @if ($desertions->isEmpty())
        <x-empty-state title="No desertion cases" description="Report a desertion to lock scheduling and start HR follow-up." icon="warning" />
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-xs dark:divide-slate-800">
                    <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                        <tr>
                            <th class="w-10 px-2.5 py-1.5">#</th>
                            <th class="px-2.5 py-1.5">Guard</th>
                            <th class="px-2.5 py-1.5">Reported</th>
                            <th class="px-2.5 py-1.5">Last site</th>
                            <th class="px-2.5 py-1.5">HR status</th>
                            <th class="px-2.5 py-1.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($desertions as $desertion)
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                                <td class="px-2.5 py-1.5 tabular-nums text-slate-500"><x-table-serial :paginator="$desertions" :index="$loop->index" /></td>
                                <td class="px-2.5 py-1.5">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ $desertion->assignedGuard?->full_name }}</p>
                                    <p class="text-[10px] leading-tight text-slate-500">{{ $desertion->assignedGuard?->employment_id }}</p>
                                </td>
                                <td class="whitespace-nowrap px-2.5 py-1.5 text-slate-600 dark:text-slate-300">{{ $desertion->date_reported->format('d M Y') }}</td>
                                <td class="px-2.5 py-1.5 text-slate-700 dark:text-slate-300">{{ $desertion->lastKnownSite?->name ?? '—' }}</td>
                                <td class="px-2.5 py-1.5"><x-status-badge :tone="$desertion->hr_status->tone()" :label="$desertion->hr_status->label()" /></td>
                                <td class="px-2.5 py-1.5 text-right">
                                    <x-action-icon :href="route('desertions.show', $desertion)" label="View" icon="eye" class="!h-6 !w-6" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div>{{ $desertions->links() }}</div>
    @endif
</div>
@endsection
