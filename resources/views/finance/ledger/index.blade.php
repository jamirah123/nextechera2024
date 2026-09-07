@extends('layouts.app')

@section('title', 'General ledger')
@section('page-title', 'General ledger')

@section('content')
<div class="space-y-3">
    <x-page-header title="General ledger" subtitle="Chart of accounts, journals from operations, VAT pack, bank reconciliation and period close.">
        <x-slot:actions>
            <a href="{{ route('ledger.accounts.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Chart of accounts</a>
            <a href="{{ route('ledger.journals.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Journals</a>
            <a href="{{ route('ledger.purchases.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Purchases</a>
            <a href="{{ route('ledger.vat.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">VAT pack</a>
            <a href="{{ route('ledger.reports.trial-balance') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Trial balance</a>
            <a href="{{ route('ledger.bank.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Bank rec</a>
            <a href="{{ route('ledger.periods.index') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">Periods</a>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:gap-3">
        @foreach ([
            ['GL accounts', number_format($stats['accounts']), 'text-sky-700'],
            ['Posted journals', number_format($stats['journals']), 'text-indigo-700'],
            ['Unmatched bank lines', number_format($stats['unmatched']), 'text-amber-700'],
            ['Closed periods', number_format($stats['closed_periods']), 'text-slate-600'],
        ] as [$label, $value, $tone])
            <div class="flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                <p class="mt-0.5 text-base font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <div class="grid gap-3 lg:grid-cols-3">
        <section class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900 lg:col-span-2">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Recent journals</h2>
                <a href="{{ route('ledger.journals.index') }}" class="text-xs font-semibold text-brand-700 hover:underline">View all</a>
            </div>
            @if ($recent->isEmpty())
                <p class="mt-3 text-xs text-slate-500">Journals post automatically when invoices are issued, payments recorded, and payroll is approved or paid.</p>
            @else
                <div class="mt-2 overflow-hidden rounded-md border border-slate-100 dark:border-slate-800">
                    <table class="min-w-full divide-y divide-slate-100 text-left text-xs dark:divide-slate-800">
                        <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60">
                            <tr>
                                <th class="px-3 py-2">Journal</th>
                                <th class="px-3 py-2">Source</th>
                                <th class="px-3 py-2">Amount</th>
                                <th class="px-3 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($recent as $journal)
                                <tr>
                                    <td class="px-3 py-2">
                                        <a href="{{ route('ledger.journals.show', $journal) }}" class="font-semibold text-brand-700 hover:underline">{{ $journal->reference }}</a>
                                        <p class="text-[11px] text-slate-500">{{ $journal->journal_date?->format('d M Y') }} · {{ $journal->description }}</p>
                                    </td>
                                    <td class="px-3 py-2"><x-status-badge :tone="$journal->source->tone()" :label="$journal->source->label()" /></td>
                                    <td class="px-3 py-2 tabular-nums">{{ \App\Support\Money::format($journal->debitTotal()) }}</td>
                                    <td class="px-3 py-2"><x-status-badge :tone="$journal->status->tone()" :label="$journal->status->label()" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="space-y-3">
            <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Current period</h2>
                @if ($openPeriod)
                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">{{ $openPeriod->label() }}</p>
                    <p class="mt-2"><x-status-badge :tone="$openPeriod->status->tone()" :label="$openPeriod->status->label()" /></p>
                @else
                    <p class="mt-1 text-xs text-slate-500">No period opened yet.</p>
                @endif
                <a href="{{ route('ledger.periods.index') }}" class="mt-3 inline-flex text-xs font-semibold text-brand-700 hover:underline">Manage period close</a>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Bank accounts</h2>
                <ul class="mt-2 space-y-2">
                    @forelse ($bankAccounts as $account)
                        <li>
                            <a href="{{ route('ledger.bank.show', $account) }}" class="block rounded-md border border-slate-100 px-2.5 py-2 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/60">
                                <p class="text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $account->name }}</p>
                                <p class="text-[11px] text-slate-500">{{ $account->glAccount?->label() }}</p>
                            </a>
                        </li>
                    @empty
                        <li class="text-xs text-slate-500">No bank accounts configured.</li>
                    @endforelse
                </ul>
            </div>
        </section>
    </div>
</div>
@endsection
