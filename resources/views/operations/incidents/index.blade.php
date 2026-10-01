@extends('layouts.app')

@section('title', 'Occurrence Book')
@section('page-title', 'Occurrence Book')
@section('page-subtitle', 'Daily site incident & occurrence log')

@section('content')
<div class="space-y-3">
    <x-page-header title="Occurrence book" subtitle="Log theft, trespass, fire, medical and other site incidents. Export daily reports for clients.">
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('incidents.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">
                    <x-icon name="plus" class="h-3.5 w-3.5" /> Log occurrence
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
        @foreach ([
            ['Today', number_format($stats['today']), 'text-sky-800'],
            ['This month', number_format($stats['month']), 'text-brand-700'],
            ['Open follow-ups', number_format($stats['open']), 'text-amber-800'],
            ['Critical (7 days)', number_format($stats['critical']), 'text-rose-700'],
        ] as [$label, $value, $tone])
            <div class="flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                <p class="mt-0.5 text-base font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <section class="filter-bar rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <form method="GET" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-6 xl:items-end" x-data x-ref="filterForm">
            <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" class="sm:col-span-2" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Site" name="site_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All sites</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}" @selected(($filters['site_id'] ?? '') == $site->id)>{{ $site->code }} — {{ $site->name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Date" name="date" type="date" :value="$filters['date'] ?? ''" x-on:change="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Type" name="incident_type" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All types</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(($filters['incident_type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Status" name="status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-form-field>
            <x-filter-reset :href="route('incidents.index')" />
        </form>
    </section>

    <div class="no-print flex flex-wrap items-center gap-2">
        <x-report-actions :csv="route('incidents.export', $exportQuery)" />
        @if ($filters['site_id'] ?? null)
            <a
                href="{{ route('incidents.export-daily', ['site_id' => $filters['site_id'], 'date' => $filters['date'] ?? now()->toDateString()]) }}"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
            >
                <x-icon name="download" class="h-3.5 w-3.5" />
                Daily PDF (client)
            </a>
        @endif
    </div>

    @if ($incidents->isEmpty())
        <x-empty-state title="No occurrences logged" description="Record incidents, disturbances, and daily site events here." icon="report" />
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="w-14 px-3 py-2">#</th>
                        <th class="px-3 py-2">Reference</th>
                        <th class="px-3 py-2">Site</th>
                        <th class="px-3 py-2">Occurred</th>
                        <th class="px-3 py-2">Type</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($incidents as $incident)
                        <tr>
                            <td class="px-3 py-2"><x-table-serial :paginator="$incidents" :index="$loop->index" /></td>
                            <td class="px-3 py-2">
                                <p class="font-semibold text-slate-900">{{ $incident->reference }}</p>
                                <p class="text-[11px] text-slate-500">{{ Str::limit($incident->title, 40) }}</p>
                            </td>
                            <td class="px-3 py-2">{{ $incident->site?->name }}</td>
                            <td class="px-3 py-2">{{ $incident->occurred_at->format('d M Y H:i') }}</td>
                            <td class="px-3 py-2"><x-status-badge :tone="$incident->incident_type->tone()" :label="$incident->incident_type->label()" /></td>
                            <td class="px-3 py-2"><x-status-badge :tone="$incident->status->tone()" :label="$incident->status->label()" /></td>
                            <td class="px-3 py-2 text-right"><x-action-icon :href="route('incidents.show', $incident)" label="View" icon="eye" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-table-pagination :paginator="$incidents" />
    @endif
</div>
@endsection
