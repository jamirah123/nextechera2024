@extends('layouts.app')

@section('title', 'Desertions')
@section('page-title', 'Desertions')
@section('page-subtitle', 'HR follow-up for deserted guards')

@section('content')
<div class="space-y-6">
    <x-page-header title="Desertions" subtitle="Deserted guards cannot be scheduled without an authorized override.">
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('desertions.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800"><x-icon name="plus" class="h-4 w-4" /> Report desertion</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="flex flex-row gap-2 sm:gap-3">
        <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-rose-700 sm:text-[11px]">Open</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ $stats['open'] }}</p>
        </div>
        <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-emerald-700 sm:text-[11px]">Returned</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ $stats['returned'] }}</p>
        </div>
        <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-slate-600 sm:text-[11px]">Closed</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ $stats['closed'] }}</p>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" class="grid gap-3 sm:grid-cols-3 xl:items-end" x-data x-ref="filterForm">
            <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
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
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="w-14 px-5 py-3">#</th>
                        <th class="px-5 py-3">Guard</th>
                        <th class="px-5 py-3">Reported</th>
                        <th class="px-5 py-3">Last site</th>
                        <th class="px-5 py-3">HR status</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($desertions as $desertion)
                        <tr>
                            <td class="px-5 py-3.5"><x-table-serial :paginator="$desertions" :index="$loop->index" /></td>
                            <td class="px-5 py-3.5"><p class="font-semibold">{{ $desertion->assignedGuard?->full_name }}</p><p class="text-xs text-slate-500">{{ $desertion->assignedGuard?->employment_id }}</p></td>
                            <td class="px-5 py-3.5">{{ $desertion->date_reported->format('d M Y') }}</td>
                            <td class="px-5 py-3.5">{{ $desertion->lastKnownSite?->name ?? '—' }}</td>
                            <td class="px-5 py-3.5"><x-status-badge :tone="$desertion->hr_status->tone()" :label="$desertion->hr_status->label()" /></td>
                            <td class="px-5 py-3.5 text-right"><x-action-icon :href="route('desertions.show', $desertion)" label="View" icon="eye" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $desertions->links() }}</div>
    @endif
</div>
@endsection
