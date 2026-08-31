@extends('layouts.app')

@section('title', 'Payments')
@section('page-title', 'Payments')

@section('content')
<div class="space-y-3">
    <x-page-header title="Payments" subtitle="Client collections and payroll disbursements.">
        <x-slot:actions>
            <x-finance.scope-tabs
                :current-url="route('payments.index', array_merge(request()->except('scope', 'page'), ['scope' => 'month']))"
                :all-url="route('payments.index', array_merge(request()->except('page'), ['scope' => 'all']))"
                :scope="$scope === 'all' ? 'all' : 'current'"
                current-label="This month"
                all-label="All history"
            />
            <x-report-actions :csv="route('payments.export', $exportQuery)" />
            @if ($canManage)
                <a href="{{ route('payments.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800"><x-icon name="plus" class="h-3.5 w-3.5" /> Record payment</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="report-print-area space-y-3">
        <x-print.report-header title="Payments register" subtitle="Client collections and payroll disbursements." />
        <section class="flex flex-row gap-2 sm:gap-3">
            <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-emerald-700 sm:text-[11px]">Collections today</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 sm:text-xl">{{ \App\Support\Money::format($stats['today']) }}</p>
            </div>
            <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-brand-700 sm:text-[11px]">Collections this month</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 sm:text-xl">{{ \App\Support\Money::format($stats['month']) }}</p>
            </div>
            <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-amber-700 sm:text-[11px]">Payroll out this month</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 sm:text-xl">{{ \App\Support\Money::format($stats['disbursements_month']) }}</p>
            </div>
        </section>

        <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
            <form method="GET" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-5 xl:items-end" x-data x-ref="filterForm">
                @if ($scope === 'all')
                    <input type="hidden" name="scope" value="all">
                @elseif ($scope === 'month')
                    <input type="hidden" name="scope" value="month">
                @endif
                <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" class="sm:col-span-2" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
                <x-form-field label="Client" name="client_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                    <option value="">All clients</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected((string) ($filters['client_id'] ?? '') === (string) $client->id)>{{ $client->name }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Date" name="date" type="date" :value="$filters['date'] ?? ''" x-on:change="$refs.filterForm.requestSubmit()" />
                <x-filter-reset :href="route('payments.index', $scope === 'all' ? ['scope' => 'all'] : ['scope' => 'month'])" />
            </form>
        </section>

        @if ($payments->isEmpty())
            <x-empty-state title="No payments recorded" description="Client collections appear when recorded against invoices. Payroll disbursements appear when a payroll run is marked as paid." icon="payment" />
        @else
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-14 px-3 py-2">#</th>
                            <th class="px-3 py-2">Payment</th>
                            <th class="px-3 py-2">Client / payroll</th>
                            <th class="px-3 py-2">Amount</th>
                            <th class="px-3 py-2">Method</th>
                            <th class="px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($payments as $payment)
                            <tr>
                                <td class="px-3 py-2"><x-table-serial :paginator="$payments" :index="$loop->index" /></td>
                                <td class="px-3 py-2">
                                    <p class="font-semibold font-mono">{{ $payment->reference }}</p>
                                    <p class="text-xs text-slate-500">{{ $payment->payment_date->format('d M Y') }}</p>
                                </td>
                                <td class="px-3 py-2">
                                    @if ($payment->isDisbursement())
                                        <p>Payroll disbursement</p>
                                        <p class="text-xs text-slate-500">{{ $payment->payrollRun?->reference }}</p>
                                    @else
                                        <p>{{ $payment->client?->name }}</p>
                                        <p class="text-xs text-slate-500">{{ $payment->invoice?->reference }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-2 font-semibold">{{ \App\Support\Money::format($payment->amount) }}</td>
                                <td class="px-3 py-2"><x-status-badge :tone="$payment->method->tone()" :label="$payment->method->label()" /></td>
                                <td class="px-3 py-2 text-right"><x-action-icon :href="route('payments.show', $payment)" label="View" icon="eye" /></td>
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
