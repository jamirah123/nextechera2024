@extends('layouts.app')

@section('title', $bill->reference)
@section('page-title', $bill->reference)

@section('content')
<div class="space-y-3">
    <x-page-header :title="$bill->reference" :subtitle="$bill->supplier_name">
        <x-slot:actions>
            @if ($canManage && $bill->isEditable())
                <a href="{{ route('ledger.purchases.edit', $bill) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm">Edit</a>
                <form method="POST" action="{{ route('ledger.purchases.post', $bill) }}">@csrf<button class="inline-flex items-center rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-800">Post to ledger</button></form>
            @endif
            @if ($canManage && $bill->status->value !== 'cancelled')
                <form method="POST" action="{{ route('ledger.purchases.cancel', $bill) }}" onsubmit="return confirm('Cancel this purchase?');">@csrf<button class="inline-flex items-center rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700">Cancel</button></form>
            @endif
            <a href="{{ route('ledger.purchases.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm">Back</a>
        </x-slot:actions>
    </x-page-header>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Status</p>
            <p class="mt-1"><x-status-badge :tone="$bill->status->tone()" :label="$bill->status->label()" /></p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Net / VAT / Total</p>
            <p class="mt-1 text-sm font-semibold tabular-nums">{{ \App\Support\Money::format($bill->subtotal) }} · {{ \App\Support\Money::format($bill->tax_amount) }} · {{ \App\Support\Money::format($bill->total) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Expense account</p>
            <p class="mt-1 text-sm font-semibold">{{ $bill->expenseAccount?->label() }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Bill date</p>
            <p class="mt-1 text-sm font-semibold">{{ $bill->bill_date?->format('d M Y') }}</p>
        </div>
    </section>

    @if ($bill->description)
        <p class="text-xs text-slate-600">{{ $bill->description }}</p>
    @endif
</div>
@endsection
