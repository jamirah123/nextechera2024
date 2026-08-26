@extends('layouts.app')

@section('title', 'Leave Management')
@section('page-title', 'Leave')
@section('page-subtitle', 'Approve leave and detect schedule conflicts')

@section('content')
<div class="space-y-6">
    <x-page-header title="Leave management" subtitle="Track pending, approved and completed leave with shift conflict awareness.">
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('leaves.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">
                    <x-icon name="plus" class="h-4 w-4" /> Request leave
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="flex flex-row gap-2 sm:gap-3">
        @foreach ([['Pending','pending','text-amber-800'],['Approved','approved','text-emerald-700'],['Completed','completed','text-sky-700'],['Conflicts','conflicts','text-rose-700']] as [$label,$key,$tone])
            <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }} sm:text-[11px]">{{ $label }}</p>
                <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ $stats[$key] }}</p>
            </div>
        @endforeach
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <form method="GET" action="{{ route('leaves.index') }}" x-data x-ref="filterForm" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5 xl:items-end">
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
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-14 px-5 py-3">#</th>
                            <th class="px-5 py-3">Guard</th>
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3">Dates</th>
                            <th class="px-5 py-3">Conflicts</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($leaves as $leave)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3.5"><x-table-serial :paginator="$leaves" :index="$loop->index" /></td>
                                <td class="px-5 py-3.5">
                                    <p class="font-semibold text-slate-900">{{ $leave->assignedGuard?->full_name }}</p>
                                    <p class="text-xs text-slate-500">{{ $leave->assignedGuard?->employment_id }}</p>
                                </td>
                                <td class="px-5 py-3.5"><x-status-badge :tone="$leave->leave_type->tone()" :label="$leave->leave_type->label()" /></td>
                                <td class="px-5 py-3.5 text-slate-700">{{ $leave->start_date->format('d M Y') }} – {{ $leave->end_date->format('d M Y') }}</td>
                                <td class="px-5 py-3.5 text-slate-700">{{ $leave->conflicting_shifts_count }}</td>
                                <td class="px-5 py-3.5"><x-status-badge :tone="$leave->status->tone()" :label="$leave->status->label()" /></td>
                                <td class="px-5 py-3.5 text-right"><x-action-icon :href="route('leaves.show', $leave)" label="View" icon="eye" tone="brand" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="flex justify-between gap-3"><p class="text-xs text-slate-500">Showing {{ $leaves->firstItem() ?? 0 }}–{{ $leaves->lastItem() ?? 0 }} of {{ $leaves->total() }}</p><div>{{ $leaves->links() }}</div></div>
    @endif
</div>
@endsection
