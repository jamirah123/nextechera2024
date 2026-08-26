@extends('layouts.app')

@section('title', $invoice->reference)
@section('page-title', 'Invoice')

@section('content')
<div class="space-y-6">
    <x-page-header :title="$invoice->reference" :subtitle="$invoice->client?->name" :back="route('invoices.index')">
        <x-slot:actions>
            <x-report-actions :csv="route('invoices.export-document', $invoice)" />
            @if ($canManage && $invoice->isEditable())
                <a href="{{ route('invoices.edit', $invoice) }}" class="inline-flex rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Edit draft</a>
                <form method="POST" action="{{ route('invoices.issue', $invoice) }}">@csrf<button class="inline-flex rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">Issue invoice</button></form>
            @endif
            @if ($canManage && $invoice->status->isOpen())
                <a href="{{ route('payments.create', ['invoice_id' => $invoice->id]) }}" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">Record payment</a>
            @endif
            @if ($canManage && $invoice->status->value !== 'paid' && (float) $invoice->amount_paid == 0)
                <form method="POST" action="{{ route('invoices.cancel', $invoice) }}" onsubmit="return confirm('Cancel this invoice?')">@csrf<button class="inline-flex rounded-xl bg-rose-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-800">Cancel</button></form>
            @endif
        </x-slot:actions>
    </x-page-header>

    @error('invoice')
        <p class="no-print rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</p>
    @enderror

    <div class="report-print-area space-y-6">
        <x-finance.document
            :title="'Tax Invoice'"
            :reference="$invoice->reference"
            :subtitle="$invoice->client?->name . ($invoice->site ? ' · '.$invoice->site->name : '')"
            :status-tone="$invoice->status->tone()"
            :status-label="$invoice->status->label()"
            :meta="array_merge($recordMeta, [[
                'label' => 'Billing period',
                'value' => $invoice->period_start->format('d M Y').' – '.$invoice->period_end->format('d M Y'),
                'hint' => $invoice->due_date ? 'Due '.$invoice->due_date->format('d M Y') : null,
            ]])"
        >
            <div class="mb-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Bill to</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $invoice->client?->name }}</p>
                    @if ($invoice->client?->contact_person)
                        <p class="text-sm text-slate-600">{{ $invoice->client->contact_person }}</p>
                    @endif
                    @if ($invoice->client?->email)
                        <p class="text-sm text-slate-600">{{ $invoice->client->email }}</p>
                    @endif
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Service location</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $invoice->site?->name ?? 'All client sites' }}</p>
                    @if ($invoice->site?->code)
                        <p class="text-sm text-slate-600">{{ $invoice->site->code }}</p>
                    @endif
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">#</th>
                            <th class="px-4 py-3 text-left">Description</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                            <th class="px-4 py-3 text-right">Unit</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($invoice->lines as $line)
                            <tr>
                                <td class="px-4 py-3.5 text-slate-500">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3.5 font-medium text-slate-900">{{ $line->description }}</td>
                                <td class="px-4 py-3.5 text-right text-slate-700">{{ number_format((float) $line->quantity, 2) }}</td>
                                <td class="px-4 py-3.5 text-right text-slate-700">{{ \App\Support\Money::format($line->unit_price, $invoice->currency) }}</td>
                                <td class="px-4 py-3.5 text-right font-semibold text-slate-900">{{ \App\Support\Money::format($line->line_total, $invoice->currency) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No line items on this invoice.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-slate-50/80 text-sm">
                        <tr><td colspan="4" class="px-4 py-2 text-right text-slate-600">Subtotal</td><td class="px-4 py-2 text-right font-medium">{{ \App\Support\Money::format($invoice->subtotal, $invoice->currency) }}</td></tr>
                        <tr><td colspan="4" class="px-4 py-2 text-right text-slate-600">Tax</td><td class="px-4 py-2 text-right font-medium">{{ \App\Support\Money::format($invoice->tax_amount, $invoice->currency) }}</td></tr>
                        <tr><td colspan="4" class="px-4 py-3 text-right font-semibold text-slate-900">Total</td><td class="px-4 py-3 text-right text-lg font-bold text-brand-800">{{ \App\Support\Money::format($invoice->total, $invoice->currency) }}</td></tr>
                        <tr><td colspan="4" class="px-4 py-2 text-right text-emerald-700">Paid</td><td class="px-4 py-2 text-right font-medium text-emerald-800">{{ \App\Support\Money::format($invoice->amount_paid, $invoice->currency) }}</td></tr>
                        <tr><td colspan="4" class="px-4 py-3 text-right font-semibold text-rose-800">Balance due</td><td class="px-4 py-3 text-right text-lg font-bold text-rose-700">{{ \App\Support\Money::format($invoice->balance, $invoice->currency) }}</td></tr>
                    </tfoot>
                </table>
            </div>

            @if ($invoice->notes)
                <p class="mt-5 text-sm text-slate-600"><span class="font-semibold text-slate-800">Notes:</span> {{ $invoice->notes }}</p>
            @endif
        </x-finance.document>

        @if ($invoice->payments->isNotEmpty())
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                    <h2 class="text-base font-semibold text-slate-900">Payment history</h2>
                    <p class="mt-0.5 text-sm text-slate-500">All collections applied to this invoice.</p>
                </div>
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-left">Reference</th>
                            <th class="px-5 py-3 text-left">Date</th>
                            <th class="px-5 py-3 text-left">Method</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($invoice->payments as $payment)
                            <tr>
                                <td class="px-5 py-3.5"><a href="{{ route('payments.show', $payment) }}" class="font-semibold text-brand-700 hover:text-brand-800">{{ $payment->reference }}</a></td>
                                <td class="px-5 py-3.5">{{ $payment->payment_date->format('d M Y') }}</td>
                                <td class="px-5 py-3.5"><x-status-badge :tone="$payment->method->tone()" :label="$payment->method->label()" /></td>
                                <td class="px-5 py-3.5 text-right font-semibold">{{ \App\Support\Money::format($payment->amount, $invoice->currency) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif
    </div>

    <x-finance.history-timeline :logs="$history" class="no-print" />
</div>
@endsection
