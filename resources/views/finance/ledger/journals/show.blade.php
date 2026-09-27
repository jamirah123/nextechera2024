@extends('layouts.app')

@section('title', $journal->reference)
@section('page-title', $journal->reference)

@section('content')
<div class="space-y-3">
    <x-page-header :title="$journal->reference" :subtitle="$journal->description">
        <x-slot:actions>
            <a href="{{ route('ledger.journals.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Back</a>
        </x-slot:actions>
    </x-page-header>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Date</p>
            <p class="mt-1 text-sm font-semibold">{{ $journal->journal_date?->format('d M Y') }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Period</p>
            <p class="mt-1 text-sm font-semibold">{{ $journal->period?->label() }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Source</p>
            <p class="mt-1"><x-status-badge :tone="$journal->source->tone()" :label="$journal->source->label()" /></p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Status</p>
            <p class="mt-1"><x-status-badge :tone="$journal->status->tone()" :label="$journal->status->label()" /></p>
        </div>
    </section>

    <div class="psg-stack overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <table class="min-w-full divide-y divide-slate-100 text-left text-xs dark:divide-slate-800">
            <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60">
                <tr>
                    <th class="px-3 py-2">Account</th>
                    <th class="px-3 py-2">Memo</th>
                    <th class="px-3 py-2 text-right">Debit</th>
                    <th class="px-3 py-2 text-right">Credit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($journal->lines as $line)
                    <tr>
                        <td class="px-3 py-2 font-semibold" data-label="Account">{{ $line->account?->label() }}</td>
                        <td class="px-3 py-2 text-slate-500" data-label="Memo">{{ $line->memo ?: '—' }}</td>
                        <td class="px-3 py-2 text-right tabular-nums" data-label="Debit">{{ (float) $line->debit > 0 ? \App\Support\Money::format($line->debit) : '—' }}</td>
                        <td class="px-3 py-2 text-right tabular-nums" data-label="Credit">{{ (float) $line->credit > 0 ? \App\Support\Money::format($line->credit) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-slate-50 text-xs font-semibold dark:bg-slate-800/60">
                <tr>
                    <td class="px-3 py-2" colspan="2" data-label="">Totals</td>
                    <td class="px-3 py-2 text-right tabular-nums" data-label="Debit">{{ \App\Support\Money::format($journal->debitTotal()) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums" data-label="Credit">{{ \App\Support\Money::format($journal->creditTotal()) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
