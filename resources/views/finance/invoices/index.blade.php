@extends('layouts.app')

@section('title', 'Invoices')
@section('page-title', 'Invoices')

@section('content')
<div class="space-y-6">
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
                <a href="{{ route('invoices.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800"><x-icon name="plus" class="h-4 w-4" /> New invoice</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="report-print-area space-y-6">
        <section class="flex flex-row gap-2 sm:gap-3">
            @foreach ([['Draft','draft','text-slate-600'],['Open','open','text-sky-700'],['Overdue','overdue','text-rose-700'],['Paid','paid','text-emerald-700'],['All time','all_time','text-indigo-700']] as [$label,$key,$tone])
                <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                    <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }} sm:text-[11px]">{{ $label }}</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ $stats[$key] }}</p>
                </div>
            @endforeach
        </section>

        <section class="no-print rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <form method="GET" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6 xl:items-end" x-data x-ref="filterForm">
                @if ($scope === 'all')<input type="hidden" name="scope" value="all">@endif
                <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" class="sm:col-span-2" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
                <x-form-field label="Status" name="status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Client" name="client_id" type="select" data-searchable="true" x-on:change="$refs.filterForm.requestSubmit()">
                    <option value="">All clients</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected((string) ($filters['client_id'] ?? '') === (string) $client->id)>{{ $client->code }} — {{ $client->name }}</option>
                    @endforeach
                </x-form-field>
                <x-filter-reset :href="route('invoices.index', $scope === 'all' ? ['scope' => 'all'] : [])" />
            </form>
        </section>

        @if ($invoices->isEmpty())
            <x-empty-state title="No invoices found" description="Create a draft invoice from contracted guard billing profiles." icon="invoice" />
        @else
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-14 px-5 py-3">#</th>
                            <th class="px-5 py-3">Invoice</th>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3">Total / balance</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Created</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($invoices as $invoice)
                            <tr @class(['opacity-70' => $invoice->trashed()])>
                                <td class="px-5 py-3.5"><x-table-serial :paginator="$invoices" :index="$loop->index" /></td>
                                <td class="px-5 py-3.5">
                                    <p class="font-semibold font-mono text-slate-900">{{ $invoice->reference }}</p>
                                    <p class="text-xs text-slate-500">{{ $invoice->period_start->format('d M Y') }} – {{ $invoice->period_end->format('d M Y') }}</p>
                                </td>
                                <td class="px-5 py-3.5">{{ $invoice->client?->name }}</td>
                                <td class="px-5 py-3.5">
                                    <p class="font-semibold">{{ \App\Support\Money::format($invoice->total, $invoice->currency) }}</p>
                                    <p class="text-xs text-slate-500">Bal {{ \App\Support\Money::format($invoice->balance, $invoice->currency) }}</p>
                                </td>
                                <td class="px-5 py-3.5"><x-status-badge :tone="$invoice->status->tone()" :label="$invoice->status->label()" /></td>
                                <td class="px-5 py-3.5 text-xs text-slate-500">{{ $invoice->created_at?->format('d M Y') }}</td>
                                <td class="px-5 py-3.5 text-right"><x-action-icon :href="route('invoices.show', $invoice)" label="View" icon="eye" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="no-print">{{ $invoices->links() }}</div>
        @endif
    </div>
</div>
@endsection
