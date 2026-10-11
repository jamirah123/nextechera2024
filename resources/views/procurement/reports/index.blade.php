@extends('layouts.app')

@section('title', 'Procurement reports')
@section('page-title', 'Procurement reports')

@section('content')
<div class="space-y-3">
    <x-page-header title="Procurement reports" subtitle="Supplier bills, stock on hand, and kit still with guards.">
        <x-slot:actions>
            <a href="{{ route('procurement.reports.export', request()->query()) }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700">Export CSV</a>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="grid gap-2 rounded-lg border border-slate-200 bg-white p-2 sm:grid-cols-3 sm:items-end lg:grid-cols-5 dark:border-slate-700 dark:bg-slate-900">
        <x-form-field label="From" name="from" type="date" :value="$filters['from']" />
        <x-form-field label="To" name="to" type="date" :value="$filters['to']" />
        <x-form-field label="Status" name="status" type="select">
            <option value="">All</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form-field>
        <x-form-field label="Supplier" name="supplier" :value="$filters['supplier']" />
        <button class="rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white">Apply</button>
    </form>

    <p class="text-xs text-slate-600">{{ $totals['count'] }} bills · Subtotal {{ \App\Support\Money::format($totals['subtotal']) }} · Input VAT {{ \App\Support\Money::format($totals['tax']) }} · Total {{ \App\Support\Money::format($totals['total']) }}</p>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Bill</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bills as $bill)
                    <tr>
                        <td><a href="{{ route('ledger.purchases.show', $bill) }}" class="font-semibold text-brand-700">{{ $bill->reference }}</a></td>
                        <td>{{ $bill->supplier_name }}</td>
                        <td>{{ $bill->status->label() }}</td>
                        <td>{{ \App\Support\Money::format($bill->total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">No bills in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $bills->links() }}</div>

    <section class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <h2 class="text-sm font-semibold">Outstanding kit</h2>
        <ul class="mt-2 space-y-1 text-xs">
            @forelse ($outstanding as $line)
                <li>{{ $line->issuance?->assignedGuard?->full_name ?? 'Guard' }} — {{ $line->displayLabel() }} — outstanding {{ $line->quantityOutstanding() }}</li>
            @empty
                <li class="text-slate-500">No outstanding kit.</li>
            @endforelse
        </ul>
    </section>
</div>
@endsection
