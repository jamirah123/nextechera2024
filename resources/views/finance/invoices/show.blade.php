@extends('layouts.app')

@section('title', $invoice->reference)
@section('page-title', 'Invoice')

@section('content')
<div class="space-y-3">
    <x-page-header :title="$invoice->reference" :subtitle="$invoice->client?->name" :back="route('invoices.index')">
        <x-slot:actions>
            <x-report-actions :csv="route('invoices.export-document', $invoice)">
                <a href="{{ route('invoices.pdf', $invoice) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    <x-icon name="download" class="h-3.5 w-3.5" />
                    Download PDF
                </a>
            </x-report-actions>
            @if ($canManage && $invoice->isEditable())
                <a href="{{ route('invoices.edit', $invoice) }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Edit draft</a>
                <form method="POST" action="{{ route('invoices.issue', $invoice) }}">@csrf<button class="inline-flex rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">Issue invoice</button></form>
            @endif
            @if ($canManage && $invoice->status->isOpen())
                <a href="{{ route('payments.create', ['invoice_id' => $invoice->id]) }}" class="btn btn-primary">Record payment</a>
            @endif
            @if ($canManage && $invoice->status->value !== 'paid' && (float) $invoice->amount_paid == 0)
                <form method="POST" action="{{ route('invoices.cancel', $invoice) }}" onsubmit="return confirm('Cancel this invoice?')">@csrf<button class="inline-flex rounded-lg bg-rose-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-800">Cancel</button></form>
            @endif
        </x-slot:actions>
    </x-page-header>

    @error('invoice')
        <p class="no-print rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</p>
    @enderror

    <x-entity.status-lifecycle
        class="no-print"
        :steps="$lifecycle['steps']"
        :current-step="$lifecycle['current']"
        :terminal-label="$lifecycle['terminal'] ?? null"
        :terminal-tone="$lifecycle['terminal_tone'] ?? 'slate'"
    />

    <div class="report-print-area space-y-3">
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900 print:overflow-visible print:rounded-none print:border-0 print:shadow-none print:bg-white">
            @include('documents.invoice.document', [
                'invoice' => $invoice,
                'paymentTerms' => $paymentTerms,
                'bankDetails' => $bankDetails,
                'companyLogo' => $companyLogo,
            ])
        </div>

        @if ($invoice->payments->isNotEmpty())
            <section class="no-print overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="border-b border-slate-100 px-3 py-2.5 dark:border-slate-700">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Payment history</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">All collections applied to this invoice.</p>
                </div>
                <table class="min-w-full divide-y divide-slate-100 text-xs dark:divide-slate-700">
                    <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        <tr>
                            <th class="px-3 py-2 text-left">Reference</th>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-left">Method</th>
                            <th class="px-3 py-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach ($invoice->payments as $payment)
                            <tr>
                                <td class="px-3 py-2"><a href="{{ route('payments.show', $payment) }}" class="font-semibold text-brand-700 hover:text-brand-800 dark:text-brand-300">{{ $payment->reference }}</a></td>
                                <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ $payment->payment_date->format('d M Y') }}</td>
                                <td class="px-3 py-2"><x-status-badge :tone="$payment->method->tone()" :label="$payment->method->label()" /></td>
                                <td class="px-3 py-2 text-right font-semibold text-slate-900 dark:text-slate-100">{{ \App\Support\Money::format($payment->amount, $invoice->currency) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif
    </div>

    <x-entity.activity-timeline :entries="$timeline" class="no-print" title="Invoice activity" />
</div>
@endsection
