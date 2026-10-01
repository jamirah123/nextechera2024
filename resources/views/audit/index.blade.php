@extends('layouts.app')

@section('title', 'Audit Logs')
@section('page-title', 'Audit logs')
@section('page-subtitle', 'Immutable trail of critical system actions')

@section('content')
<div class="space-y-3">
    <x-page-header
        title="Audit logs"
        subtitle="Append-only security and operations trail. Overrides are highlighted."
    >
        <x-slot:actions>
            <x-report-actions
                :csv="route('audit.export', array_merge($exportQuery, ['format' => 'csv']))"
            />
        </x-slot:actions>
    </x-page-header>

    <div class="report-print-area space-y-3">
        <x-print.report-header title="Audit logs" subtitle="Append-only security and operations trail." />
        <section class="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
            @foreach ([['Total','total','text-slate-700'],['Today','today','text-brand-800'],['Overrides','overrides','text-rose-700'],['Critical','critical','text-amber-800']] as [$label,$key,$tone])
                <div class="flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                    <p class="mt-0.5 text-base font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ number_format($stats[$key]) }}</p>
                </div>
            @endforeach
        </section>

        <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
            <form method="GET" action="{{ route('audit.index') }}" x-data x-ref="filterForm" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-6 xl:items-end">
                <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" placeholder="Action, actor, summary" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" class="sm:col-span-2" />
                <x-form-field label="Category" name="category" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                    <option value="">All</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->value }}" @selected(($filters['category'] ?? '') === $category->value)>{{ $category->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Severity" name="severity" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                    <option value="">All</option>
                    @foreach ($severities as $severity)
                        <option value="{{ $severity->value }}" @selected(($filters['severity'] ?? '') === $severity->value)>{{ $severity->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Overrides" name="overrides" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                    <option value="0" @selected(! request()->boolean('overrides'))>All events</option>
                    <option value="1" @selected(request()->boolean('overrides'))>Overrides only</option>
                </x-form-field>
                <x-filter-reset :href="route('audit.index')" />
            </form>
        </section>

        @if ($logs->isEmpty())
            <x-empty-state title="No audit events" description="Critical actions will appear here as the system is used." icon="audit" />
        @else
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="w-14 px-3 py-2">#</th>
                                <th class="px-3 py-2">When</th>
                                <th class="px-3 py-2">Event</th>
                                <th class="px-3 py-2">Actor</th>
                                <th class="px-3 py-2">Category</th>
                                <th class="px-3 py-2">Severity</th>
                                <th class="px-3 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($logs as $log)
                                <tr @class(['hover:bg-slate-50/80', 'bg-rose-50/40' => $log->is_override])>
                                    <td class="px-3 py-2"><x-table-serial :paginator="$logs" :index="$loop->index" /></td>
                                    <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ $log->occurredAtLabel('d M Y, H:i:s') }}</td>
                                    <td class="px-3 py-2">
                                        <p class="font-semibold text-slate-900">{{ $log->summary }}</p>
                                        <p class="text-xs text-slate-500">{{ $log->action }}@if($log->is_override) · Override @endif</p>
                                    </td>
                                    <td class="px-3 py-2 text-slate-700">
                                        <p>{{ $log->actor_name ?? 'System' }}</p>
                                        <p class="text-xs text-slate-500">{{ $log->actor_role ?? '—' }}</p>
                                    </td>
                                    <td class="px-3 py-2"><x-status-badge :tone="$log->category->tone()" :label="$log->category->label()" /></td>
                                    <td class="px-3 py-2"><x-status-badge :tone="$log->severity->tone()" :label="$log->severity->label()" /></td>
                                    <td class="px-3 py-2 text-right"><x-action-icon :href="route('audit.show', $log)" label="View" icon="eye" tone="brand" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <x-table-pagination :paginator="$logs" />
        @endif
    </div>
</div>
@endsection
