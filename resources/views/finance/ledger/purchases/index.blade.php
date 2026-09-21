@extends('layouts.app')

@section('title', 'Purchases')
@section('page-title', 'Purchases')

@section('content')
<div class="space-y-3">
    <x-page-header title="Purchase invoices" subtitle="Supplier bills post expense, input VAT and accounts payable.">
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('ledger.purchases.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">New purchase</a>
            @endif
            @can('viewFinance')
                <a href="{{ route('ledger.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Ledger hub</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <form method="GET" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4 xl:items-end" x-data x-ref="filterForm">
            <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" class="sm:col-span-2" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Status" name="status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-form-field>
            <x-filter-reset :href="route('ledger.purchases.index')" />
        </form>
    </section>

    @if ($bills->isEmpty())
        <x-empty-state title="No purchase invoices" description="Record a supplier bill to capture operating expense and recoverable VAT." icon="invoice" />
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <table class="min-w-full divide-y divide-slate-100 text-left text-xs dark:divide-slate-800">
                <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Bill</th>
                        <th class="px-3 py-2">Supplier</th>
                        <th class="px-3 py-2">Total</th>
                        <th class="px-3 py-2">VAT</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($bills as $bill)
                        <tr>
                            <td class="px-3 py-2">
                                <p class="font-semibold">{{ $bill->reference }}</p>
                                <p class="text-[11px] text-slate-500">{{ $bill->bill_date?->format('d M Y') }}</p>
                            </td>
                            <td class="px-3 py-2">{{ $bill->supplier_name }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ \App\Support\Money::format($bill->total) }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ \App\Support\Money::format($bill->tax_amount) }}</td>
                            <td class="px-3 py-2"><x-status-badge :tone="$bill->status->tone()" :label="$bill->status->label()" /></td>
                            <td class="px-3 py-2 text-right"><a href="{{ route('ledger.purchases.show', $bill) }}" class="font-semibold text-brand-700 hover:underline">Open</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="no-print">{{ $bills->links() }}</div>
    @endif
</div>
@endsection
