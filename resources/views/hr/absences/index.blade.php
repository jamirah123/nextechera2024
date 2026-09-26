@extends('layouts.app')

@section('title', 'Absences')
@section('page-title', 'Absences')
@section('page-subtitle', 'Daily absence recording and follow-up')

@section('content')
<div class="space-y-3">
    <x-page-header title="Absences" subtitle="Record no-shows and operational absences. Linked shifts can be marked missed.">
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('absences.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800"><x-icon name="plus" class="h-3.5 w-3.5" /> Record absence</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
        @foreach ([
            ['Today', number_format($stats['today']), 'text-amber-800'],
            ['This month', number_format($stats['month']), 'text-slate-600'],
            ['Need replacement', number_format($stats['replacement']), 'text-rose-700'],
            ['Covered', number_format($stats['covered']), 'text-emerald-700'],
        ] as [$label, $value, $tone])
            <div class="flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                <p class="mt-0.5 text-base font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <section class="filter-bar rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <form method="GET" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-5 xl:items-end" x-data x-ref="filterForm">
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
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="w-14 px-3 py-2">#</th>
                        <th class="px-3 py-2">Guard</th>
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Site</th>
                        <th class="px-3 py-2">Reason</th>
                        <th class="px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($absences as $absence)
                        <tr>
                            <td class="px-3 py-2"><x-table-serial :paginator="$absences" :index="$loop->index" /></td>
                            <td class="px-3 py-2"><p class="font-semibold">{{ $absence->assignedGuard?->full_name }}</p><p class="text-xs text-slate-500">{{ $absence->assignedGuard?->employment_id }}</p></td>
                            <td class="px-3 py-2">{{ $absence->absence_date->format('d M Y') }}</td>
                            <td class="px-3 py-2">{{ $absence->site?->name ?? '—' }}</td>
                            <td class="px-3 py-2"><x-status-badge :tone="$absence->reason->tone()" :label="$absence->reason->label()" /></td>
                            <td class="px-3 py-2 text-right"><x-action-icon :href="route('absences.show', $absence)" label="View" icon="eye" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-table-pagination :paginator="$absences" />
    @endif
</div>
@endsection
