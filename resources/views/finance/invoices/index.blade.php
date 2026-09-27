@extends('layouts.app')

@section('title', 'Invoices')
@section('page-title', 'Invoices')

@section('content')
<div class="space-y-3">
    <x-page-header title="Invoices" subtitle="Draft, issue, collect and retain full invoice history.">
        <x-slot:actions>
            <x-finance.scope-tabs
                :current-url="route('invoices.index', request()->except('scope', 'page'))"
                :all-url="route('invoices.index', array_merge(request()->except('page'), ['scope' => 'all']))"
                :scope="$scope"
                current-label="Open"
                all-label="All history"
            />
            <x-report-actions :csv="route('invoices.export', $exportQuery)" />
            @if ($canManage)
                <a href="{{ route('invoices.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800"><x-icon name="plus" class="h-3.5 w-3.5" /> New invoice</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="report-print-area space-y-3">
        <x-print.report-header title="Invoices register" subtitle="Draft, issued, overdue and paid invoice summary." />
        <section class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5 lg:gap-3">
            @foreach ([
                ['Draft', number_format($stats['draft']), 'text-slate-600'],
                ['Open', number_format($stats['open']), 'text-sky-700'],
                ['Overdue', number_format($stats['overdue']), 'text-rose-700'],
                ['Paid', number_format($stats['paid']), 'text-emerald-700'],
                ['All time', number_format($stats['all_time']), 'text-indigo-700'],
            ] as [$label, $value, $tone])
                <div class="flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                    <p class="mt-0.5 text-base font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $value }}</p>
                </div>
            @endforeach
        </section>

        <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
            <form method="GET" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-6 xl:items-end" x-data x-ref="filterForm">
                @if ($scope === 'all')<input type="hidden" name="scope" value="all">@endif
                <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" class="sm:col-span-2" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
                <x-form-field label="Status" name="status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Client" name="client_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                    <option value="">All clients</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected((string) ($filters['client_id'] ?? '') === (string) $client->id)>{{ $client->name }}</option>
                    @endforeach
                </x-form-field>
                <x-filter-reset :href="route('invoices.index', $scope === 'all' ? ['scope' => 'all'] : [])" />
            </form>
        </section>

        @if ($invoices->isEmpty())
            <x-empty-state title="No invoices found" description="Create a draft invoice from contracted guard billing profiles." icon="invoice" />
        @else
            <div class="grid gap-2 sm:grid-cols-2 lg:hidden print:hidden">
                @foreach ($invoices as $invoice)
                    <article @class([
                        'rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900',
                        'opacity-70' => $invoice->trashed(),
                    ])>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('invoices.show', $invoice) }}" class="font-mono text-xs font-semibold text-slate-900 hover:text-brand-700 dark:text-slate-100">{{ $invoice->reference }}</a>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $invoice->period_start->format('d M Y') }} – {{ $invoice->period_end->format('d M Y') }}</p>
                            </div>
                            <x-status-badge :tone="$invoice->status->tone()" :label="$invoice->status->label()" />
                        </div>
                        <p class="mt-2 text-xs font-medium text-slate-800 dark:text-slate-200">{{ $invoice->client?->name }}</p>
                        <div class="mt-3 flex items-end justify-between gap-3 border-t border-slate-100 pt-3 dark:border-slate-800">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-slate-900 dark:text-slate-100">{{ \App\Support\Money::format($invoice->total, $invoice->currency) }}</p>
                                <p class="text-xs text-slate-500">Bal {{ \App\Support\Money::format($invoice->balance, $invoice->currency) }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <p class="text-xs text-slate-500">{{ $invoice->created_at?->format('d M Y') }}</p>
                                <x-action-icon :href="route('invoices.show', $invoice)" label="View" icon="eye" />
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="hidden lg:block print:block">
                <div class="data-table-shell">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="w-14">#</th>
                                <th>Invoice</th>
                                <th>Client</th>
                                <th>Total / balance</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoices as $invoice)
                                <tr @class(['opacity-70' => $invoice->trashed()])>
                                    <td class="whitespace-nowrap"><x-table-serial :paginator="$invoices" :index="$loop->index" /></td>
                                    <td class="whitespace-nowrap">
                                        <p class="font-semibold font-mono text-slate-900 dark:text-slate-100">{{ $invoice->reference }}</p>
                                        <p class="text-xs text-slate-500">{{ $invoice->period_start->format('d M Y') }} – {{ $invoice->period_end->format('d M Y') }}</p>
                                    </td>
                                    <td class="whitespace-nowrap">{{ $invoice->client?->name }}</td>
                                    <td class="whitespace-nowrap">
                                        <p class="font-semibold">{{ \App\Support\Money::format($invoice->total, $invoice->currency) }}</p>
                                        <p class="text-xs text-slate-500">Bal {{ \App\Support\Money::format($invoice->balance, $invoice->currency) }}</p>
                                    </td>
                                    <td class="whitespace-nowrap"><x-status-badge :tone="$invoice->status->tone()" :label="$invoice->status->label()" /></td>
                                    <td class="whitespace-nowrap text-xs text-slate-500">{{ $invoice->created_at?->format('d M Y') }}</td>
                                    <td class="whitespace-nowrap text-right"><x-action-icon :href="route('invoices.show', $invoice)" label="View" icon="eye" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <x-table-pagination :paginator="$invoices" />
        @endif
    </div>
</div>
@endsection
