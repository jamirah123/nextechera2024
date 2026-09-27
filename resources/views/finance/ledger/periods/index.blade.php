@extends('layouts.app')

@section('title', 'Accounting periods')
@section('page-title', 'Accounting periods')

@section('content')
<div class="space-y-3">
    <x-page-header title="Period close" subtitle="Closed periods reject new journal postings dated inside them.">
        <x-slot:actions>
            <a href="{{ route('ledger.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Ledger hub</a>
        </x-slot:actions>
    </x-page-header>

    @if ($errors->has('period'))
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">{{ $errors->first('period') }}</div>
    @endif

    <div class="psg-stack overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <table class="min-w-full divide-y divide-slate-100 text-left text-xs dark:divide-slate-800">
            <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60">
                <tr>
                    <th class="px-3 py-2">Period</th>
                    <th class="px-3 py-2">Range</th>
                    <th class="px-3 py-2">Journals</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($periods as $period)
                    <tr>
                        <td class="px-3 py-2 font-semibold" data-label="Period">{{ $period->label() }}</td>
                        <td class="px-3 py-2 text-slate-500" data-label="Range">{{ $period->starts_on->format('d M Y') }} – {{ $period->ends_on->format('d M Y') }}</td>
                        <td class="px-3 py-2 tabular-nums" data-label="Journals">{{ number_format($period->posted_journals_count) }}</td>
                        <td class="px-3 py-2" data-label="Status">
                            <x-status-badge :tone="$period->status->tone()" :label="$period->status->label()" />
                            @if ($period->closed_at)
                                <p class="mt-1 text-[11px] text-slate-500">Closed {{ $period->closed_at->format('d M Y H:i') }}@if($period->closer) by {{ $period->closer->name }}@endif</p>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right" data-label="Actions">
                            @if ($canManage)
                                @if ($period->isOpen())
                                    <form method="POST" action="{{ route('ledger.periods.close', $period) }}" class="inline" onsubmit="return confirm('Close {{ $period->label() }}? New journals in this month will be blocked.');">
                                        @csrf
                                        <button type="submit" class="font-semibold text-amber-700 hover:underline">Close</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('ledger.periods.reopen', $period) }}" class="inline" onsubmit="return confirm('Re-open {{ $period->label() }}?');">
                                        @csrf
                                        <button type="submit" class="font-semibold text-brand-700 hover:underline">Re-open</button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="no-print">{{ $periods->links() }}</div>
</div>
@endsection
