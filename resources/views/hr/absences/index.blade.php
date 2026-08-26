@extends('layouts.app')

@section('title', 'Absences')
@section('page-title', 'Absences')
@section('page-subtitle', 'Daily absence recording and follow-up')

@section('content')
<div class="space-y-6">
    <x-page-header title="Absences" subtitle="Record no-shows and operational absences. Linked shifts can be marked missed.">
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('absences.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800"><x-icon name="plus" class="h-4 w-4" /> Record absence</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="flex flex-row gap-2 sm:gap-3">
        <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-amber-800 sm:text-[11px]">Today</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ $stats['today'] }}</p>
        </div>
        <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-slate-600 sm:text-[11px]">This month</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ $stats['month'] }}</p>
        </div>
        <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-rose-700 sm:text-[11px]">Need replacement</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ $stats['replacement'] }}</p>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <form method="GET" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5 xl:items-end" x-data x-ref="filterForm">
            <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" class="sm:col-span-2" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Date" name="date" type="date" :value="$filters['date'] ?? ''" x-on:change="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Reason" name="reason" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All reasons</option>
                @foreach ($reasons as $reason)
                    <option value="{{ $reason->value }}" @selected(($filters['reason'] ?? '') === $reason->value)>{{ $reason->label() }}</option>
                @endforeach
            </x-form-field>
            <x-filter-reset :href="route('absences.index')" />
        </form>
    </section>

    @if ($absences->isEmpty())
        <x-empty-state title="No absences recorded" description="Record an absence when a guard misses duty." icon="alert" />
    @else
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="w-14 px-5 py-3">#</th>
                        <th class="px-5 py-3">Guard</th>
                        <th class="px-5 py-3">Date</th>
                        <th class="px-5 py-3">Site</th>
                        <th class="px-5 py-3">Reason</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($absences as $absence)
                        <tr>
                            <td class="px-5 py-3.5"><x-table-serial :paginator="$absences" :index="$loop->index" /></td>
                            <td class="px-5 py-3.5"><p class="font-semibold">{{ $absence->assignedGuard?->full_name }}</p><p class="text-xs text-slate-500">{{ $absence->assignedGuard?->employment_id }}</p></td>
                            <td class="px-5 py-3.5">{{ $absence->absence_date->format('d M Y') }}</td>
                            <td class="px-5 py-3.5">{{ $absence->site?->name ?? '—' }}</td>
                            <td class="px-5 py-3.5"><x-status-badge :tone="$absence->reason->tone()" :label="$absence->reason->label()" /></td>
                            <td class="px-5 py-3.5 text-right"><x-action-icon :href="route('absences.show', $absence)" label="View" icon="eye" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $absences->links() }}</div>
    @endif
</div>
@endsection
