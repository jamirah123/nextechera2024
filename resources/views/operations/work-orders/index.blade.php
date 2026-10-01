@extends('layouts.app')

@section('title', 'Work Orders')
@section('page-title', 'Work orders')
@section('page-subtitle', 'Assignable tasks from alerts and manual follow-ups')

@section('content')
<div class="space-y-3">
    <x-page-header title="Work orders" subtitle="Track staffing gaps, contract renewals, desertion follow-ups and other actionable tasks.">
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('work-orders.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">
                    <x-icon name="plus" class="h-3.5 w-3.5" />
                    New task
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
        @foreach ([
            ['Open', number_format($stats['open']), 'text-brand-800'],
            ['Assigned to me', number_format($stats['mine']), 'text-indigo-700'],
            ['Overdue', number_format($stats['overdue']), 'text-rose-700'],
            ['Due this week', number_format($stats['due_week']), 'text-amber-800'],
        ] as [$label, $value, $tone])
            <div class="flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                <p class="mt-0.5 text-base font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <section class="filter-bar rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <form method="GET" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-6 xl:items-end" x-data x-ref="filterForm">
            <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" class="sm:col-span-2" placeholder="Reference, title, assignee" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Status" name="status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Category" name="category" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->value }}" @selected(($filters['category'] ?? '') === $category->value)>{{ $category->label() }}</option>
                @endforeach
            </x-form-field>
            @if ($canManage)
                <x-form-field label="Assignee" name="assigned_to" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                    <option value="">Anyone</option>
                    @foreach ($assignees as $assignee)
                        <option value="{{ $assignee->id }}" @selected((string) ($filters['assigned_to'] ?? '') === (string) $assignee->id)>{{ $assignee->name }}</option>
                    @endforeach
                </x-form-field>
            @endif
            <div class="flex flex-wrap items-end gap-2">
                <label class="form-checkbox">
                    <input type="hidden" name="mine" value="0">
                    <input type="checkbox" name="mine" value="1" class="form-checkbox__input" @checked($filters['mine'] ?? false) x-on:change="$refs.filterForm.requestSubmit()">
                    <span class="form-checkbox__content"><span class="form-checkbox__label">Mine only</span></span>
                </label>
                <label class="form-checkbox">
                    <input type="hidden" name="overdue" value="0">
                    <input type="checkbox" name="overdue" value="1" class="form-checkbox__input" @checked($filters['overdue'] ?? false) x-on:change="$refs.filterForm.requestSubmit()">
                    <span class="form-checkbox__content"><span class="form-checkbox__label">Overdue</span></span>
                </label>
            </div>
            <x-filter-reset :href="route('work-orders.index')" />
        </form>
    </section>

    @if ($workOrders->isEmpty())
        <x-empty-state title="No work orders" description="Tasks are created automatically from proactive alerts, or you can add one manually." icon="report">
            @if ($canManage)
                <x-slot:actions>
                    <a href="{{ route('work-orders.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">New task</a>
                </x-slot:actions>
            @endif
        </x-empty-state>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <table class="min-w-full divide-y divide-slate-100 text-left text-xs dark:divide-slate-800">
                <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60">
                    <tr>
                        <th class="w-14 px-3 py-2">#</th>
                        <th class="px-3 py-2">Task</th>
                        <th class="px-3 py-2">Category</th>
                        <th class="px-3 py-2">Assignee</th>
                        <th class="px-3 py-2">Due</th>
                        <th class="px-3 py-2">Priority</th>
                        <th class="px-3 py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($workOrders as $workOrder)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                            <td class="px-3 py-2"><x-table-serial :paginator="$workOrders" :index="$loop->index" /></td>
                            <td class="px-3 py-2">
                                <a href="{{ route('work-orders.show', $workOrder) }}" class="font-semibold text-brand-800 hover:underline dark:text-brand-300">{{ $workOrder->title }}</a>
                                <p class="text-[10px] text-slate-500">{{ $workOrder->reference }}</p>
                            </td>
                            <td class="px-3 py-2"><x-status-badge :tone="$workOrder->category->tone()" :label="$workOrder->category->label()" /></td>
                            <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ $workOrder->assignee?->name ?? 'Unassigned' }}</td>
                            <td class="px-3 py-2 @if($workOrder->isOverdue()) text-rose-700 font-semibold @else text-slate-600 dark:text-slate-400 @endif">
                                {{ $workOrder->due_at?->format('d M Y') ?? '—' }}
                            </td>
                            <td class="px-3 py-2"><x-status-badge :tone="$workOrder->priority->tone()" :label="$workOrder->priority->label()" /></td>
                            <td class="px-3 py-2"><x-status-badge :tone="$workOrder->status->tone()" :label="$workOrder->status->label()" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-table-pagination :paginator="$workOrders" />
    @endif
</div>
@endsection
