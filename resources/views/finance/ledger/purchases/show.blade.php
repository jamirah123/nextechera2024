@extends('layouts.app')

@section('title', $bill->reference)
@section('page-title', $bill->reference)
@section('page-subtitle', $bill->supplier_name)

@section('content')
@php
    $empty = '<span class="font-normal text-slate-400">Not recorded</span>';
@endphp

<div class="space-y-3">
    <x-page-header size="sm" :title="$bill->reference" :subtitle="$bill->supplier_name" :back="route('ledger.purchases.index')">
        <x-slot:actions>
            <x-status-badge :tone="$bill->status->tone()" :label="$bill->status->label()" />
            @if ($canManage && $bill->isEditable())
                <a href="{{ route('ledger.purchases.edit', $bill) }}" class="btn btn-secondary">Edit</a>
                <form method="POST" action="{{ route('ledger.purchases.post', $bill) }}">@csrf<button class="btn btn-primary">Post to ledger</button></form>
            @endif
            @if ($canManage && $bill->status->value !== 'cancelled')
                <x-confirm-action
                    :action="route('ledger.purchases.cancel', $bill)"
                    title="Cancel this supplier bill?"
                    confirm="Posted bills stay on record. Cancelling writes a reversing journal."
                    label="Cancel bill"
                    confirm-label="Yes, cancel bill"
                    cancel-label="Keep bill"
                    button-class="inline-flex items-center rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700"
                >
                    <x-slot:fields>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200" for="cancel-reason">Cancellation reason</label>
                        <textarea id="cancel-reason" name="reason" required maxlength="1000" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800"></textarea>
                    </x-slot:fields>
                </x-confirm-action>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-100 bg-slate-50/80 px-3 py-2 dark:border-slate-800 dark:bg-slate-800/50">
            <h2 class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Bill details</h2>
        </div>
        <dl class="grid gap-0 sm:grid-cols-2 lg:grid-cols-3">
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Supplier</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($bill->supplier)
                        <a href="{{ route('procurement.suppliers.show', $bill->supplier) }}" class="text-brand-700 hover:text-brand-800">{{ $bill->supplier_name }}</a>
                    @else
                        {{ $bill->supplier_name }}
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">TIN</dt>
                <dd class="mt-0.5 text-xs font-semibold tabular-nums text-slate-900 dark:text-slate-100">
                    @if ($bill->supplier_tin)
                        {{ $bill->supplier_tin }}
                    @else
                        {!! $empty !!}
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Supplier invoice no.</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($bill->supplier_invoice_no)
                        {{ $bill->supplier_invoice_no }}
                    @else
                        {!! $empty !!}
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Bill date</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $bill->bill_date?->format('d M Y') }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Due date</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($bill->due_date)
                        {{ $bill->due_date->format('d M Y') }}
                    @else
                        {!! $empty !!}
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Tax treatment</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $bill->vat_treatment?->label() ?? 'Standard-rated' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:col-span-2 lg:col-span-3 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Expense account</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $bill->expenseAccount?->label() ?: 'Not recorded' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Net amount</dt>
                <dd class="mt-0.5 text-xs font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ \App\Support\Money::format($bill->subtotal) }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">VAT</dt>
                <dd class="mt-0.5 text-xs font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ \App\Support\Money::format($bill->tax_amount) }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Total</dt>
                <dd class="mt-0.5 text-xs font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ \App\Support\Money::format($bill->total) }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:col-span-2 lg:col-span-3 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Description</dt>
                <dd class="mt-0.5 text-xs leading-snug text-slate-700 dark:text-slate-300">
                    @if ($bill->description)
                        {{ $bill->description }}
                    @else
                        {!! $empty !!}
                    @endif
                </dd>
            </div>
            @if ($bill->attachment_path)
                <div class="border-b border-slate-100 px-3 py-2 sm:col-span-2 lg:col-span-3 dark:border-slate-800">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Supplier invoice file</dt>
                    <dd class="mt-0.5 flex gap-3 text-xs font-semibold">
                        <a href="{{ route('ledger.purchases.attachment.view', $bill) }}" class="text-brand-700 hover:text-brand-800">View</a>
                        <a href="{{ route('ledger.purchases.attachment', $bill) }}" class="text-brand-700 hover:text-brand-800">Download</a>
                    </dd>
                </div>
            @endif
            @if ($bill->posted_at)
                <div class="border-b border-slate-100 px-3 py-2 sm:col-span-2 lg:col-span-3 dark:border-slate-800">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Posted</dt>
                    <dd class="mt-0.5 text-xs text-slate-700 dark:text-slate-300">{{ $bill->posted_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}@if ($bill->poster) by {{ $bill->poster->name }}@endif</dd>
                </div>
            @endif
            @if ($bill->cancellation_reason)
                <div class="px-3 py-2 sm:col-span-2 lg:col-span-3">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-rose-700">Cancelled</dt>
                    <dd class="mt-0.5 text-xs text-rose-700">{{ $bill->cancelled_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}@if ($bill->canceller) by {{ $bill->canceller->name }}@endif. {{ $bill->cancellation_reason }}</dd>
                </div>
            @endif
        </dl>
    </section>
</div>
@endsection
