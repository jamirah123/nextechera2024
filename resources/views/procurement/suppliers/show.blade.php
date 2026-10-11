@extends('layouts.app')

@section('title', $supplier->reference)
@section('page-title', $supplier->name)
@section('page-subtitle', $supplier->reference)

@section('content')
@php
    $empty = '<span class="font-normal text-slate-400">Not recorded</span>';
@endphp

<div class="space-y-3">
    <x-page-header
        size="sm"
        :title="$supplier->name"
        :subtitle="$supplier->reference.' · '.$supplier->category->label()"
        :back="route('procurement.suppliers.index')"
    >
        <x-slot:actions>
            <x-status-badge :tone="$supplier->is_active ? 'emerald' : 'slate'" :label="$supplier->is_active ? 'Active' : 'Inactive'" />
            @if ($canManage)
                <a href="{{ route('procurement.suppliers.edit', $supplier) }}" class="btn btn-secondary">Edit</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-100 bg-slate-50/80 px-3 py-2 dark:border-slate-800 dark:bg-slate-800/50">
            <h2 class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Supplier details</h2>
        </div>
        <dl class="grid gap-0 sm:grid-cols-2 lg:grid-cols-3">
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Category</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $supplier->category->label() }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">TIN</dt>
                <dd class="mt-0.5 text-xs font-semibold tabular-nums text-slate-900 dark:text-slate-100">
                    @if ($supplier->tin)
                        {{ $supplier->tin }}
                    @else
                        {!! $empty !!}
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                <dd class="mt-0.5">
                    <x-status-badge :tone="$supplier->is_active ? 'emerald' : 'slate'" :label="$supplier->is_active ? 'Active' : 'Inactive'" />
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Contact person</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($supplier->contact_person)
                        {{ $supplier->contact_person }}
                    @else
                        {!! $empty !!}
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Phone</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($supplier->phone)
                        <a href="tel:{{ $supplier->phone }}" class="hover:text-brand-700">{{ $supplier->phone }}</a>
                    @else
                        {!! $empty !!}
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Email</dt>
                <dd class="mt-0.5 break-all text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($supplier->email)
                        <a href="mailto:{{ $supplier->email }}" class="hover:text-brand-700">{{ $supplier->email }}</a>
                    @else
                        {!! $empty !!}
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:col-span-2 lg:col-span-3 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Physical address</dt>
                <dd class="mt-0.5 text-xs font-semibold leading-snug text-slate-900 dark:text-slate-100">
                    @if ($supplier->address)
                        {{ $supplier->address }}
                    @else
                        {!! $empty !!}
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:col-span-2 lg:col-span-3 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Delivery address</dt>
                <dd class="mt-0.5 text-xs font-semibold leading-snug text-slate-900 dark:text-slate-100">
                    @if ($supplier->delivery_address)
                        {{ $supplier->delivery_address }}
                    @else
                        {!! $empty !!}
                    @endif
                </dd>
            </div>
            @if ($supplier->notes)
                <div class="border-b border-slate-100 px-3 py-2 sm:col-span-2 lg:col-span-3 dark:border-slate-800">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Notes</dt>
                    <dd class="mt-0.5 text-xs leading-snug text-slate-700 dark:text-slate-300">{{ $supplier->notes }}</dd>
                </div>
            @endif
            @if ($supplier->attachment_path)
                <div class="px-3 py-2 sm:col-span-2 lg:col-span-3">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Supporting document</dt>
                    <dd class="mt-0.5 text-xs font-semibold">
                        <a href="{{ route('procurement.suppliers.document', $supplier) }}" class="text-brand-700 hover:text-brand-800">Download</a>
                    </dd>
                </div>
            @endif
        </dl>
    </section>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-3 py-2 dark:border-slate-800 dark:bg-slate-800/50">
            <div>
                <h2 class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Linked bills</h2>
                <p class="mt-0.5 text-[11px] text-slate-500">Supplier bills saved against this record.</p>
            </div>
            <span class="text-[10px] font-semibold text-slate-500">{{ $supplier->bills_count }}</span>
        </div>
        @if ($bills->isEmpty())
            <div class="px-3 py-4 text-center">
                <p class="text-xs font-semibold text-slate-900 dark:text-slate-100">No bills yet</p>
                <p class="mt-0.5 text-[11px] text-slate-500">Bills raised for this supplier will appear here.</p>
            </div>
        @else
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($bills as $bill)
                    <li>
                        <a href="{{ route('ledger.purchases.show', $bill) }}" class="flex flex-col gap-1 px-3 py-2 hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between dark:hover:bg-slate-800/60">
                            <div class="min-w-0">
                                <p class="truncate text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $bill->reference }}</p>
                                <p class="mt-0.5 text-[10px] text-slate-500">
                                    {{ $bill->bill_date?->format('d M Y') ?? 'No date' }}
                                    · {{ \App\Support\Money::format($bill->total) }}
                                </p>
                            </div>
                            <x-status-badge :tone="$bill->status->tone()" :label="$bill->status->label()" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
