@extends('layouts.app')

@section('title', 'Payroll')
@section('page-title', 'Payroll')

@section('content')
<div class="space-y-3">
    <x-page-header title="Payroll runs" subtitle="Close periods, calculate pay from completed shifts, approve and export bank files.">
        <x-slot:actions>
            <x-finance.scope-tabs
                :current-url="route('payroll.index', request()->except('scope', 'page'))"
                :all-url="route('payroll.index', array_merge(request()->except('page'), ['scope' => 'all']))"
                :scope="$scope"
                current-label="Open"
                all-label="All history"
            />
            @if ($canSubmit)
                <a href="{{ route('payroll.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">
                    <x-icon name="plus" class="h-3.5 w-3.5" /> New payroll run
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="flex flex-row gap-2 sm:gap-3">
        @foreach ([['Draft','draft','text-slate-600'],['Calculated','calculated','text-sky-700'],['Submitted','submitted','text-amber-700'],['Approved','approved','text-indigo-700'],['Paid','paid','text-emerald-700']] as [$label,$key,$tone])
            <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4 dark:border-slate-700 dark:bg-slate-900">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }} sm:text-[11px]">{{ $label }}</p>
                <p class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100 sm:text-lg">{{ $stats[$key] }}</p>
            </div>
        @endforeach
    </section>

    <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <form method="GET" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4 xl:items-end" x-data x-ref="filterForm">
            @if ($scope === 'all')<input type="hidden" name="scope" value="all">@endif
            <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" class="sm:col-span-2" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Status" name="status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-form-field>
            <x-filter-reset :href="route('payroll.index', $scope === 'all' ? ['scope' => 'all'] : [])" />
        </form>
    </section>

    @if ($runs->isEmpty())
        <x-empty-state title="No payroll runs" description="Open a payroll period to calculate guard pay from completed shifts." icon="payroll" />
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Period</th>
                        <th>Scope</th>
                        <th>Guards</th>
                        <th>Net pay</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($runs as $run)
                        <tr>
                            <td class="font-mono font-semibold">{{ $run->reference }}</td>
                            <td>{{ $run->periodLabel() }}</td>
                            <td class="text-slate-600 dark:text-slate-400">
                                @if ($run->site)
                                    {{ $run->site->name }}
                                @elseif ($run->region)
                                    {{ $run->region->name }}
                                @else
                                    Company-wide
                                @endif
                            </td>
                            <td>{{ $run->guard_count }}</td>
                            <td>{{ \App\Support\Money::format($run->net_total, $run->currency) }}</td>
                            <td><x-status-badge :tone="$run->status->tone()" :label="$run->status->label()" /></td>
                            <td class="text-right">
                                <div class="inline-flex items-center justify-end gap-3">
                                    <a href="{{ route('payroll.show', $run) }}" class="text-brand-700 hover:underline dark:text-brand-400">View</a>
                                    @if (\App\Support\Finance\PayrollAccess::canCancel(auth()->user(), $run))
                                        @php
                                            $cancelLabel = \App\Support\Finance\PayrollAccess::cancelLabel($run->status, auth()->user());
                                            $isDelete = str_contains(strtolower($cancelLabel), 'delete');
                                        @endphp
                                        <x-confirm-action
                                            :action="route('payroll.cancel', $run)"
                                            :title="$isDelete ? 'Delete payroll run' : 'Cancel payroll run'"
                                            :confirm="($isDelete ? 'Delete' : 'Cancel').' '.$run->reference.'? This removes all payslips and restores salary advance balances.'"
                                            :label="$cancelLabel"
                                            :confirm-label="$isDelete ? 'Yes, delete' : 'Yes, cancel'"
                                            cancel-label="Go back"
                                            button-class="text-rose-600 hover:underline dark:text-rose-400 text-xs font-semibold bg-transparent shadow-none p-0 rounded-none"
                                        />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $runs->links() }}
    @endif
</div>
@endsection
