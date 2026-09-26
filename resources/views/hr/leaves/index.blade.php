@extends('layouts.app')

@section('title', 'Leave Management')
@section('page-title', 'Leave')
@section('page-subtitle', 'Approve leave and detect schedule conflicts')

@section('content')
<div class="space-y-3">
    <x-page-header title="Leave management" subtitle="Track pending, approved and completed leave with shift conflict awareness.">
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('leaves.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">
                    <x-icon name="plus" class="h-3.5 w-3.5" /> Request leave
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
        @foreach ([
            ['Pending', number_format($stats['pending']), 'text-amber-800'],
            ['Approved', number_format($stats['approved']), 'text-emerald-700'],
            ['Completed', number_format($stats['completed']), 'text-sky-700'],
            ['Conflicts', number_format($stats['conflicts']), 'text-rose-700'],
        ] as [$label, $value, $tone])
            <div class="flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                <p class="mt-0.5 text-base font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <section class="filter-bar rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <form method="GET" action="{{ route('leaves.index') }}" x-data x-ref="filterForm" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-5 xl:items-end">
            <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" placeholder="Guard or reason" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" class="sm:col-span-2" />
            <x-form-field label="Status" name="status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Type" name="leave_type" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All types</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(($filters['leave_type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-form-field>
            <x-filter-reset :href="route('leaves.index')" />
        </form>
    </section>

    @if ($leaves->isEmpty())
        <x-empty-state title="No leave records" description="Create a leave request to begin HR tracking." icon="leave" />
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-14 px-3 py-2">#</th>
                            <th class="px-3 py-2">Guard</th>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Dates</th>
                            <th class="px-3 py-2">Conflicts</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($leaves as $leave)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-3 py-2"><x-table-serial :paginator="$leaves" :index="$loop->index" /></td>
                                <td class="px-3 py-2">
                                    <p class="font-semibold text-slate-900">{{ $leave->assignedGuard?->full_name }}</p>
                                    <p class="text-xs text-slate-500">{{ $leave->assignedGuard?->employment_id }}</p>
                                </td>
                                <td class="px-3 py-2"><x-status-badge :tone="$leave->leave_type->tone()" :label="$leave->leave_type->label()" /></td>
                                <td class="px-3 py-2 text-slate-700">{{ $leave->start_date->format('d M Y') }} – {{ $leave->end_date->format('d M Y') }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $leave->conflicting_shifts_count }}</td>
                                <td class="px-3 py-2"><x-status-badge :tone="$leave->status->tone()" :label="$leave->status->label()" /></td>
                                <td class="px-3 py-2 text-right"><x-action-icon :href="route('leaves.show', $leave)" label="View" icon="eye" tone="brand" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <x-table-pagination :paginator="$leaves" />
    @endif
</div>
@endsection
