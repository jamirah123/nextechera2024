@extends('layouts.app')

@section('title', 'VAT pack')
@section('page-title', 'VAT pack')

@section('content')
<div class="space-y-3">
    <x-page-header title="VAT pack" subtitle="Output VAT from issued invoices less input VAT on posted purchases.">
        <x-slot:actions>
            <x-report-actions :csv="route('ledger.vat.export', ['period_id' => $period->id])" />
            <a href="{{ route('ledger.purchases.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm">Purchases</a>
            <a href="{{ route('ledger.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm">Ledger hub</a>
        </x-slot:actions>
    </x-page-header>

    <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <form method="GET" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4 xl:items-end" x-data x-ref="filterForm">
            <x-form-field label="Period" name="period_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                @foreach ($periodOptions as $option)
                    <option value="{{ $option->id }}" @selected($period->id === $option->id)>{{ $option->label() }}</option>
                @endforeach
            </x-form-field>
        </form>
    </section>

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6 lg:gap-3">
        @foreach ([
            ['Default rate', number_format($pack['rate'], 0).'%', 'text-slate-600'],
            ['Taxable sales', \App\Support\Money::format($pack['taxable_sales']), 'text-indigo-700'],
            ['Output VAT', \App\Support\Money::format($pack['output_vat']), 'text-amber-700'],
            ['Input VAT', \App\Support\Money::format($pack['input_vat']), 'text-sky-700'],
            ['Net VAT due', \App\Support\Money::format($pack['net_vat']), 'text-rose-700'],
            ['Zero / cash sales', \App\Support\Money::format($pack['zero_rated_sales']), 'text-slate-500'],
        ] as [$label, $value, $tone])
            <div class="flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                <p class="mt-0.5 break-words text-xs font-semibold tabular-nums leading-snug text-slate-900 dark:text-slate-100 sm:text-sm">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <p class="text-[11px] text-slate-500">Output control: {{ $pack['vat_account']->label() }}. Input control: {{ $pack['vat_input_account']->label() }}. Ledger output {{ \App\Support\Money::format($pack['journal_output_vat']) }} · ledger input {{ \App\Support\Money::format($pack['journal_input_vat']) }}.</p>

    @if ($pack['invoices']->isEmpty())
        <x-empty-state title="No issued invoices in this period" description="Issue invoices with VAT (or cash/no-VAT) to populate the return." icon="invoice" />
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <table class="min-w-full divide-y divide-slate-100 text-left text-xs dark:divide-slate-800">
                <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60">
                    <tr>
                        <th class="px-3 py-2">Invoice</th>
                        <th class="px-3 py-2">Client</th>
                        <th class="px-3 py-2">Taxable</th>
                        <th class="px-3 py-2">VAT</th>
                        <th class="px-3 py-2">Total</th>
                        <th class="px-3 py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($pack['invoices'] as $invoice)
                        <tr>
                            <td class="px-3 py-2">
                                <a href="{{ route('invoices.show', $invoice) }}" class="font-semibold text-brand-700 hover:underline">{{ $invoice->reference }}</a>
                                <p class="text-[11px] text-slate-500">{{ optional($invoice->issue_date)?->format('d M Y') }}</p>
                            </td>
                            <td class="px-3 py-2">{{ $invoice->client?->name }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ \App\Support\Money::format($invoice->subtotal) }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ \App\Support\Money::format($invoice->tax_amount) }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ \App\Support\Money::format($invoice->total) }}</td>
                            <td class="px-3 py-2"><x-status-badge :tone="$invoice->status->tone()" :label="$invoice->status->label()" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($pack['purchases']->isNotEmpty())
        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Purchases (input VAT)</h2>
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <table class="min-w-full divide-y divide-slate-100 text-left text-xs dark:divide-slate-800">
                <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Bill</th>
                        <th class="px-3 py-2">Supplier</th>
                        <th class="px-3 py-2">Taxable</th>
                        <th class="px-3 py-2">Input VAT</th>
                        <th class="px-3 py-2">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($pack['purchases'] as $bill)
                        <tr>
                            <td class="px-3 py-2">
                                <a href="{{ route('ledger.purchases.show', $bill) }}" class="font-semibold text-brand-700 hover:underline">{{ $bill->reference }}</a>
                                <p class="text-[11px] text-slate-500">{{ $bill->bill_date?->format('d M Y') }}</p>
                            </td>
                            <td class="px-3 py-2">{{ $bill->supplier_name }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ \App\Support\Money::format($bill->subtotal) }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ \App\Support\Money::format($bill->tax_amount) }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ \App\Support\Money::format($bill->total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
