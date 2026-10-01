@extends('layouts.app')

@section('title', $account->name)
@section('page-title', $account->name)

@section('content')
<div class="space-y-3">
    <x-page-header :title="$account->name" :subtitle="($account->bank_name ?: 'Bank').' · GL '.$account->glAccount?->label()">
        <x-slot:actions>
            @if ($canManage)
                <form method="POST" action="{{ route('ledger.bank.auto-match', $account) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Auto-match</button>
                </form>
            @endif
            <a href="{{ route('ledger.bank.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">All accounts</a>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:gap-3">
        @foreach ([
            ['Opening', \App\Support\Money::format($account->opening_balance), 'text-slate-600'],
            ['Book (matched)', \App\Support\Money::format($summary['book_balance']), 'text-indigo-700'],
            ['Statement', \App\Support\Money::format($summary['statement_balance']), 'text-sky-700'],
            ['Unmatched', number_format($summary['unmatched_count']), 'text-amber-700'],
        ] as [$label, $value, $tone])
            <div class="flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                <p class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    @if ($canManage)
        <section class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Add statement line</h2>
            <p class="mt-1 text-[11px] text-slate-500">Use positive amounts for receipts and negative amounts for payments out.</p>
            <form method="POST" action="{{ route('ledger.bank.lines.store', $account) }}" class="mt-2 grid gap-2 sm:grid-cols-2 xl:grid-cols-6 xl:items-end">
                @csrf
                <x-form-field label="Date" name="transaction_date" type="date" :value="old('transaction_date', now()->toDateString())" required />
                <x-form-field label="Description" name="description" :value="old('description')" class="sm:col-span-2" required />
                <x-form-field label="Amount" name="amount" type="number" :value="old('amount')" step="0.01" required />
                <x-form-field label="Bank ref" name="external_reference" :value="old('external_reference')" />
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-700 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-800">Add line</button>
            </form>
        </section>
    @endif

    <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <form method="GET" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4 xl:items-end" x-data x-ref="filterForm">
            <x-form-field label="Status" name="status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-form-field>
            <x-filter-reset :href="route('ledger.bank.show', $account)" />
        </form>
    </section>

    @if ($lines->isEmpty())
        <x-empty-state title="No statement lines" description="Add lines from the bank statement, then match them to payments." icon="wallet" />
    @else
        <div class="psg-stack overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <table class="min-w-full divide-y divide-slate-100 text-left text-xs dark:divide-slate-800">
                <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60">
                    <tr>
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Description</th>
                        <th class="px-3 py-2">Amount</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Match</th>
                        <th class="px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($lines as $line)
                        <tr>
                            <td class="px-3 py-2" data-label="Date">{{ $line->transaction_date?->format('d M Y') }}</td>
                            <td class="px-3 py-2" data-label="Description">
                                <p class="font-semibold">{{ $line->description }}</p>
                                @if ($line->external_reference)
                                    <p class="text-[11px] text-slate-500">Ref {{ $line->external_reference }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-2 tabular-nums" data-label="Amount">{{ \App\Support\Money::format($line->amount) }}</td>
                            <td class="px-3 py-2" data-label="Status"><x-status-badge :tone="$line->status->tone()" :label="$line->status->label()" /></td>
                            <td class="px-3 py-2" data-label="Match">
                                @if ($line->matchedPayment)
                                    <a href="{{ route('payments.show', $line->matchedPayment) }}" class="font-semibold text-brand-700 hover:underline">{{ $line->matchedPayment->reference }}</a>
                                @elseif ($canManage && $line->status->value === 'unmatched')
                                    <form method="POST" action="{{ route('ledger.bank.lines.match', [$account, $line]) }}" class="flex items-center gap-1">
                                        @csrf
                                        <select name="payment_id" class="field__control text-xs" required>
                                            <option value="">Select payment</option>
                                            @foreach ($candidatePayments as $payment)
                                                <option value="{{ $payment->id }}">
                                                    {{ $payment->reference }} · {{ \App\Support\Money::format($payment->amount) }}
                                                    @if ($payment->invoice) · {{ $payment->invoice->reference }} @endif
                                                    @if ($payment->payrollRun) · {{ $payment->payrollRun->reference }} @endif
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="rounded bg-brand-700 px-2 py-1 text-[11px] font-semibold text-white">Match</button>
                                    </form>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right" data-label="Actions">
                                @if ($canManage && $line->status->value === 'matched')
                                    <form method="POST" action="{{ route('ledger.bank.lines.unmatch', [$account, $line]) }}" class="inline">@csrf<button class="font-semibold text-amber-700 hover:underline">Unmatch</button></form>
                                @elseif ($canManage && $line->status->value === 'unmatched')
                                    <form method="POST" action="{{ route('ledger.bank.lines.exclude', [$account, $line]) }}" class="inline">@csrf<button class="font-semibold text-slate-500 hover:underline">Exclude</button></form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-table-pagination :paginator="$lines" />
    @endif
</div>
@endsection
