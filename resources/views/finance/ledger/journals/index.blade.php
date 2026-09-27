@extends('layouts.app')

@section('title', 'Journals')
@section('page-title', 'Journals')

@section('content')
<div class="space-y-3">
    <x-page-header title="Journals" subtitle="Double-entry postings generated from finance operations.">
        <x-slot:actions>
            @if ($canManage ?? false)
                <a href="{{ route('ledger.journals.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">New journal</a>
            @endif
            <a href="{{ route('ledger.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Ledger hub</a>
        </x-slot:actions>
    </x-page-header>

    <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <form method="GET" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-5 xl:items-end" x-data x-ref="filterForm">
            <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" class="sm:col-span-2" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Source" name="source" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All sources</option>
                @foreach ($sources as $source)
                    <option value="{{ $source->value }}" @selected(($filters['source'] ?? '') === $source->value)>{{ $source->label() }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Status" name="status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-form-field>
            <x-filter-reset :href="route('ledger.journals.index')" />
        </form>
    </section>

    @if ($journals->isEmpty())
        <x-empty-state title="No journals yet" description="Issue an invoice, record a payment, or approve payroll to post the first journals." icon="wallet" />
    @else
        <div class="psg-stack overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <table class="min-w-full divide-y divide-slate-100 text-left text-xs dark:divide-slate-800">
                <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60">
                    <tr>
                        <th class="w-14 px-3 py-2">#</th>
                        <th class="px-3 py-2">Journal</th>
                        <th class="px-3 py-2">Period</th>
                        <th class="px-3 py-2">Source</th>
                        <th class="px-3 py-2">Amount</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($journals as $journal)
                        <tr>
                            <td class="px-3 py-2" data-label="#"><x-table-serial :paginator="$journals" :index="$loop->index" /></td>
                            <td class="px-3 py-2" data-label="Journal">
                                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ $journal->reference }}</p>
                                <p class="text-[11px] text-slate-500">{{ $journal->journal_date?->format('d M Y') }} · {{ $journal->description }}</p>
                            </td>
                            <td class="px-3 py-2" data-label="Period">{{ $journal->period?->label() }}</td>
                            <td class="px-3 py-2" data-label="Source"><x-status-badge :tone="$journal->source->tone()" :label="$journal->source->label()" /></td>
                            <td class="px-3 py-2 tabular-nums" data-label="Amount">{{ \App\Support\Money::format($journal->debitTotal()) }}</td>
                            <td class="px-3 py-2" data-label="Status"><x-status-badge :tone="$journal->status->tone()" :label="$journal->status->label()" /></td>
                            <td class="px-3 py-2 text-right" data-label="Actions">
                                <a href="{{ route('ledger.journals.show', $journal) }}" class="font-semibold text-brand-700 hover:underline">Open</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="no-print">{{ $journals->links() }}</div>
    @endif
</div>
@endsection
