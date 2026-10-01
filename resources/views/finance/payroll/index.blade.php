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

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5 lg:gap-3">
        @foreach ([
            ['Draft', number_format($stats['draft']), 'text-slate-600'],
            ['Calculated', number_format($stats['calculated']), 'text-sky-700'],
            ['Submitted', number_format($stats['submitted']), 'text-amber-700'],
            ['Approved', number_format($stats['approved']), 'text-indigo-700'],
            ['Paid', number_format($stats['paid']), 'text-emerald-700'],
        ] as [$label, $value, $tone])
            <div class="flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                <p class="mt-0.5 text-base font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $value }}</p>
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
        <div class="psg-stack overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="w-10">#</th>
                        <th>Reference</th>
                        <th>Period</th>
                        <th>Scope</th>
                        <th>Guards & staff</th>
                        <th>Net pay</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($runs as $run)
                        <tr>
                            <td class="text-slate-500" data-label="#">
                                <x-table-serial :paginator="$runs" :index="$loop->index" />
                            </td>
                            <td class="font-mono font-semibold" data-label="Reference">{{ $run->reference }}</td>
                            <td data-label="Period">{{ $run->periodLabel() }}</td>
                            <td class="text-slate-600 dark:text-slate-400" data-label="Scope">
                                @if ($run->site)
                                    {{ $run->site->name }}
                                @elseif ($run->region)
                                    {{ $run->region->name }}
                                @else
                                    Company-wide
                                @endif
                            </td>
                            <td data-label="People">{{ $run->guard_count }}</td>
                            <td data-label="Net pay">{{ \App\Support\Money::format($run->net_total, $run->currency) }}</td>
                            <td data-label="Status"><x-status-badge :tone="$run->status->tone()" :label="$run->status->label()" /></td>
                            <td class="text-right" data-label="Actions">
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
        <x-table-pagination :paginator="$runs" />
    @endif
</div>
@endsection
