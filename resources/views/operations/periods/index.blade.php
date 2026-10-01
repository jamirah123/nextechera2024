@extends('layouts.app')

@section('title', 'Operational periods')
@section('page-title', 'Operational periods')

@section('content')
<div class="space-y-3">
    <x-page-header title="Operational period lock" subtitle="Finalize a month after reporting/payroll so past duty records cannot be casually rewritten. Historical correction permission still allows audited fixes with a reason.">
        <x-slot:actions>
            <a href="{{ route('organization.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Organization</a>
        </x-slot:actions>
    </x-page-header>

    @if ($errors->has('period'))
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">{{ $errors->first('period') }}</div>
    @endif

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <table class="min-w-full divide-y divide-slate-100 text-left text-xs dark:divide-slate-800">
            <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60">
                <tr>
                    <th class="px-3 py-2">Period</th>
                    <th class="px-3 py-2">Range</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($periods as $period)
                    <tr>
                        <td class="px-3 py-2 font-semibold">{{ $period->label() }}</td>
                        <td class="px-3 py-2 text-slate-500">{{ $period->starts_on->format('d M Y') }} – {{ $period->ends_on->format('d M Y') }}</td>
                        <td class="px-3 py-2">
                            <x-status-badge :tone="$period->status->tone()" :label="$period->status->label()" />
                            @if ($period->closed_at)
                                <p class="mt-1 text-[11px] text-slate-500">Finalized {{ $period->closed_at->format('d M Y H:i') }}@if($period->closer) by {{ $period->closer->name }}@endif</p>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right">
                            @if ($canManage)
                                @if ($period->isOpen())
                                    <form method="POST" action="{{ route('operations.periods.close', $period) }}" class="inline" onsubmit="return confirm('Finalize {{ $period->label() }}? Edits to that month will require historical correction permission and a reason.');">
                                        @csrf
                                        <button type="submit" class="font-semibold text-amber-700 hover:underline">Finalize</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('operations.periods.reopen', $period) }}" class="inline" onsubmit="return confirm('Re-open {{ $period->label() }}?');">
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
    <x-table-pagination :paginator="$periods" />
</div>
@endsection
