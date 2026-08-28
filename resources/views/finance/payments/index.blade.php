@extends('layouts.app')

@section('title', 'Payments')
@section('page-title', 'Payments')

@section('content')
<div class="space-y-6">
    <x-page-header title="Payments" subtitle="Collections ledger — this month and full payment history.">
        <x-slot:actions>
            <x-finance.scope-tabs
                :current-url="route('payments.index', request()->except('scope', 'page'))"
                :all-url="route('payments.index', array_merge(request()->except('page'), ['scope' => 'all']))"
                :scope="$scope === 'all' ? 'all' : 'current'"
                current-label="This month"
                all-label="All history"
            />
            <x-report-actions :csv="route('payments.export', $exportQuery)" />
            @if ($canManage)
                <a href="{{ route('payments.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800"><x-icon name="plus" class="h-4 w-4" /> Record payment</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="report-print-area space-y-6">
        <x-print.report-header title="Payments register" subtitle="Collections ledger — this month and full payment history." />
        <section class="flex flex-row gap-2 sm:gap-3">
            <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-emerald-700 sm:text-[11px]">Today</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 sm:text-xl">{{ \App\Support\Money::format($stats['today']) }}</p>
            </div>
            <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-brand-700 sm:text-[11px]">This month</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 sm:text-xl">{{ \App\Support\Money::format($stats['month']) }}</p>
            </div>
            <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-indigo-700 sm:text-[11px]">All time</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 sm:text-xl">{{ \App\Support\Money::format($stats['all_time']) }}</p>
            </div>
        </section>

        <section class="no-print rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <form method="GET" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5 xl:items-end" x-data x-ref="filterForm">
                @if ($scope === 'all')<input type="hidden" name="scope" value="all">@endif
                <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" class="sm:col-span-2" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
                <x-form-field label="Client" name="client_id" type="select" data-searchable="true" x-on:change="$refs.filterForm.requestSubmit()">
                    <option value="">All clients</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected((string) ($filters['client_id'] ?? '') === (string) $client->id)>{{ $client->name }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Date" name="date" type="date" :value="$filters['date'] ?? ''" x-on:change="$refs.filterForm.requestSubmit()" />
                <x-filter-reset :href="route('payments.index', $scope === 'all' ? ['scope' => 'all'] : [])" />
            </form>
        </section>

        @if ($payments->isEmpty())
            <x-empty-state title="No payments recorded" description="Record collections against open invoices." icon="payment" />
        @else
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-14 px-5 py-3">#</th>
                            <th class="px-5 py-3">Payment</th>
                            <th class="px-5 py-3">Client / invoice</th>
                            <th class="px-5 py-3">Amount</th>
                            <th class="px-5 py-3">Method</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($payments as $payment)
                            <tr>
                                <td class="px-5 py-3.5"><x-table-serial :paginator="$payments" :index="$loop->index" /></td>
                                <td class="px-5 py-3.5">
                                    <p class="font-semibold font-mono">{{ $payment->reference }}</p>
                                    <p class="text-xs text-slate-500">{{ $payment->payment_date->format('d M Y') }}</p>
                                </td>
                                <td class="px-5 py-3.5">
                                    <p>{{ $payment->client?->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $payment->invoice?->reference }}</p>
                                </td>
                                <td class="px-5 py-3.5 font-semibold">{{ \App\Support\Money::format($payment->amount) }}</td>
                                <td class="px-5 py-3.5"><x-status-badge :tone="$payment->method->tone()" :label="$payment->method->label()" /></td>
                                <td class="px-5 py-3.5 text-right"><x-action-icon :href="route('payments.show', $payment)" label="View" icon="eye" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="no-print">{{ $payments->links() }}</div>
        @endif
    </div>
</div>
@endsection
