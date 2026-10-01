@extends('layouts.app')

@section('title', 'Salary advances')
@section('page-title', 'Salary advances')

@section('content')
<div class="space-y-3">
    <x-page-header title="Salary advances" subtitle="Open and historical advances for guards and staff recovered through payroll.">
        <x-slot:actions>
            <x-finance.scope-tabs
                :current-url="route('advances.index', request()->except('scope', 'page'))"
                :all-url="route('advances.index', array_merge(request()->except('page'), ['scope' => 'all']))"
                :scope="$scope === 'all' ? 'all' : 'current'"
                current-label="Active"
                all-label="All history"
            />
            <x-report-actions :csv="route('advances.export', $exportQuery)" />
        </x-slot:actions>
    </x-page-header>

    <div class="report-print-area space-y-3">
        <x-print.report-header title="Salary advances register" subtitle="Active balances recovered via payroll deductions." />

        <section class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5 lg:gap-3">
            @foreach ([
                ['Active advances', number_format($stats['active']), 'text-amber-700'],
                ['Open balance', \App\Support\Money::format($stats['balance']), 'text-rose-700'],
                ['Guards', number_format($stats['guards']), 'text-sky-700'],
                ['Staff', number_format($stats['staff']), 'text-indigo-700'],
                ['All records', number_format($stats['all']), 'text-slate-600'],
            ] as [$label, $value, $tone])
                <div class="flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                    <p class="mt-0.5 break-words text-xs font-semibold tabular-nums leading-snug text-slate-900 dark:text-slate-100 sm:text-sm">{{ $value }}</p>
                </div>
            @endforeach
        </section>

        <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <form method="GET" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-5 xl:items-end" x-data x-ref="filterForm">
                @if ($scope === 'all')
                    <input type="hidden" name="scope" value="all">
                @endif
                <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" class="sm:col-span-2" placeholder="Person, ID or label" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
                <x-form-field label="Person type" name="person_type" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                    <option value="">All people</option>
                    <option value="guard" @selected(($filters['person_type'] ?? '') === 'guard')>Guards</option>
                    <option value="staff" @selected(($filters['person_type'] ?? '') === 'staff')>Staff</option>
                </x-form-field>
                <x-filter-reset :href="route('advances.index', $scope === 'all' ? ['scope' => 'all'] : [])" />
            </form>
            <p class="mt-2 text-[11px] text-slate-500">New advances are still recorded from a guard or staff profile.</p>
        </section>

        @if ($advances->isEmpty())
            <x-empty-state title="No salary advances found" description="Record an advance on a guard or staff profile to start recovering it through payroll." icon="wallet" />
        @else
            <div class="psg-stack overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <table class="min-w-full divide-y divide-slate-100 text-left text-xs dark:divide-slate-800">
                    <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80">
                        <tr>
                            <th class="w-14 px-3 py-2">#</th>
                            <th class="px-3 py-2">Person</th>
                            <th class="px-3 py-2">Label</th>
                            <th class="px-3 py-2">Original</th>
                            <th class="px-3 py-2">Balance</th>
                            <th class="px-3 py-2">Installment</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2 text-right">Open</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($advances as $advance)
                            @php
                                $personUrl = $advance->guard_id
                                    ? route('guards.show', $advance->guard_id)
                                    : ($advance->staff_id ? route('staff.show', $advance->staff_id) : null);
                                $personName = $advance->assignedGuard?->full_name ?? $advance->assignedStaff?->full_name ?? '—';
                                $personCode = $advance->assignedGuard?->employment_id ?? $advance->assignedStaff?->employment_id;
                                $active = $advance->is_active && (float) $advance->balance_remaining > 0;
                            @endphp
                            <tr>
                                <td class="px-3 py-2" data-label="#"><x-table-serial :paginator="$advances" :index="$loop->index" /></td>
                                <td class="px-3 py-2" data-label="Person">
                                    <p class="font-semibold text-slate-900 dark:text-slate-100">{{ $personName }}</p>
                                    <p class="text-[11px] text-slate-500">
                                        {{ $advance->guard_id ? 'Guard' : 'Staff' }}
                                        @if ($personCode) · {{ $personCode }} @endif
                                    </p>
                                </td>
                                <td class="px-3 py-2" data-label="Label">{{ $advance->label }}</td>
                                <td class="px-3 py-2 font-medium tabular-nums" data-label="Original">{{ \App\Support\Money::format($advance->original_amount) }}</td>
                                <td class="px-3 py-2 font-semibold tabular-nums text-rose-700 dark:text-rose-300" data-label="Balance">{{ \App\Support\Money::format($advance->balance_remaining) }}</td>
                                <td class="px-3 py-2 tabular-nums text-slate-600 dark:text-slate-300" data-label="Installment">
                                    {{ $advance->monthly_installment !== null ? \App\Support\Money::format($advance->monthly_installment) : '—' }}
                                </td>
                                <td class="px-3 py-2" data-label="Status">
                                    <x-status-badge :tone="$active ? 'amber' : 'slate'" :label="$active ? 'Active' : 'Closed'" />
                                </td>
                                <td class="px-3 py-2 text-right" data-label="Open">
                                    @if ($personUrl)
                                        <x-action-icon :href="$personUrl" label="Open profile" icon="eye" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-table-pagination :paginator="$advances" />
        @endif
    </div>
</div>
@endsection
